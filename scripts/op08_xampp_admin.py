"""OP-08. Offline by default; no SQL, credentials or receipt issuance on import/prepare.

One explicitly authorized local schema execution, no retry/cleanup/import/activation.
Historical tools and migrations are reused as definitions, never retargeted.
"""
import argparse
import copy
import datetime
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import time
import uuid

import op05_xampp_admin as base
import op07_xampp_backup as prior

ROOT, QA = base.ROOT, base.QA
PLAN = ROOT/'docs/OP-08-SCHEMA-LOCAL-PREPARATION.md'
AGREEMENT = QA/'op08-execution-enregistrement-accord.json'
REFERENCE = QA/'op07-reprise-controle-independant.json'
SOURCE_RUN = ROOT/'storage/app/private/database-backups/op07/20261008T135251Z-936e0ffa-9ca0-4b24-88a2-9614b4fb7bad'
ACCESS_DIR = ROOT/'storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307'
TABLES = (*base.TABLES, 'migrations')
MYSQL, PHP = prior.MYSQL, prior.PHP
MAX_SECONDS = 1800
COLUMNS = ('load_state', 'verification_document', 'verified_at')
SPECS = (('schema', 'migrations', ('SELECT', 'INSERT'), ()),
         ('schema', 'archive_lots', ('REFERENCES',), ()),
         ('schema', 'archive_categories', ('REFERENCES',), ())) + tuple(
    ('reader', t, ('SELECT',), ()) for t in base.TABLES) + tuple(
    ('import', t, ('SELECT', 'INSERT'), ()) for t in base.TABLES) + (
    ('import', 'archive_lots', ('UPDATE',), COLUMNS),)
GRANTS = tuple('GRANT '+(', '.join(rights) if not columns else 'UPDATE ('+', '.join(columns)+')')+
               ' ON `homya_local`.`'+table+"` TO 'homya_local_"+role+"'@'localhost'"
               for role, table, rights, columns in SPECS)
ARTIFACTS = ('scripts/op08_xampp_admin.py', 'scripts/op08_journal.php', 'scripts/test_op08_xampp_admin.py',
             'tests/Feature/Op08SchemaPreparationTest.php', 'scripts/op05_xampp_admin.py',
             'scripts/op07_xampp_backup.py', 'scripts/op07_dump.py',
             'app/Services/CatalogueArchiveSchema.php', 'app/Services/ArchiveDatabaseGuard.php',
             'app/Services/PrivateLocalFiles.php', 'app/Services/StrictJson.php',
             'app/Console/Commands/PrepareCatalogueSchema.php', 'app/Console/Commands/CheckLocalDatabase.php',
             'config/database.php', 'config/homya.php', 'config/catalogue.php', 'composer.lock',
             'vendor/laravel/framework/src/Illuminate/Database/Migrations/Migrator.php',
             'vendor/laravel/framework/src/Illuminate/Database/Migrations/DatabaseMigrationRepository.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connection.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connectors/MySqlConnector.php',
             'vendor/laravel/framework/src/Illuminate/Database/Connectors/MariaDbConnector.php',
             'output/qa-import/op05-journal-propose.sql', 'output/qa-import/op04-complement-schema-final.json',
             'output/qa-import/op04-complement-schema-final.sql', 'output/qa-import/op07-reprise-controle-independant.json',
             'output/qa-import/op07-reprise-synthese-verification.json', 'output/qa-import/op07-reprise-cloture-registre.json',
             'output/qa-import/op08-outils-enregistrement-accord.json',
             'storage/app/private/catalogue-archives/'+prior.LOT+'/manifest.json',
             str((SOURCE_RUN/'source-before.json').relative_to(ROOT))) + tuple(
    'database/migrations/catalogue/'+n+'.php' for n in base.MIGRATIONS)
require, digest, save = prior.require, prior.digest, prior.save


