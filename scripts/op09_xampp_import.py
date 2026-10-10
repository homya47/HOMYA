"""OP-09. Offline by default; SQL/export/import require a new human execution agreement.

No SQL, native process or private credential loading on import/prepare. No cleanup/retry.
"""
import argparse
from contextlib import contextmanager
import datetime
import fcntl
import json
import os
from pathlib import Path
import re
import shutil
import signal
import stat
import subprocess
import time
import uuid

import op08_xampp_admin as history
from op07_dump import validate_dump, tokens

ROOT, QA = history.ROOT, history.QA
prior, base = history.prior, history.base
PLAN = ROOT/'docs/OP-09-IMPORT-LOCAL-PREPARATION.md'
AGREEMENT = QA/'op09-execution-enregistrement-accord.json'
PROGRAM = QA/'op09-outils-programme.json'
INSPECTION = QA/'op09-preparation-inspection.json'
TOOLS_AGREEMENT = QA/'op09-outils-enregistrement-accord.json'
MYSQL, DUMP, PHP = history.MYSQL, prior.DUMP, history.PHP
PYTHON = '/usr/bin/python3'
LOT, TABLES = prior.LOT, history.TABLES
BACKUPS = ROOT/'storage/app/private/database-backups/op09'
MAX_SECONDS, RESERVE_BYTES, DUMP_CAP = 1800, 268435456, 1048576
ACTIVE_DEADLINE = None
INITIAL_COUNTS = {t: 4 if t == 'migrations' else 0 for t in TABLES}
FINAL_COUNTS = dict(archive_lots=1, archive_categories=65, archive_products=3709,
                    archive_translations=23878, migrations=4)
PAYLOAD_DIGEST = '49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1'
ARTIFACTS = ('scripts/op09_xampp_import.py', 'scripts/op09_catalogue.php',
             'scripts/test_op09_xampp_import.py', 'tests/Feature/Op09CataloguePreparationTest.php',
             'output/qa-import/op09-preparation-inspection.json', 'output/qa-import/op09-preparation-verification.json',
             'output/qa-import/op09-plan-valide-v01.md', 'output/qa-import/op09-outils-enregistrement-accord.json',
             'output/qa-import/op08-reprise-cloture-registre.json', 'output/qa-import/op08-reprise-synthese-verification.json',
             'output/qa-import/op08-reprise-controle-independant.json',
             'storage/app/private/catalogue-archives/'+LOT+'/manifest.json')
require, digest, save = prior.require, prior.digest, prior.save


def protected_files():
    data = json.loads(INSPECTION.read_text(), object_pairs_hook=base.unique_object)['preservation_before']
    require(all(Path(p).is_absolute() and Path(p).resolve() == Path(p)
                and digest(p) == h for p, h in data.items()), 'PROTECTED_FILE_CHANGED')
    return data


