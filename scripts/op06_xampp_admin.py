"""OP-06 envelope. Default prepares offline; never retries or cleans a partial run."""
import argparse
import copy
import datetime
import fcntl
import json
import os
from pathlib import Path
import re
import signal
import stat
import subprocess
import time
import uuid

import op05_xampp_admin as base
import op05_resume as prior

ROOT, QA = base.ROOT, base.QA
PLAN = ROOT / 'docs/OP-06-RECETTE-XAMPP-PREPARATION.md'
LOT = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809'
COLUMNS = ('load_state', 'verification_document', 'verified_at')
GRANTS = tuple("GRANT SELECT ON homya_test." + t + " TO 'homya_test_reader'@'localhost'" for t in base.TABLES) + tuple(
    "GRANT SELECT, INSERT ON homya_test." + t + " TO 'homya_test_import'@'localhost'" for t in base.TABLES) + (
    "GRANT UPDATE (load_state, verification_document, verified_at) ON homya_test.archive_lots TO 'homya_test_import'@'localhost'",)
ARTIFACTS = ('scripts/op06_xampp_admin.py', 'scripts/op06_recipe.php', 'scripts/op06_common.php',
             'tests/Support/Op06Recipe.php', 'tests/Support/CatalogueArchiveFixture.php',
             'tests/MariaDB/CatalogueArchiveReadRecipeTest.php', 'tests/MariaDB/CatalogueArchiveWriteRecipeTest.php',
             'app/Services/CatalogueArchiveImporter.php', 'app/Services/CatalogueArchivePayload.php',
             'app/Services/CatalogueArchiveStore.php', 'app/Services/CatalogueCapsule.php',
             'app/Services/ArchiveDatabaseGuard.php', 'app/Services/PrivateLocalFiles.php', 'app/Services/StrictJson.php',
             'app/Services/CatalogueArchiveSchema.php', 'app/Console/Commands/ImportCatalogueArchive.php',
             'config/database.php', 'config/catalogue.php', 'config/homya.php', 'composer.lock',
             'vendor/laravel/framework/src/Illuminate/Database/Connection.php',
             'vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connectors/MySqlConnector.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connectors/MariaDbConnector.php',
             'scripts/op05_xampp_admin.py', 'scripts/op05_resume.py',
             'output/qa-import/op05-reprise-controle-independant.json',
             'output/qa-import/op04-complement-simulation.json', 'output/qa-import/op06-fixtures-proposees.json',
             'output/qa-import/op06-programme.json', 'output/qa-import/op06-reprise-etat-fige.json', 'phpunit.mariadb.xml',
             'output/qa-import/op06-reprise-enregistrement-accord.json',
             'output/qa-import/op06-reprise-autorisation.json',
             'output/qa-import/op06-read-test-avant-isolation.php',
             'output/qa-import/op06-admin-avant-audit-test.py')


def require(value, code):
    base.require(value, code)


def validate_receipt(path):
    path = Path(path)
    require(path.is_absolute() and path.parent == QA and path.resolve() == path, 'RECEIPT_PATH')
    meta = path.stat()
    require(stat.S_IMODE(meta.st_mode) == 0o600 and meta.st_uid == os.getuid() and meta.st_size < 65536, 'RECEIPT_MODE')
    receipt = json.loads(path.read_text(), object_pairs_hook=base.unique_object)
    require(set(receipt) == {'operation', 'schema', 'decision', 'execution_authorized', 'sql_read_recipe_authorized',
                            'lot_key', 'plan_sha256', 'artifacts'}, 'RECEIPT_FIELDS')
    require(receipt['operation'] == 'OP-06' and receipt['schema'] == 'homya_test' and receipt['lot_key'] == LOT
            and receipt['execution_authorized'] is True and receipt['sql_read_recipe_authorized'] is True
            and re.fullmatch(r'DECISION-[0-9]{3,}', receipt['decision']), 'RECEIPT_SCOPE')
    require(receipt['plan_sha256'] == base.digest(PLAN), 'PLAN_CHANGED')
    require(set(receipt['artifacts']) == set(ARTIFACTS), 'ARTIFACT_LIST')
    for name in ARTIFACTS:
        p = ROOT / name
        require(p.resolve() == p and base.digest(p) == receipt['artifacts'][name], 'ARTIFACT_CHANGED')
    return receipt