def validate_receipt(path):
    r = prior.safe_json(path, QA)
    require(set(r) == {'operation', 'schema', 'decision', 'execution_authorized', 'agreement_sha256',
                      'plan_sha256', 'artifacts', 'tools'}, 'RECEIPT_FIELDS')
    require(r['operation'] == 'OP-08' and r['schema'] == 'homya_local' and r['execution_authorized'] is True
            and re.fullmatch(r'DECISION-[0-9]{3,}', r['decision']) and r['decision'] != 'DECISION-037', 'RECEIPT_SCOPE')
    require(digest(PLAN) == r['plan_sha256'] and set(r['artifacts']) == set(ARTIFACTS), 'PLAN_OR_ARTIFACT_LIST')
    for rel, h in r['artifacts'].items():
        p = ROOT/rel
        require(p.resolve() == p and digest(p) == h, 'ARTIFACT_CHANGED')
    require(set(r['tools']) == {MYSQL, PHP}, 'TOOL_LIST')
    require(all(digest(p) == h for p, h in r['tools'].items()), 'TOOL_CHANGED')
    a = prior.safe_json(AGREEMENT, QA)
    require(digest(AGREEMENT) == r['agreement_sha256'] and a.get('operation') == 'OP-08'
            and a.get('decision') == r['decision'] and a.get('sql_authorized') is True
            and a.get('execution_authorized') is True and a.get('registry_readback_verified') is True
            and digest(prior.REGISTRY) == a.get('registry_after_sha256'), 'EXECUTION_AGREEMENT_REQUIRED')
    entries = prior.REGISTRY.read_text().split('#### '+r['decision']+'\n')
    require(len(entries) == 2 and re.search(r'\| Projet / périmètre \| HOMYA[^\n]*OP-08', entries[1].split('#### ')[0])
            and 'VALIDÉ' in entries[1].split('#### ')[0], 'REGISTRY_SCOPE')
    return r


def sql(query, receipt):
    validate_receipt(receipt)
    result = subprocess.run([MYSQL, '--no-defaults', '--protocol=SOCKET', '--socket='+base.SOCKET,
                             '--user=root', '--skip-password', '--batch', '--raw', '--skip-column-names', '--connect-timeout=5'],
                            input=query+';\n', env=prior.environment(), capture_output=True, text=True, timeout=20)
    if result.returncode:
        m = re.search(r'ERROR\s+(\d+)', result.stderr)
        raise RuntimeError('XAMPP_SQL_ERROR_'+(m[1] if m else 'WITHHELD'))
    require(not result.stderr, 'XAMPP_SQL_STDERR_WITHHELD')
    return result.stdout


def reference():
    return json.loads(REFERENCE.read_text(), object_pairs_hook=base.unique_object)


def expected_metadata(stage, phase):
    require(stage in range(13) and phase in ('empty', 'journal', 'final'), 'STAGE_SCOPE')
    require((phase == 'empty' and stage == 0) or (phase == 'journal' and stage in range(4))
            or (phase == 'final' and stage in range(3, 13)), 'PHASE_STAGE_SCOPE')
    data = copy.deepcopy(reference()['source_audit']['metadata'])
    for role, table, rights, columns in SPECS[:stage]:
        user = 'homya_local_'+role
        if columns:
            for c in columns:
                data['column_privileges'].append(dict(grantee="'"+user+"'@'localhost'", schema='homya_local',
                                                     table=table, column=c, privilege='UPDATE', grantable='NO'))
                data['column_records'].append(dict(user=user, host='localhost', schema='homya_local', table=table,
                                                  column=c, privileges='Update'))
            data['unexpected_rights']['column'] += len(columns)
        else:
            for p in rights:
                data['privileges'].append(dict(grantee="'"+user+"'@'localhost'", schema='homya_local',
                                               table=table, privilege=p, grantable='NO'))
    records = {}
    for p in data['privileges']:
        key = (p['grantee'][1:].split("'")[0], p['schema'], p['table'])
        records.setdefault(key, set()).add(p['privilege'].title())
    data['table_records'] = [dict(user=u, host='localhost', schema=s, table=t,
                                 privileges=','.join(sorted(rights)),
                                 column='Update' if u == 'homya_test_import' and t == 'archive_lots'
                                 or stage == 12 and u == 'homya_local_import' and t == 'archive_lots' else '')
                             for (u, s, t), rights in sorted(records.items())]
    local = [] if phase == 'empty' else (['migrations'] if phase == 'journal' else TABLES)
    data['tables'] += [dict(schema='homya_local', table=t, type='BASE TABLE', engine='InnoDB', collation='utf8mb4_bin') for t in local]
    data['tables'].sort(key=lambda p: (p['schema'], p['table']))
    return data