def validate_receipt(path):
    r = prior.safe_json(Path(path), QA)
    fields = {'operation', 'schema', 'lot_key', 'decision', 'execution_authorized',
              'agreement_sha256', 'plan_sha256', 'artifacts', 'tools'}
    require(set(r) == fields, 'RECEIPT_FIELDS')
    require(r['operation'] == 'OP-09' and r['schema'] == 'homya_local' and r['lot_key'] == LOT
            and r['execution_authorized'] is True and re.fullmatch(r'DECISION-[0-9]{3,}', r['decision'])
            and int(r['decision'].split('-')[1]) > 40, 'NEW_EXECUTION_AGREEMENT_REQUIRED')
    require(digest(PLAN) == r['plan_sha256'] and set(r['artifacts']) == set(ARTIFACTS), 'PLAN_OR_ARTIFACT_LIST')
    for p, h in r['artifacts'].items():
        require((ROOT/p).resolve() == ROOT/p and digest(ROOT/p) == h, 'ARTIFACT_CHANGED')
    require(set(r['tools']) == {MYSQL, DUMP, PHP, PYTHON}
            and all(digest(p) == h for p, h in r['tools'].items()), 'TOOL_CHANGED')
    a = prior.safe_json(AGREEMENT, QA)
    require(digest(AGREEMENT) == r['agreement_sha256'] and a.get('decision') == r['decision']
            and a.get('operation') == 'OP-09' and a.get('schema') == 'homya_local' and a.get('lot_key') == LOT
            and a.get('sql_authorized') is True and a.get('execution_authorized') is True
            and a.get('registry_readback_verified') is True
            and digest(prior.REGISTRY) == a.get('registry_after_sha256'), 'EXECUTION_AGREEMENT_REQUIRED')
    require(a.get('plan_sha256') == r['plan_sha256'] and a.get('programme_sha256') == digest(PROGRAM), 'APPROVED_PROGRAM_REQUIRED')
    program = prior.safe_json(PROGRAM, QA)
    require(program.get('operation') == 'OP-09' and program.get('schema') == 'homya_local'
            and program.get('lot_key') == LOT and program.get('execution_authorized') is False
            and program.get('plan_sha256') == r['plan_sha256'] and program.get('artifacts') == r['artifacts']
            and program.get('tools') == r['tools'], 'APPROVED_PROGRAM_MISMATCH')
    sections = prior.REGISTRY.read_text().split('#### '+r['decision']+'\n')
    require(len(sections) == 2, 'REGISTRY_ENTRY_REQUIRED')
    entry = sections[1].split('#### ')[0]
    require(re.search(r'\| Projet / périmètre \| HOMYA[^\n]*OP-09[^\n]*exécution', entry)
            and 'VALIDÉ' in entry and LOT in entry and 'homya_local' in entry, 'REGISTRY_EXECUTION_SCOPE')
    protected_files()
    return r


def sql(query, receipt):
    validate_receipt(receipt)
    require(isinstance(query, str) and (query.startswith('SELECT ') or query.startswith('SHOW CREATE TABLE ')), 'ADMIN_READONLY_REQUIRED')
    parsed = tokens(query)  # A semicolon inside a quoted row framing value is data.
    require(not any(kind == 'word' and value in ('INTO', 'OUTFILE', 'DUMPFILE') for kind, value in parsed), 'ADMIN_READONLY_REQUIRED')
    budget = 20 if ACTIVE_DEADLINE is None else min(20, ACTIVE_DEADLINE-time.monotonic())
    require(budget > 0, 'OPERATION_DEADLINE')
    result = subprocess.run([MYSQL, '--no-defaults', '--protocol=SOCKET', '--socket='+base.SOCKET,
                             '--user=root', '--skip-password', '--batch', '--raw', '--skip-column-names',
                             '--connect-timeout=5'], input=query+';\n', env=prior.environment(),
                            capture_output=True, text=True, timeout=budget)
    if result.returncode:
        match = re.search(r'ERROR\s+(\d+)', result.stderr)
        raise RuntimeError('XAMPP_SQL_ERROR_'+(match.group(1) if match else 'UNCLASSIFIED'))
    return result.stdout


@contextmanager
def readonly_definitions(receipt):
    """Scoped reuse of pure audit definitions; never retarget old workflows or authority."""
    old_base, old_history = base.sql, history.sql
    try:
        base.sql = lambda q: sql(q, receipt)
        history.sql = lambda q, _r: sql(q, receipt)
        yield
    finally:
        base.sql, history.sql = old_base, old_history