def validate_metadata(data, stage):
    require(stage in range(10), 'GRANT_STAGE')
    normalized = copy.deepcopy(data)
    expected = []
    for index, table in enumerate(base.TABLES):
        if stage > index:
            expected.append(dict(grantee="'homya_test_reader'@'localhost'", schema='homya_test', table=table, privilege='SELECT', grantable='NO'))
        if stage > 4 + index:
            for privilege in ('INSERT', 'SELECT'):
                expected.append(dict(grantee="'homya_test_import'@'localhost'", schema='homya_test', table=table, privilege=privilege, grantable='NO'))
    order = lambda p: (p['grantee'], p['schema'], p['table'], p['privilege'])
    extra = [p for p in data['privileges'] if p['grantee'] in ("'homya_test_reader'@'localhost'", "'homya_test_import'@'localhost'")]
    require(sorted(extra, key=order) == sorted(expected, key=order), 'DML_RIGHTS_DRIFT')
    normalized['privileges'] = [p for p in data['privileges'] if p not in extra]
    records = []
    for p in data['table_records']:
        if p['user'] not in ('homya_test_reader', 'homya_test_import'):
            records.append(p)
            continue
        rights = {v['privilege'].title() for v in expected if v['grantee'] == "'" + p['user'] + "'@'localhost'" and v['table'] == p['table']}
        require(p['host'] == 'localhost' and p['schema'] == 'homya_test' and p['table'] in base.TABLES
                and set(p['privileges'].split(',')) == rights and bool(rights), 'PHYSICAL_DML_DRIFT')
        require(p['column'] == ('Update' if stage == 9 and p['user'] == 'homya_test_import' and p['table'] == 'archive_lots' else ''), 'COLUMN_SUMMARY_DRIFT')
    require(len(data['table_records']) - len(records) == min(stage, 8), 'DML_RECORD_COUNT')
    normalized['table_records'] = records
    expected_columns = [dict(grantee="'homya_test_import'@'localhost'", schema='homya_test', table='archive_lots',
                             column=c, privilege='UPDATE', grantable='NO') for c in COLUMNS] if stage == 9 else []
    require(data['column_privileges'] == expected_columns, 'COLUMN_RIGHTS_DRIFT')
    physical_columns = [dict(user='homya_test_import', host='localhost', schema='homya_test', table='archive_lots', column=c, privileges='Update') for c in COLUMNS] if stage == 9 else []
    require(data['column_records'] == physical_columns and data['unexpected_rights']['column'] == len(physical_columns), 'PHYSICAL_COLUMN_DRIFT')
    normalized['unexpected_rights']['column'] = 0
    prior.validate_metadata(normalized, 2, True)


def inspect(stage, empty=True):
    data = base.inspect()
    users = ','.join("'" + u + "'" for u in base.USERS)
    data['column_privileges'] = [json.loads(line) for line in base.sql(
        "SELECT JSON_OBJECT('grantee',GRANTEE,'schema',TABLE_SCHEMA,'table',TABLE_NAME,'column',COLUMN_NAME,'privilege',PRIVILEGE_TYPE,'grantable',IS_GRANTABLE) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE IN (" +
        ','.join("CONCAT(CHAR(39),'" + u + "',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))" for u in base.USERS) + ") ORDER BY GRANTEE,TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,PRIVILEGE_TYPE").splitlines()]
    data['column_records'] = [json.loads(line) for line in base.sql(
        "SELECT JSON_OBJECT('user',User,'host',Host,'schema',Db,'table',Table_name,'column',Column_name,'privileges',Column_priv) FROM mysql.columns_priv WHERE User IN (" + users + ") ORDER BY User,Db,Table_name,Column_name").splitlines()]
    validate_metadata(data, stage)
    base.journal_check('final')
    frozen = json.loads((QA / 'op05-reprise-controle-independant.json').read_text())['result']['physical_ddl']
    ddl = {t: base.sql('SHOW CREATE TABLE homya_test.' + t).strip() for t in (*base.TABLES, 'migrations')}
    require(ddl == frozen, 'PHYSICAL_DDL_CHANGED')
    counts = {t: int(base.sql('SELECT COUNT(*) FROM homya_test.' + t).strip()) for t in base.TABLES}
    if empty:
        require(all(c == 0 for c in counts.values()), 'INITIAL_ARCHIVE_NOT_EMPTY')
    return dict(metadata=data, counts=counts, physical_ddl=ddl)


def save(path, data):
    require(path.resolve() == path and not path.exists(), 'REPORT_COLLISION')
    with path.open('x') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
        f.write('\n')


def grant(receipt, checker=inspect, writer=base.sql, recorder=lambda i, d: None):
    validate_receipt(receipt)
    checker(0)
    for i, statement in enumerate(GRANTS):
        validate_receipt(receipt)
        checker(i)
        writer(statement)
        recorder(i + 1, checker(i + 1))