def validate_metadata(data, stage, phase):
    expected = expected_metadata(stage, phase)
    require(set(data) == set(expected), 'METADATA_FIELDS')
    require({k: v for k, v in data['server'].items() if k != 'connection_id'} ==
            {k: v for k, v in expected['server'].items() if k != 'connection_id'}, 'SERVER_DRIFT')
    for key in ('accounts', 'unexpected_rights', 'schemas', 'tables', 'other_objects', 'engines', 'collations'):
        require(data[key] == expected[key], 'METADATA_'+key.upper())
    for key in ('privileges', 'column_privileges', 'column_records'):
        require(sorted(data[key], key=lambda x: json.dumps(x, sort_keys=True)) ==
                sorted(expected[key], key=lambda x: json.dumps(x, sort_keys=True)), 'RIGHTS_'+key.upper())
    normalize = lambda row: (row['user'], row['host'], row['schema'], row['table'],
                             tuple(sorted(row['privileges'].split(','))), row['column'])
    require(sorted(map(normalize, data['table_records'])) == sorted(map(normalize, expected['table_records'])), 'PHYSICAL_RIGHTS')


def inspect(stage, phase, receipt):
    original = base.sql
    try:
        base.sql = lambda q: sql(q, receipt)
        data = base.inspect()
    finally:
        base.sql = original
    users = ','.join("'"+u+"'" for u in base.USERS)
    grantees = ','.join("CONCAT(CHAR(39),'"+u+"',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))" for u in base.USERS)
    data['column_privileges'] = [json.loads(x) for x in sql("SELECT JSON_OBJECT('grantee',GRANTEE,'schema',TABLE_SCHEMA,'table',TABLE_NAME,'column',COLUMN_NAME,'privilege',PRIVILEGE_TYPE,'grantable',IS_GRANTABLE) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE IN ("+grantees+") ORDER BY GRANTEE,TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME", receipt).splitlines()]
    data['column_records'] = [json.loads(x) for x in sql("SELECT JSON_OBJECT('user',User,'host',Host,'schema',Db,'table',Table_name,'column',Column_name,'privileges',Column_priv) FROM mysql.columns_priv WHERE User IN ("+users+") ORDER BY User,Db,Table_name,Column_name", receipt).splitlines()]
    validate_metadata(data, stage, phase)
    ref = reference()['physical_ddl']
    require({t: sql('SHOW CREATE TABLE `homya_test`.`'+t+'`', receipt).strip() for t in TABLES} == ref, 'TEST_DDL_CHANGED')
    require({t: sql('SHOW CREATE TABLE `homya_restore_op07`.`'+t+'`', receipt).strip() for t in TABLES} == ref, 'RESTORE_DDL_CHANGED')
    if phase != 'empty':
        wanted = {t: ref[t] for t in (('migrations',) if phase == 'journal' else TABLES)}
        if phase == 'journal':
            wanted['migrations'] = wanted['migrations'].replace(' AUTO_INCREMENT=5', '')
        require({t: sql('SHOW CREATE TABLE `homya_local`.`'+t+'`', receipt).strip() for t in wanted} == wanted, 'LOCAL_DDL_CHANGED')
        rows = [json.loads(x) for x in sql("SELECT JSON_OBJECT('id',id,'migration',migration,'batch',batch) FROM homya_local.migrations ORDER BY id", receipt).splitlines()]
        require(rows == ([] if phase == 'journal' else [{'id': i, 'migration': m, 'batch': 1} for i, m in enumerate(base.MIGRATIONS, 1)]), 'LOCAL_JOURNAL')
        if phase == 'final':
            require(all(sql('SELECT COUNT(*) FROM `homya_local`.`'+t+'`', receipt).strip() == '0' for t in base.TABLES), 'LOCAL_DATA_NOT_EMPTY')
            require(sql("SELECT UNIQUE_CONSTRAINT_SCHEMA,DELETE_RULE,UPDATE_RULE,COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='homya_local' GROUP BY UNIQUE_CONSTRAINT_SCHEMA,DELETE_RULE,UPDATE_RULE", receipt).strip() == 'homya_local\tRESTRICT\tRESTRICT\t4', 'LOCAL_FK_TARGET')
    restore_rights(receipt)
    return data