def inspect(receipt, loaded=False):
    with readonly_definitions(receipt):
        data = base.inspect()
        users = ','.join("'"+u+"'" for u in base.USERS)
        grantees = ','.join("CONCAT(CHAR(39),'"+u+"',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))" for u in base.USERS)
        data['column_privileges'] = [json.loads(x) for x in sql("SELECT JSON_OBJECT('grantee',GRANTEE,'schema',TABLE_SCHEMA,'table',TABLE_NAME,'column',COLUMN_NAME,'privilege',PRIVILEGE_TYPE,'grantable',IS_GRANTABLE) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE IN ("+grantees+") ORDER BY GRANTEE,TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME", receipt).splitlines()]
        data['column_records'] = [json.loads(x) for x in sql("SELECT JSON_OBJECT('user',User,'host',Host,'schema',Db,'table',Table_name,'column',Column_name,'privileges',Column_priv) FROM mysql.columns_priv WHERE User IN ("+users+") ORDER BY User,Db,Table_name,Column_name", receipt).splitlines()]
        history.validate_metadata(data, 12, 'final')
        ddl = history.reference()['physical_ddl']
        for schema in ('homya_test', 'homya_restore_op07', 'homya_local'):
            require({t: sql('SHOW CREATE TABLE `'+schema+'`.`'+t+'`', receipt).strip() for t in TABLES} == ddl, 'DDL_DRIFT')
        rows = [json.loads(x) for x in sql("SELECT JSON_OBJECT('id',id,'migration',migration,'batch',batch) FROM homya_local.migrations ORDER BY id", receipt).splitlines()]
        require(rows == [{'id': i, 'migration': m, 'batch': 1} for i, m in enumerate(base.MIGRATIONS, 1)], 'JOURNAL_DRIFT')
        counts = {t: int(sql('SELECT COUNT(*) FROM `homya_local`.`'+t+'`', receipt).strip()) for t in TABLES}
        require(counts == (FINAL_COUNTS if loaded else INITIAL_COUNTS), 'LOCAL_COUNTS_OR_COLLISION')
        require(sql("SELECT UNIQUE_CONSTRAINT_SCHEMA,DELETE_RULE,UPDATE_RULE,COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='homya_local' GROUP BY UNIQUE_CONSTRAINT_SCHEMA,DELETE_RULE,UPDATE_RULE", receipt).strip() == 'homya_local\tRESTRICT\tRESTRICT\t4', 'LOCAL_FK_TARGET')
        if loaded:
            lots = sql('SELECT lot_key,load_state FROM homya_local.archive_lots', receipt).strip()
            require(lots == LOT+'\tverified', 'LOT_STATE')
        history.restore_rights(receipt)
    data['server'].pop('connection_id', None)  # Independent native sessions have different IDs.
    return {'metadata': data, 'counts': counts, 'physical_ddl': ddl, 'journal': rows}


def preservation(receipt):
    with readonly_definitions(receipt):
        return history.preservation(receipt)


def access_files():
    files = history.access_files()  # Only metadata; never credential content.
    return {k: files[k] for k in ('import', 'reader')}


def originals(deadline):
    manifest = ROOT/'storage/app/private/catalogue-archives'/LOT/'manifest.json'
    require(digest(manifest) == LOT, 'CAPSULE_MANIFEST_CHANGED')
    data = json.loads(manifest.read_text(), object_pairs_hook=base.unique_object)
    count = size = 0
    for item in data['files']:
        require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
        p = ROOT/item['path']
        require(p.resolve() == p and p.is_file() and p.stat().st_size == item['bytes']
                and digest(p) == item['sha256'], 'ORIGINAL_CHANGED')
        count += 1; size += item['bytes']
    require(count == 22239 and size == 3477829369, 'ORIGINAL_COUNTS')
    return {'files': count, 'bytes': size, 'manifest_sha256': LOT}


def dump_command():
    return [DUMP, '--no-defaults', '--protocol=SOCKET', '--socket='+base.SOCKET,
            '--user=root', '--skip-password', *prior.FLAGS, 'homya_local', *TABLES]