def confirm_wait(blocking, blocked):
    require(blocking > 0 and blocked > 0 and blocking != blocked, 'WORKER_IDS')
    ids = str(blocking) + ',' + str(blocked)
    workers = [json.loads(line) for line in base.sql("SELECT JSON_OBJECT('id',ID,'user',USER,'database',DB) FROM information_schema.PROCESSLIST WHERE ID IN (" + ids + ") ORDER BY ID").splitlines()]
    require(len(workers) == 2 and all(w['user'] == 'homya_test_import' and w['database'] == 'homya_test' for w in workers), 'WORKER_IDENTITIES')
    query = "SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX a ON w.blocking_trx_id=a.trx_id JOIN information_schema.INNODB_TRX b ON w.requesting_trx_id=b.trx_id WHERE a.trx_mysql_thread_id=" + str(blocking) + " AND b.trx_mysql_thread_id=" + str(blocked)
    deadline = time.monotonic() + 3
    while time.monotonic() < deadline:
        if int(base.sql(query).strip()) > 0:
            return {'blocking': blocking, 'blocked': blocked, 'wait_confirmed': True}
        time.sleep(0.05)
    raise RuntimeError('REAL_LOCK_WAIT_NOT_OBSERVED')


def validate_run(run):
    require(run.is_absolute() and run.parent == QA and run.resolve() == run
            and re.fullmatch(r'op06-run-[a-zA-Z0-9-]+', run.name), 'RUN_PATH')
    require(stat.S_IMODE(run.stat().st_mode) == 0o700 and run.stat().st_uid == os.getuid(), 'RUN_MODE')
    return run


def audit_result(run):
    validate_run(run)
    proof = json.loads((run / 'recipe-result.json').read_text())
    require(proof['status'] == 'RECIPE_COMPLETED_NOT_ACTIVATED', 'RECIPE_RESULT')
    state = inspect(9, False)
    require(state['counts'] == {'archive_lots': 5, 'archive_categories': 67, 'archive_products': 3711, 'archive_translations': 23890}, 'FINAL_COUNTS')
    fixtures = {r['scenario']: r['lot_key'] for r in json.loads((QA / 'op06-fixtures-proposees.json').read_text())['lots']}
    expected = {LOT: 'verified', fixtures['transport']: 'verified', fixtures['concurrency']: 'verified', fixtures['failure']: 'failed', fixtures['partial']: 'prepared'}
    rows = [json.loads(line) for line in base.sql("SELECT JSON_OBJECT('key',lot_key,'state',load_state) FROM homya_test.archive_lots ORDER BY lot_key").splitlines()]
    require({r['key']: r['state'] for r in rows} == expected, 'FINAL_LOTS')
    for key, load_state in expected.items():
        counts = {t: int(base.sql("SELECT COUNT(*) FROM homya_test." + t + " WHERE lot_key='" + key + "'").strip()) for t in base.TABLES[1:]}
        target = dict(zip(base.TABLES[1:], (65, 3709, 23878) if key == LOT else (1, 1, 6) if load_state == 'verified' else (0, 0, 0)))
        require(counts == target, 'FINAL_CHILD_COUNTS')
    return state


def resume_source(previous):
    frozen = json.loads((QA / 'op06-reprise-etat-fige.json').read_text())
    run = validate_run(Path(previous))
    require(str(run) == frozen['previous_run'], 'RESUME_RUN_CHANGED')
    for name, expected in frozen['previous_proofs'].items():
        p = Path(name)
        require(p.resolve() == p and base.digest(p) == expected, 'RESUME_PROOF_CHANGED')
    files = [Path(name) for name in frozen['previous_proofs'] if Path(name).parent == run]
    require({p.name for p in run.iterdir()} == {p.name for p in files}, 'RESUME_FILE_LIST_CHANGED')
    failures = [p for p in files if re.fullmatch(r'failure-[0-9]+\.json', p.name)]
    require(len(failures) == 1, 'RESUME_FAILURE_SCOPE')
    require(json.loads((run / 'status.json').read_text())['status'] == 'STOP_INSPECT_NO_RETRY_OR_CLEANUP'
            and json.loads(failures[0].read_text())['code'] == 'FAILURE_UNEXPECTED_RESULT', 'RESUME_FAILURE_SCOPE')
    for step in ('integrity', 'collision', 'transport'):
        require(json.loads((run / (step + '.json')).read_text())['status'] == 'STEP_COMPLETED', 'RESUME_COMPLETED_STEP_MISSING')
    return frozen