def restore_rights(receipt):
    u = prior.USER
    g = "CONCAT(CHAR(39),'"+u+"',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))"
    require(sql("SELECT User,Host,plugin,is_role,default_role FROM mysql.user WHERE User='"+u+"'", receipt).rstrip('\n') == u+'\tlocalhost\tmysql_native_password\tN\t', 'RESTORE_ACCOUNT_CHANGED')
    wanted = '\n'.join('homya_restore_op07\t'+t+'\tSELECT\tNO' for t in sorted(TABLES))
    require(sql('SELECT TABLE_SCHEMA,TABLE_NAME,PRIVILEGE_TYPE,IS_GRANTABLE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE='+g+' ORDER BY TABLE_SCHEMA,TABLE_NAME,PRIVILEGE_TYPE', receipt).strip() == wanted, 'RESTORE_RIGHTS_CHANGED')
    for q in ('SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE='+g+" AND (PRIVILEGE_TYPE!='USAGE' OR IS_GRANTABLE='YES')",
              'SELECT COUNT(*) FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE='+g,
              'SELECT COUNT(*) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE='+g,
              "SELECT COUNT(*) FROM mysql.procs_priv WHERE User='"+u+"'",
              "SELECT COUNT(*) FROM mysql.roles_mapping WHERE User='"+u+"' OR Role='"+u+"'",
              "SELECT COUNT(*) FROM mysql.proxies_priv WHERE User='"+u+"' OR Proxied_user='"+u+"'"):
        require(sql(q, receipt).strip() == '0', 'RESTORE_EXTRA_RIGHTS')
    require(sql("SELECT DEFAULT_CHARACTER_SET_NAME,DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='homya_restore_op07'", receipt).strip() == 'utf8mb4\tutf8mb4_bin', 'RESTORE_SCHEMA_CHANGED')
    wanted = '\n'.join(t+'\tBASE TABLE\tInnoDB\tutf8mb4_bin' for t in sorted(TABLES))
    require(sql("SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='homya_restore_op07' ORDER BY TABLE_NAME", receipt).strip() == wanted, 'RESTORE_OBJECTS_CHANGED')
    for table, column in (('ROUTINES', 'ROUTINE_SCHEMA'), ('TRIGGERS', 'TRIGGER_SCHEMA'), ('EVENTS', 'EVENT_SCHEMA')):
        require(sql("SELECT COUNT(*) FROM information_schema."+table+" WHERE "+column+"='homya_restore_op07'", receipt).strip() == '0', 'RESTORE_EXTRA_OBJECTS')


def preservation(receipt):
    """Hash exact typed server bytes per PK row; output only hashes, never row values."""
    frozen = json.loads((SOURCE_RUN/'source-before.json').read_text())['tables']
    result = {}
    for schema in ('homya_test', 'homya_restore_op07'):
        result[schema] = {}
        for t in TABLES:
            columns = [p['name'] for p in frozen[t]['columns']]
            keys = frozen[t]['primary_key']
            require(all(re.fullmatch('[a-z_]+', c) for c in columns+keys), 'SNAPSHOT_COLUMN')
            framed = ["CASE WHEN `"+c+"` IS NULL THEN 'N;' ELSE CONCAT('B',OCTET_LENGTH(CAST(`"+c+"` AS BINARY)),':',CAST(`"+c+"` AS BINARY),';') END" for c in columns]
            hashes = sql('SELECT SHA2(CONCAT('+','.join(framed)+'),256) FROM `'+schema+'`.`'+t+'` ORDER BY '+','.join('`'+k+'`' for k in keys), receipt).splitlines()
            require(len(hashes) == prior.EXPECTED_COUNTS[t] and all(re.fullmatch('[a-f0-9]{64}', h) for h in hashes), 'PROTECTED_ROWS')
            result[schema][t] = {'rows': len(hashes), 'sha256': hashlib.sha256('\n'.join(hashes).encode('ascii')).hexdigest()}
    require(result['homya_test'] == result['homya_restore_op07'], 'PROTECTED_COPY_DRIFT')
    return result