def backup(receipt, deadline, audit):
    validate_receipt(receipt)
    prior.private_directory(BACKUPS.parent); prior.private_directory(BACKUPS)
    require(shutil.disk_usage(BACKUPS).free > RESERVE_BYTES+DUMP_CAP, 'BACKUP_SPACE')
    directory = BACKUPS/(datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+str(uuid.uuid4()))
    require(not directory.exists(), 'BACKUP_COLLISION'); prior.private_directory(directory)
    path = directory/'local-before.sql'
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600), 'wb') as out:
        process = subprocess.Popen(dump_command(), env=prior.environment(), stdout=out,
                                   stderr=subprocess.PIPE, start_new_session=True)
        try:
            while process.poll() is None:
                require(time.monotonic() < deadline and path.stat().st_size <= DUMP_CAP, 'DUMP_LIMIT')
                time.sleep(0.1)
            _, error = process.communicate(timeout=5)
            require(process.returncode == 0 and not error and 0 < path.stat().st_size <= DUMP_CAP, 'DUMP_FAILED_DETAILS_WITHHELD')
            out.flush(); os.fsync(out.fileno())
        finally:
            if process.poll() is None:
                os.killpg(process.pid, signal.SIGTERM)
                try: process.communicate(timeout=5)
                except subprocess.TimeoutExpired:
                    os.killpg(process.pid, signal.SIGKILL); process.communicate(timeout=5)
    with path.open() as stream:
        proof = validate_dump(stream, audit['physical_ddl'], INITIAL_COUNTS)
    require(inspect(receipt) == audit, 'PREIMPORT_CHANGED_DURING_DUMP')
    # Dump contains only four fixed journal rows: check their values, not just row counts.
    from op07_dump import statements, tokens
    with path.open() as stream:
        insertions = [tokens(s) for s, _ in statements(stream) if s.startswith('INSERT INTO')]
    expected = []
    for i, name in enumerate(base.MIGRATIONS, 1):
        expected.append(tokens("INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ("+str(i)+",'"+name+"',1)"))
    require(insertions == expected, 'DUMP_JOURNAL_VALUES')
    path.chmod(0o400)
    proof.update(path=str(path.relative_to(ROOT)), sha256=digest(path), bytes=path.stat().st_size,
                 journal_exact=True, restoration_tested=False)
    save(directory/'verification.json', proof)
    return proof