def resume_initial(previous, checker=inspect, reader=base.sql):
    frozen = resume_source(previous)
    state = checker(9, False)
    require(state['counts'] == frozen['existing_counts'], 'RESUME_COUNTS_CHANGED')
    rows = [json.loads(line) for line in reader("SELECT JSON_OBJECT('key',lot_key,'state',load_state,'verified',verified_at IS NOT NULL,'proof',verification_document IS NOT NULL) FROM homya_test.archive_lots ORDER BY lot_key").splitlines()]
    require(rows == [{'key': frozen['allowed_existing_lot'], 'state': 'verified', 'verified': True, 'proof': True}], 'RESUME_LOTS_CHANGED')
    return state


def audit_execution_link(current, original, recorded, parent_hash):
    """Read-only verification may fix test isolation while preserving the execution receipt."""
    extras = {'output/qa-import/op06-reprise-enregistrement-accord.json',
              'output/qa-import/op06-reprise-autorisation.json',
              'output/qa-import/op06-read-test-avant-isolation.php',
              'output/qa-import/op06-admin-avant-audit-test.py'}
    require(current['decision'] == original['decision'] == recorded['decision'] == 'DECISION-033'
            and original['operation'] == 'OP-06' and original['schema'] == 'homya_test'
            and original['lot_key'] == current['lot_key'] == LOT
            and original['plan_sha256'] == current['plan_sha256']
            and original['execution_authorized'] is True and recorded['execution_authorized'] is True
            and recorded['registry_readback_verified'] is True
            and current['artifacts']['output/qa-import/op06-reprise-autorisation.json'] == recorded['receipt_sha256'] == parent_hash,
            'AUDIT_EXECUTION_SOURCE_CHANGED')
    require(set(original['artifacts']) == set(current['artifacts']) - extras, 'AUDIT_SOURCE_ARTIFACTS')
    changed = {p for p, h in original['artifacts'].items() if current['artifacts'][p] != h}
    require(changed == {'scripts/op06_xampp_admin.py', 'tests/MariaDB/CatalogueArchiveReadRecipeTest.php'}, 'AUDIT_UNAPPROVED_CODE_DRIFT')
    require(original['artifacts']['scripts/op06_xampp_admin.py'] == current['artifacts']['output/qa-import/op06-admin-avant-audit-test.py']
            and original['artifacts']['tests/MariaDB/CatalogueArchiveReadRecipeTest.php'] == current['artifacts']['output/qa-import/op06-read-test-avant-isolation.php'], 'AUDIT_SOURCE_BACKUPS')