def access_files():
    root = ROOT/'storage/app/private/database-access'
    for directory in (root, ACCESS_DIR):
        meta = directory.lstat()
        require(directory.resolve() == directory and stat.S_ISDIR(meta.st_mode) and stat.S_IMODE(meta.st_mode) == 0o700 and meta.st_uid == os.getuid(), 'ACCESS_DIRECTORY')
    result = {}
    for role in ('schema', 'reader', 'import'):
        p = ACCESS_DIR/('.env.homya_local_'+role)
        m = p.lstat()
        require(p.resolve() == p and stat.S_ISREG(m.st_mode) and stat.S_IMODE(m.st_mode) == 0o600
                and m.st_uid == os.getuid() and m.st_nlink == 1 and m.st_size <= 4096, 'ACCESS_FILE')
        result[role] = str(p)
    return result


def php_call(args, receipt, deadline, migration=False):
    validate_receipt(receipt)
    budget = min(300, deadline-time.monotonic())
    require(budget > 0, 'OPERATION_DEADLINE')
    result = subprocess.run([PHP, '-d', 'memory_limit=512M', *args], input='yes\n' if migration else '',
                            env=prior.environment(), capture_output=True, text=True, timeout=budget, cwd=ROOT)
    require(result.returncode == 0 and not result.stderr, 'PHP_FAILED_DETAILS_WITHHELD')
    if migration:
        require('CATALOGUE_MIGRATIONS_COMPLETED_NEEDS_INDEPENDENT_REVIEW' in result.stdout, 'MIGRATOR_RESULT')
    else:
        data = json.loads(result.stdout, object_pairs_hook=base.unique_object)
        require(data.get('status') in ('LOCAL_SCHEMA_EMPTY_PROBED', 'LOCAL_JOURNAL_CREATED', 'LOCAL_ROLE_READ_PROBED'), 'PHP_HELPER_RESULT')
    # Do not save raw framework/native errors or outputs with uncertain sensitivity.


def workflow(receipt, run, deadline):
    validate_receipt(receipt)
    runtime = prior.runtime_witnesses()
    require(not (ROOT/'bootstrap/cache/config.php').exists() and not list((ROOT/'database/schema').glob('*')), 'CACHED_CONFIG_OR_SCHEMA_DUMP')
    access = access_files()
    save(run/'before.json', inspect(0, 'empty', receipt))
    preserved = preservation(receipt); save(run/'protected-before.json', preserved)
    media = prior.verify_media(deadline); save(run/'media-before.json', media)
    require(os.statvfs(run).f_bavail*os.statvfs(run).f_frsize >= 256*1024*1024, 'SPACE_RESERVE')
    save(run/'runtime-before.json', runtime)
    helper = str(ROOT/'scripts/op08_journal.php')
    php_call([helper, '--probe', '--empty', '--role=schema', '--receipt='+str(receipt), '--access='+access['schema']], receipt, deadline)
    validate_receipt(receipt); inspect(0, 'empty', receipt)
    php_call([helper, '--create-journal', '--receipt='+str(receipt), '--access='+access['schema']], receipt, deadline)
    save(run/'journal.json', inspect(0, 'journal', receipt))
    for i in range(3):
        inspect(i, 'journal', receipt)
        require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
        sql(GRANTS[i], receipt)
        save(run/('grant-'+str(i+1)+'.json'), inspect(i+1, 'journal', receipt))
    frozen = json.loads((QA/'op04-complement-schema-final.json').read_text())
    validate_receipt(receipt); inspect(3, 'journal', receipt)
    php_call([str(ROOT/'artisan'), 'homya:catalogue-schema', '--execute', '--operation=OP-08',
              '--connection=homya_local_schema', '--access='+access['schema'],
              '--expected-ddl='+frozen['sha256'], '--expected-files='+frozen['files_sha256']], receipt, deadline, migration=True)
    save(run/'migrated.json', inspect(3, 'final', receipt))
    for i in range(3, 12):
        inspect(i, 'final', receipt)
        require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
        sql(GRANTS[i], receipt)
        save(run/('grant-'+str(i+1)+'.json'), inspect(i+1, 'final', receipt))
    for role in ('schema', 'reader', 'import'):
        php_call([helper, '--probe', '--role='+role, '--receipt='+str(receipt), '--access='+access[role]], receipt, deadline)
    save(run/'final.json', inspect(12, 'final', receipt))
    after = preservation(receipt); save(run/'protected-after.json', after)
    require(after == preserved and prior.runtime_witnesses() == runtime and prior.verify_media(deadline) == media, 'PRESERVATION_DRIFT')
    save(run/'runtime-after.json', runtime)
    validate_receipt(receipt); require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
    save(run/'status.json', {'status': 'LOCAL_SCHEMA_VERIFIED_EMPTY_NO_IMPORT_NO_ACTIVATION',
                           'duration_seconds': MAX_SECONDS-(deadline-time.monotonic()),
                           'import_executed': False, 'activated': False})