def helper_ticket(receipt, run, mode, wrapper_pid):
    authority = validate_receipt(receipt)
    require(mode in ('import', 'read') and wrapper_pid > 1, 'HELPER_SCOPE')
    require(run.parent == QA and run.resolve() == run and run.name.startswith('op09-run-'), 'RUN_PATH')
    require(run.is_dir(), 'RUN_MISSING'); prior.private_directory(run)
    consumed = prior.safe_json(QA/('.op09-consumed-'+authority['decision']+'.json'), QA)
    require(consumed == {'decision': authority['decision'], 'run': str(run),
                         'receipt_sha256': digest(receipt), 'wrapper_pid': wrapper_pid}, 'EXECUTION_MARKER')
    ticket = prior.safe_json(run/('ticket-'+mode+'.json'), run)
    require(ticket == {'mode': mode, 'receipt_sha256': digest(receipt), 'wrapper_pid': wrapper_pid}, 'HELPER_TICKET')
    fd = os.open(run/('used-'+mode+'.json'), os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as f: json.dump({'mode': mode, 'consumed': True}, f)


def php_call(mode, receipt, run, access, deadline):
    validate_receipt(receipt)
    require(mode in ('import', 'read') and time.monotonic() < deadline, 'HELPER_SCOPE_OR_DEADLINE')
    save(run/('ticket-'+mode+'.json'), {'mode': mode, 'receipt_sha256': digest(receipt), 'wrapper_pid': os.getpid()})
    cmd = [PHP, '-d', 'memory_limit=512M', str(ROOT/'scripts/op09_catalogue.php'), '--'+mode,
           '--receipt='+str(receipt), '--run-dir='+str(run), '--access='+access]
    process = subprocess.Popen(cmd, env=prior.environment(), stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, text=True, cwd=ROOT, start_new_session=True)
    try:
        out, err = process.communicate(timeout=max(0.01, deadline-time.monotonic()))
    except subprocess.TimeoutExpired:
        os.killpg(process.pid, signal.SIGTERM)
        try: process.communicate(timeout=5)
        except subprocess.TimeoutExpired:
            os.killpg(process.pid, signal.SIGKILL); process.communicate(timeout=5)
        raise RuntimeError('PHP_TIMEOUT_STATUS_TO_INSPECT') from None
    require(process.returncode == 0 and not err, 'PHP_FAILED_DETAILS_WITHHELD')
    data = json.loads(out, object_pairs_hook=base.unique_object)
    require(data == {'status': 'ARCHIVE_VERIFIED_NOT_ACTIVATED' if mode == 'import' else 'ARCHIVE_READ_VERIFIED_NOT_ACTIVATED',
                     'lot_key': LOT, 'payload_sha256': PAYLOAD_DIGEST,
                     'counts': {'lots': 1, 'categories': 65, 'products': 3709, 'translations': 23878,
                                'specifications': 41977, 'variants': 92, 'gallery_associations': 18454,
                                'overlays': 35, 'review_rows': 627, 'empty_brands': 6}, 'activated': False}, 'HELPER_RESULT')
    return data


def workflow(receipt, run, deadline):
    validate_receipt(receipt)
    require(not (ROOT/'bootstrap/cache/config.php').exists() and not list((ROOT/'database/schema').glob('*')), 'CACHED_CONFIG_OR_SCHEMA_DUMP')
    runtime = prior.runtime_witnesses(); files = protected_files(); access = access_files()
    before = inspect(receipt); save(run/'before.json', before)
    protected = preservation(receipt); save(run/'protected-before.json', protected)
    capsule = prior.verify_media(deadline); source = originals(deadline)
    save(run/'files-before.json', {'capsule': capsule, 'originals': source})
    snapshot = backup(receipt, deadline, before); save(run/'backup.json', snapshot)
    require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
    validate_receipt(receipt)
    require(inspect(receipt) == before and preservation(receipt) == protected
            and prior.runtime_witnesses() == runtime and protected_files() == files, 'PREIMPORT_DRIFT')
    save(run/'import-attempted.json', {'attempted': True, 'lot_key': LOT})
    save(run/'import.json', php_call('import', receipt, run, access['import'], deadline))
    save(run/'read.json', php_call('read', receipt, run, access['reader'], deadline))
    save(run/'final.json', inspect(receipt, loaded=True))
    after = preservation(receipt); save(run/'protected-after.json', after)
    require(after == protected and prior.runtime_witnesses() == runtime and protected_files() == files
            and prior.verify_media(deadline) == capsule and originals(deadline) == source, 'PRESERVATION_DRIFT')
    require(digest(ROOT/snapshot['path']) == snapshot['sha256'] and time.monotonic() < deadline, 'BACKUP_OR_DEADLINE_DRIFT')
    validate_receipt(receipt)
    save(run/'status.json', {'status': 'LOCAL_ARCHIVE_VERIFIED_NOT_ACTIVATED', 'counts': FINAL_COUNTS,
                           'lot_key': LOT, 'import_calls': 1, 'journal_changed': False, 'activated': False,
                           'backup_restoration_tested': False, 'preservation_verified': True})


def execute(receipt):
    global ACTIVE_DEADLINE
    receipt = Path(receipt); r = validate_receipt(receipt)
    fd = os.open(QA/'.op08.lock', os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as lock:
        m = os.fstat(lock.fileno())
        require(stat.S_ISREG(m.st_mode) and stat.S_IMODE(m.st_mode) == 0o600
                and m.st_uid == os.getuid() and m.st_nlink == 1, 'LOCK_FILE')
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        run = QA/('op09-run-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+str(uuid.uuid4()))
        require(not run.exists() and run.resolve() == run, 'RUN_COLLISION')
        marker = QA/('.op09-consumed-'+r['decision']+'.json')
        consumed = os.open(marker, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with os.fdopen(consumed, 'w') as f: json.dump({'decision': r['decision'], 'run': str(run), 'receipt_sha256': digest(receipt), 'wrapper_pid': os.getpid()}, f)
        run.mkdir(mode=0o700); save(run/'receipt.json', r)
        try:
            ACTIVE_DEADLINE = time.monotonic()+MAX_SECONDS
            workflow(receipt, run, ACTIVE_DEADLINE)
            return run
        except Exception as error:
            code = str(error) if isinstance(error, RuntimeError) and re.fullmatch('[A-Z0-9_]+', str(error)) else 'DETAILS_WITHHELD'
            save(run/'failure.json', {'status': 'STOP_INSPECT_NO_RETRY', 'code': code,
                                     'partial_or_committed_import_possible': (run/'import-attempted.json').exists(),
                                     'retry': False, 'cleanup': False, 'activated': False})
            raise
        finally:
            ACTIVE_DEADLINE = None


def prepare():
    protected_files()
    return {'operation': 'OP-09', 'status': 'TOOLS_PREPARED_EXECUTION_NOT_AUTHORIZED', 'schema': 'homya_local',
            'lot_key': LOT, 'execution_authorized': False, 'sql_executed': False, 'plan_sha256': digest(PLAN),
            'artifacts': {p: digest(ROOT/p) for p in ARTIFACTS}, 'tools': {p: digest(p) for p in (MYSQL, DUMP, PHP, PYTHON)},
            'max_seconds': MAX_SECONDS, 'initial_counts': INITIAL_COUNTS, 'final_counts': FINAL_COUNTS,
            'backup_dump_command': dump_command(), 'backup_cap_bytes': DUMP_CAP, 'reserve_bytes': RESERVE_BYTES,
            'import_calls': 1, 'grants': [], 'migrations': [], 'activated': False}


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--execute', action='store_true'); p.add_argument('--validate-receipt', action='store_true')
    p.add_argument('--validate-helper', action='store_true'); p.add_argument('--receipt')
    p.add_argument('--run-dir'); p.add_argument('--mode'); p.add_argument('--wrapper-pid', type=int)
    a = p.parse_args()
    try:
        require(sum((a.execute, a.validate_receipt, a.validate_helper)) <= 1, 'ARGUMENT_SCOPE')
        if a.validate_helper:
            require(a.receipt and a.run_dir and a.mode and a.wrapper_pid, 'HELPER_ARGUMENTS')
            helper_ticket(Path(a.receipt), Path(a.run_dir), a.mode, a.wrapper_pid)
            print(json.dumps({'status': 'HELPER_TICKET_CONSUMED_NO_SQL'}))
        else:
            require(a.run_dir is None and a.mode is None and a.wrapper_pid is None, 'OFFLINE_HELPER_ARGUMENTS_FORBIDDEN')
            if a.execute:
                require(a.receipt is not None, 'RECEIPT_REQUIRED')
                print(json.dumps({'status': 'OP09_EXECUTED_NOT_ACTIVATED', 'run': str(execute(Path(a.receipt)))}))
            elif a.validate_receipt:
                require(a.receipt is not None, 'RECEIPT_REQUIRED'); validate_receipt(Path(a.receipt))
                print(json.dumps({'status': 'RECEIPT_VALID_NO_SQL'}))
            else:
                require(a.receipt is None, 'OFFLINE_ACCESS_FORBIDDEN'); print(json.dumps(prepare(), indent=2))
        return 0
    except Exception as error:
        code = str(error) if isinstance(error, RuntimeError) and re.fullmatch('[A-Z0-9_]+', str(error)) else 'DETAILS_WITHHELD'
        print(json.dumps({'status': 'STOP_INSPECT_NO_RETRY', 'code': code, 'cleanup': False})); return 1


if __name__ == '__main__':
    raise SystemExit(main())