def execute(args):
    validate_receipt(args.receipt)
    for role, access in (('import', args.import_access), ('reader', args.reader_access)):
        require(access and Path(access).name == '.env.homya_test_' + role
                and access.startswith(str(ROOT / 'storage/app/private/database-access') + '/'), 'ACCESS_SCOPE')
    require(os.isatty(0), 'INTERACTIVE_REQUIRED')
    require(input('Accord pour cette exécution OP-06 et ses empreintes obtenu ? (yes/no) [no]: ') == 'yes', 'CONFIRMATION_REQUIRED')
    run = QA / ('op06-run-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + str(uuid.uuid4()))
    run.mkdir(mode=0o700)
    try:
        descriptor = os.open(QA / '.op06.lock', os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
        with os.fdopen(descriptor, 'a+') as lock:
            os.chmod(QA / '.op06.lock', 0o600)
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            parent = {'pid': os.getpid(), 'receipt_sha256': base.digest(Path(args.receipt))}
            if args.resume_from:
                parent['resume_source'] = args.resume_from
            save(run / 'parent.json', parent)
            save(run / 'initial.json', resume_initial(args.resume_from) if args.resume_from else inspect(0))
            if not args.resume_from:
                grant(args.receipt, recorder=lambda i, d: save(run / f'grant-{i}.json', d))
            command = ['/opt/homebrew/bin/php', '-d', 'memory_limit=512M', str(ROOT / 'scripts/op06_recipe.php'), '--execute', '--receipt=' + args.receipt,
                       '--import-access=' + args.import_access, '--reader-access=' + args.reader_access, '--run-dir=' + str(run)]
            if args.resume_from:
                command.append('--resume')
            # Long capsule checks remain supervised; each individual poll <= 1 second.
            process = subprocess.Popen(command, cwd=ROOT, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True)
            deadline = time.monotonic() + 1800
            while process.poll() is None and time.monotonic() < deadline:
                time.sleep(1)
            if process.poll() is None:
                os.killpg(process.pid, signal.SIGTERM)
                process.wait(timeout=10)
                raise RuntimeError('RECIPE_TIMEOUT_STATUS_TO_INSPECT')
            require(process.returncode == 0, 'RECIPE_FAILED_STATUS_TO_INSPECT')
            save(run / 'final-admin.json', audit_result(run))
        save(run / 'status.json', {'status': 'RECIPE_ADMIN_VERIFIED_NOT_ACTIVATED', 'run_dir': str(run)})
        return {'status': 'RECIPE_ADMIN_VERIFIED_NOT_ACTIVATED', 'run_dir': str(run)}
    except Exception:
        save(run / 'status.json', {'status': 'STOP_INSPECT_NO_RETRY_OR_CLEANUP'})
        raise


def main():
    os.umask(0o077)
    p = argparse.ArgumentParser(description=__doc__)
    modes = p.add_mutually_exclusive_group()
    modes.add_argument('--execute', action='store_true')
    modes.add_argument('--validate-receipt', action='store_true')
    modes.add_argument('--confirm-wait', action='store_true')
    modes.add_argument('--audit-final', action='store_true')
    modes.add_argument('--inspect', type=int, choices=range(10))
    modes.add_argument('--inspect-retained', action='store_true')
    p.add_argument('--receipt')
    p.add_argument('--import-access')
    p.add_argument('--reader-access')
    p.add_argument('--blocking', type=int)
    p.add_argument('--blocked', type=int)
    p.add_argument('--run-dir')
    p.add_argument('--resume-from')
    args = p.parse_args()
    try:
        require(not args.resume_from or args.execute, 'RESUME_EXECUTION_ONLY')
        if args.execute:
            require(not args.blocking and not args.blocked and not args.run_dir, 'ARGUMENT_SCOPE')
            result = execute(args)
        elif args.validate_receipt:
            require(args.receipt and not any((args.import_access, args.reader_access, args.blocking, args.blocked, args.run_dir)), 'ARGUMENT_SCOPE')
            validate_receipt(args.receipt)
            result = {'status': 'RECEIPT_VALIDATED_NO_SQL'}
        elif args.confirm_wait:
            require(args.receipt and args.blocking and args.blocked and not any((args.import_access, args.reader_access, args.run_dir)), 'ARGUMENT_SCOPE')
            validate_receipt(args.receipt)
            result = confirm_wait(args.blocking, args.blocked)
        elif args.audit_final:
            require(args.receipt and args.run_dir and not any((args.import_access, args.reader_access, args.blocking, args.blocked)), 'ARGUMENT_SCOPE')
            current = validate_receipt(args.receipt)
            run = validate_run(Path(args.run_dir))
            parent_path = run / 'parent.json'
            require(parent_path.resolve() == parent_path and stat.S_IMODE(parent_path.stat().st_mode) == 0o600
                    and parent_path.stat().st_uid == os.getuid(), 'RUN_PARENT_MODE')
            parent = json.loads(parent_path.read_text())
            if parent['receipt_sha256'] != base.digest(Path(args.receipt)):
                original = json.loads((QA / 'op06-reprise-autorisation.json').read_text(), object_pairs_hook=base.unique_object)
                recorded = json.loads((QA / 'op06-reprise-enregistrement-accord.json').read_text(), object_pairs_hook=base.unique_object)
                audit_execution_link(current, original, recorded, parent['receipt_sha256'])
            result = audit_result(run)
        elif args.inspect_retained:
            require(args.receipt and args.run_dir and not any((args.import_access, args.reader_access, args.blocking, args.blocked)), 'ARGUMENT_SCOPE')
            validate_receipt(args.receipt)
            result = resume_initial(args.run_dir)
        elif args.inspect is not None:
            require(args.receipt and not any((args.import_access, args.reader_access, args.blocking, args.blocked, args.run_dir)), 'ARGUMENT_SCOPE')
            validate_receipt(args.receipt)
            result = inspect(args.inspect)
        else:
            require(not any((args.receipt, args.import_access, args.reader_access, args.blocking, args.blocked, args.run_dir)), 'ARGUMENT_SCOPE')
            result = {'status': 'OP06_PREPARED_NO_SQL', 'grants': GRANTS, 'artifacts': ARTIFACTS, 'lot_key': LOT}
        print(json.dumps(result))
        return 0
    except Exception as error:
        code = str(error) if type(error) is RuntimeError and re.fullmatch('[A-Z0-9_]+', str(error)) else 'IO_OR_SCOPE_ERROR'
        print(json.dumps({'status': 'STOP_NO_RETRY_OR_CLEANUP', 'code': code}))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