def execute(receipt):
    receipt = Path(receipt)
    validate_receipt(receipt)
    fd = os.open(QA/'.op08.lock', os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as lock:
        m = os.fstat(lock.fileno())
        require(stat.S_ISREG(m.st_mode) and stat.S_IMODE(m.st_mode) == 0o600 and m.st_uid == os.getuid() and m.st_nlink == 1, 'LOCK_FILE')
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        run = QA/('op08-run-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+str(uuid.uuid4()))
        require(not run.exists() and run.resolve() == run, 'RUN_COLLISION')
        run.mkdir(mode=0o700)
        save(run/'receipt.json', json.loads(receipt.read_text()))
        try:
            workflow(receipt, run, time.monotonic()+MAX_SECONDS)
            return run
        except Exception as error:
            code = str(error) if isinstance(error, RuntimeError) and re.fullmatch('[A-Z0-9_]+', str(error)) else 'DETAILS_WITHHELD'
            save(run/'failure.json', {'status': 'STOP_INSPECT_NO_RETRY', 'code': code,
                                     'partial_schema_or_rights_possible': True, 'cleanup': False, 'retry': False})
            raise


def prepare():
    return {'operation': 'OP-08', 'status': 'TOOLS_PREPARED_EXECUTION_NOT_AUTHORIZED', 'schema': 'homya_local',
            'execution_authorized': False, 'sql_executed': False, 'plan_sha256': digest(PLAN),
            'artifacts': {p: digest(ROOT/p) for p in ARTIFACTS}, 'tools': {p: digest(p) for p in (MYSQL, PHP)},
            'grants': GRANTS, 'access_paths': {r: str(ACCESS_DIR/('.env.homya_local_'+r)) for r in ('schema', 'reader', 'import')},
            'max_seconds': MAX_SECONDS, 'new_tables': TABLES, 'imports': False, 'activated': False}


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--execute', action='store_true'); p.add_argument('--validate-receipt', action='store_true'); p.add_argument('--receipt')
    a = p.parse_args()
    try:
        require(not (a.execute and a.validate_receipt), 'ARGUMENT_SCOPE')
        if a.validate_receipt:
            require(a.receipt is not None, 'RECEIPT_REQUIRED'); validate_receipt(Path(a.receipt))
            print(json.dumps({'status': 'RECEIPT_VALID_NO_SQL'}))
        elif a.execute:
            require(a.receipt is not None, 'RECEIPT_REQUIRED')
            print(json.dumps({'status': 'OP08_EXECUTED_NOT_ACTIVATED', 'run': str(execute(a.receipt))}))
        else:
            require(a.receipt is None, 'OFFLINE_ACCESS_FORBIDDEN')
            print(json.dumps(prepare(), indent=2))
        return 0
    except Exception as error:
        code = str(error) if isinstance(error, RuntimeError) and re.fullmatch('[A-Z0-9_]+', str(error)) else 'DETAILS_WITHHELD'
        print(json.dumps({'status': 'STOP_INSPECT_NO_RETRY', 'code': code, 'cleanup': False}))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
