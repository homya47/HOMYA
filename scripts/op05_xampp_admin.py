"""OP-05: metadata inspection; one journal GRANT only after separate approval.

No password is accepted or emitted. Native XAMPP client, explicit local socket,
no inherited MYSQL options. Importing this module never connects or writes.
"""
import argparse
import datetime
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess

ROOT = Path(__file__).resolve().parents[1]
QA = ROOT / 'output/qa-import'
SOCKET = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
SCHEMAS = ('homya_test', 'homya_local')
USERS = tuple(s + '_' + role for s in SCHEMAS for role in ('reader', 'import', 'schema'))
TABLES = ('archive_lots', 'archive_categories', 'archive_products', 'archive_translations')
MIGRATIONS = tuple(f'2026_10_08_00000{i}_create_{table}' for i, table in enumerate(TABLES, 1))
GRANT = "GRANT SELECT, INSERT ON `homya_test`.`migrations` TO 'homya_test_schema'@'localhost'"
ARTIFACTS = ('scripts/op05_xampp_admin.py', 'scripts/op05_journal.php',
             'output/qa-import/op05-journal-propose.sql',
             'output/qa-import/op04-complement-schema-final.json',
             'output/qa-import/op04-complement-schema-final.sql')


def require(condition, code):
    if not condition:
        raise RuntimeError(code)


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, 'DUPLICATE_JSON_KEY')
        result[key] = value
    return result


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def validate_receipt(path):
    path = Path(path)
    require(path.is_absolute() and path.parent == QA and not path.is_symlink(), 'RECEIPT_PATH')
    metadata = path.stat()
    require(stat.S_IMODE(metadata.st_mode) == 0o600 and metadata.st_uid == os.getuid(), 'RECEIPT_MODE')
    receipt = json.loads(path.read_text(), object_pairs_hook=unique_object)
    require(set(receipt) == {'operation', 'schema', 'account', 'decision', 'execution_authorized',
                            'plan_sha256', 'artifacts'}, 'RECEIPT_FIELDS')
    require(receipt['operation'] == 'OP-05' and receipt['schema'] == 'homya_test'
            and receipt['account'] == 'homya_test_schema@localhost'
            and receipt['execution_authorized'] is True
            and re.fullmatch(r'DECISION-[0-9]{3,}', receipt['decision']) is not None, 'RECEIPT_SCOPE')
    require(receipt['plan_sha256'] == digest(ROOT / 'docs/OP-05-SCHEMA-TEST-PREPARATION.md'), 'PLAN_CHANGED')
    require(set(receipt['artifacts']) == set(ARTIFACTS), 'ARTIFACT_LIST')
    for name in ARTIFACTS:
        require(not (ROOT / name).is_symlink() and receipt['artifacts'][name] == digest(ROOT / name), 'ARTIFACT_CHANGED')
    # The human approval must be recorded/read back separately before this file
    # is issued. A receipt is an execution guard, not a source of authorization.
    return receipt


def sql(query):
    environment = {k: v for k, v in os.environ.items() if not k.startswith(('MYSQL', 'MARIADB'))}
    result = subprocess.run(['/Applications/XAMPP/xamppfiles/bin/mysql', '--no-defaults',
                             '--protocol=SOCKET', '--socket=' + SOCKET, '--user=root', '--skip-password',
                             '--batch', '--raw', '--skip-column-names', '--connect-timeout=5', '--execute=' + query],
                            env=environment, capture_output=True, text=True, timeout=20)
    if result.returncode:
        match = re.search(r'ERROR\s+(\d+)', result.stderr)
        raise RuntimeError('XAMPP_SQL_ERROR_' + (match.group(1) if match else 'UNCLASSIFIED'))
    return result.stdout


def inspect():
    names = ','.join("'" + name + "'" for name in USERS)
    grantees = ','.join("CONCAT(CHAR(39),'" + u + "',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))" for u in USERS)
    queries = [
        "SELECT JSON_OBJECT('version',VERSION(),'identity',CURRENT_USER(),'port',@@port,'socket',@@socket,"
        "'datadir',@@datadir,'bind',@@bind_address,'packet_global',@@global.max_allowed_packet,"
        "'packet_session',@@session.max_allowed_packet,'foreign_keys',CAST(@@foreign_key_checks AS UNSIGNED),"
        "'checks',CAST(@@check_constraint_checks AS UNSIGNED),'events',@@event_scheduler,'sql_mode',@@session.sql_mode,"
        "'connection_id',CONNECTION_ID(),'general_log',CAST(@@global.general_log AS UNSIGNED),'slow_log',CAST(@@global.slow_query_log AS UNSIGNED),'log_bin',CAST(@@global.log_bin AS UNSIGNED))",
        f"SELECT JSON_OBJECT('user',User,'host',Host,'plugin',plugin,'is_role',is_role,'default_role',default_role) FROM mysql.user WHERE User IN ({names}) ORDER BY User,Host",
        f"SELECT JSON_OBJECT('global', (SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE IN ({grantees}) AND PRIVILEGE_TYPE<>'USAGE'),"
        f"'global_delegation',(SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE IN ({grantees}) AND IS_GRANTABLE='YES'),"
        f"'schema',(SELECT COUNT(*) FROM mysql.db WHERE User IN ({names})),'column',(SELECT COUNT(*) FROM mysql.columns_priv WHERE User IN ({names})),"
        f"'routine',(SELECT COUNT(*) FROM mysql.procs_priv WHERE User IN ({names})),'roles',(SELECT COUNT(*) FROM mysql.roles_mapping WHERE User IN ({names}) OR Role IN ({names})),"
        f"'proxy',(SELECT COUNT(*) FROM mysql.proxies_priv WHERE User IN ({names}) OR Proxied_user IN ({names})))",
        f"SELECT JSON_OBJECT('user',User,'host',Host,'schema',Db,'table',Table_name,'privileges',Table_priv,'column',Column_priv) FROM mysql.tables_priv WHERE User IN ({names}) ORDER BY User,Db,Table_name",
        f"SELECT JSON_OBJECT('grantee',GRANTEE,'schema',TABLE_SCHEMA,'table',TABLE_NAME,'privilege',PRIVILEGE_TYPE,'grantable',IS_GRANTABLE) FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE IN ({grantees}) ORDER BY GRANTEE,TABLE_SCHEMA,TABLE_NAME,PRIVILEGE_TYPE",
        "SELECT JSON_OBJECT('schema',SCHEMA_NAME,'charset',DEFAULT_CHARACTER_SET_NAME,'collation',DEFAULT_COLLATION_NAME) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('homya_test','homya_local') ORDER BY SCHEMA_NAME",
        "SELECT JSON_OBJECT('schema',TABLE_SCHEMA,'table',TABLE_NAME,'type',TABLE_TYPE,'engine',ENGINE,'collation',TABLE_COLLATION) FROM information_schema.TABLES WHERE TABLE_SCHEMA IN ('homya_test','homya_local') ORDER BY TABLE_SCHEMA,TABLE_NAME",
        "SELECT JSON_OBJECT('routines',(SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA IN ('homya_test','homya_local')),'events',(SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA IN ('homya_test','homya_local')),'triggers',(SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA IN ('homya_test','homya_local')))",
        "SELECT JSON_OBJECT('engine',ENGINE,'support',SUPPORT,'transactions',TRANSACTIONS,'savepoints',SAVEPOINTS) FROM information_schema.ENGINES WHERE ENGINE='InnoDB'",
        "SELECT JSON_OBJECT('collation',COLLATION_NAME,'charset',CHARACTER_SET_NAME) FROM information_schema.COLLATIONS WHERE COLLATION_NAME IN ('utf8mb4_bin','ascii_bin') ORDER BY COLLATION_NAME",
    ]
    # Each SELECT is bounded to its own result group; no raw grants/auth tables.
    keys = ('server', 'accounts', 'unexpected_rights', 'table_records', 'privileges', 'schemas', 'tables', 'other_objects', 'engines', 'collations')
    data = {key: [json.loads(line) for line in sql(query).splitlines()] for key, query in zip(keys, queries)}
    for key in ('server', 'unexpected_rights', 'other_objects'):
        require(len(data[key]) == 1, 'METADATA_SHAPE')
        data[key] = data[key][0]
    return data


def validate(data, phase):
    require(phase in ('empty', 'journal', 'granted', 'final'), 'PHASE_INVALID')
    expected_server = {'version': '10.4.28-MariaDB', 'identity': 'root@localhost', 'port': 3307,
                       'socket': SOCKET, 'datadir': '/Applications/XAMPP/xamppfiles/var/mysql/',
                       'bind': '127.0.0.1', 'packet_global': 16777216, 'packet_session': 16777216,
                       'foreign_keys': 1, 'checks': 1, 'events': 'OFF'}
    require(all(data['server'].get(k) == v for k, v in expected_server.items()), 'XAMPP_IDENTITY_CAPACITY')
    require({'STRICT_TRANS_TABLES', 'NO_ENGINE_SUBSTITUTION', 'NO_AUTO_CREATE_USER'} <= set(data['server']['sql_mode'].split(',')), 'STRICT_MODE')
    expected_accounts = sorted([{'user': u, 'host': 'localhost', 'plugin': 'mysql_native_password',
                                 'is_role': 'N', 'default_role': ''} for u in USERS], key=lambda a: a['user'])
    require(data['accounts'] == expected_accounts, 'ACCOUNTS_DRIFT')
    require(all(v == 0 for v in data['unexpected_rights'].values()), 'RIGHTS_OUT_OF_SCOPE')
    expected_privileges = []
    for schema in SCHEMAS:
        for table in (*TABLES, 'migrations'):
            for privilege in ('ALTER', 'CREATE'):
                expected_privileges.append({'grantee': f"'{schema}_schema'@'localhost'", 'schema': schema,
                                            'table': table, 'privilege': privilege, 'grantable': 'NO'})
    if phase in ('granted', 'final'):
        for privilege in ('INSERT', 'SELECT'):
            expected_privileges.append({'grantee': "'homya_test_schema'@'localhost'", 'schema': 'homya_test',
                                        'table': 'migrations', 'privilege': privilege, 'grantable': 'NO'})
    order = lambda p: (p['grantee'], p['schema'], p['table'], p['privilege'])
    require(data['privileges'] == sorted(expected_privileges, key=order), 'TABLE_RIGHTS_DRIFT')
    expected_records = []
    for schema in SCHEMAS:
        for table in (*TABLES, 'migrations'):
            privileges = {'Create', 'Alter'}
            if schema == 'homya_test' and table == 'migrations' and phase in ('granted', 'final'):
                privileges |= {'Select', 'Insert'}
            expected_records.append((schema + '_schema', 'localhost', schema, table, frozenset(privileges), ''))
    actual = [(p['user'], p['host'], p['schema'], p['table'], frozenset(p['privileges'].split(',')), p['column']) for p in data['table_records']]
    require(set(actual) == set(expected_records) and len(actual) == 10, 'PHYSICAL_RIGHTS_DRIFT')
    require(data['schemas'] == [{'schema': s, 'charset': 'utf8mb4', 'collation': 'utf8mb4_bin'} for s in sorted(SCHEMAS)], 'SCHEMAS_DRIFT')
    expected_tables = [] if phase == 'empty' else (['migrations'] if phase != 'final' else sorted((*TABLES, 'migrations')))
    require(data['tables'] == [{'schema': 'homya_test', 'table': t, 'type': 'BASE TABLE', 'engine': 'InnoDB',
                               'collation': 'utf8mb4_bin'} for t in expected_tables], 'OBJECTS_DRIFT')
    require(all(v == 0 for v in data['other_objects'].values()), 'OTHER_OBJECTS_DRIFT')
    require(len(data['engines']) == 1 and data['engines'][0]['support'] in ('YES', 'DEFAULT')
            and data['engines'][0]['transactions'] == 'YES' and data['engines'][0]['savepoints'] == 'YES', 'INNODB_UNAVAILABLE')
    require(data['collations'] == [{'collation': 'ascii_bin', 'charset': 'ascii'}, {'collation': 'utf8mb4_bin', 'charset': 'utf8mb4'}], 'COLLATIONS_UNAVAILABLE')


def journal_check(phase):
    columns = [json.loads(line) for line in sql("SELECT JSON_OBJECT('name',COLUMN_NAME,'type',COLUMN_TYPE,'nullable',IS_NULLABLE,'extra',EXTRA,'collation',COLLATION_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='homya_test' AND TABLE_NAME='migrations' ORDER BY ORDINAL_POSITION").splitlines()]
    require(columns == [{'name': 'id', 'type': 'int(10) unsigned', 'nullable': 'NO', 'extra': 'auto_increment', 'collation': None},
                        {'name': 'migration', 'type': 'varchar(255)', 'nullable': 'NO', 'extra': '', 'collation': 'utf8mb4_bin'},
                        {'name': 'batch', 'type': 'int(11)', 'nullable': 'NO', 'extra': '', 'collation': None}], 'JOURNAL_COLUMNS')
    keys = [json.loads(line) for line in sql("SELECT JSON_OBJECT('name',CONSTRAINT_NAME,'type',CONSTRAINT_TYPE) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA='homya_test' AND TABLE_NAME='migrations'").splitlines()]
    require(keys == [{'name': 'PRIMARY', 'type': 'PRIMARY KEY'}], 'JOURNAL_KEYS')
    indexes = sql("SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='homya_test' AND TABLE_NAME='migrations' ORDER BY INDEX_NAME,SEQ_IN_INDEX").strip()
    require(indexes == 'PRIMARY\t0\tid\t1', 'JOURNAL_INDEX')
    rows = [json.loads(line) for line in sql("SELECT JSON_OBJECT('id',id,'migration',migration,'batch',batch) FROM homya_test.migrations ORDER BY id").splitlines()]
    require(rows == ([] if phase != 'final' else [{'id': i, 'migration': m, 'batch': 1} for i, m in enumerate(MIGRATIONS, 1)]), 'JOURNAL_ROWS')
    return {'columns': columns, 'constraints': keys, 'rows': rows}


def checked_inspection(phase):
    data = inspect()
    validate(data, phase)
    if phase != 'empty':
        data['journal'] = journal_check(phase)
    if phase == 'final':
        data['archive_counts'] = {t: int(sql('SELECT COUNT(*) FROM homya_test.' + t).strip()) for t in TABLES}
        require(all(v == 0 for v in data['archive_counts'].values()), 'ARCHIVE_NOT_EMPTY')
        data['physical_ddl'] = {t: sql('SHOW CREATE TABLE homya_test.' + t).strip() for t in TABLES}
        # Full physical constraints/columns must also be reviewed independently
        # against the frozen DDL. A zero-row/object check alone is insufficient.
    return data


def grant_journal(receipt_path, runner=sql, checker=checked_inspection):
    validate_receipt(receipt_path)
    before = checker('journal')
    validate_receipt(receipt_path)
    runner(GRANT)
    after = checker('granted')
    return {'before': before, 'after': after, 'write_sql': [GRANT]}


def migration_process(arguments):
    # Keep the TTY: Laravel demands an explicit interactive confirmation.
    result = subprocess.run(arguments, cwd=ROOT, timeout=120)
    require(result.returncode == 0, 'MIGRATION_FAILED_INSPECT_PARTIAL_STATE')


def run_migrations(receipt_path, access, runner=migration_process, checker=checked_inspection):
    validate_receipt(receipt_path)
    require(isinstance(access, str) and access.startswith(str(ROOT / 'storage/app/private/database-access') + '/')
            and Path(access).name == '.env.homya_test_schema', 'MIGRATION_ACCESS_SCOPE')
    before = checker('granted')
    frozen = json.loads((QA / 'op04-complement-schema-final.json').read_text())
    validate_receipt(receipt_path)
    runner(['/opt/homebrew/bin/php', str(ROOT / 'artisan'), 'homya:catalogue-schema', '--execute',
            '--operation=OP-05', '--connection=homya_test_schema', '--access=' + access,
            '--expected-ddl=' + frozen['sha256'], '--expected-files=' + frozen['files_sha256']])
    after = checker('final')
    return {'before': before, 'after': after, 'physical_ddl_review_pending': True}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--phase', choices=('empty', 'journal', 'granted', 'final'), default='empty')
    parser.add_argument('--grant-journal', action='store_true')
    parser.add_argument('--run-migrations', action='store_true')
    parser.add_argument('--access')
    parser.add_argument('--receipt')
    parser.add_argument('--validate-receipt', action='store_true')
    parser.add_argument('--report')
    args = parser.parse_args()
    try:
        if args.validate_receipt:
            require(args.receipt and not args.report and not args.grant_journal and not args.run_migrations
                    and not args.access and args.phase == 'empty', 'RECEIPT_MODE_SCOPE')
            validate_receipt(args.receipt)
            print(json.dumps({'status': 'RECEIPT_VALIDATED_NO_SQL'}))
            return 0
        require(args.report is not None, 'REPORT_REQUIRED')
        report = Path(args.report)
        require(report.is_absolute() and report.parent == QA and not report.exists() and not report.is_symlink(), 'REPORT_PATH')
        require(not (args.grant_journal and args.run_migrations), 'OPERATION_CONFLICT')
        require(not args.receipt or args.grant_journal or args.run_migrations, 'UNUSED_RECEIPT')
        require(not args.access or args.run_migrations, 'UNUSED_ACCESS')
        if args.grant_journal or args.run_migrations:
            require(args.receipt and args.phase == ('journal' if args.grant_journal else 'granted'), 'WRITE_SCOPE')
            lock_path = QA / '.op05.lock'
            require(not lock_path.is_symlink(), 'LOCK_PATH')
            descriptor = os.open(lock_path, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
            with os.fdopen(descriptor, 'r+') as lock:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                result = grant_journal(args.receipt) if args.grant_journal else run_migrations(args.receipt, args.access)
        else:
            result = checked_inspection(args.phase)
        proof = {'operation': 'OP-05', 'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                 'phase': args.phase, 'status': 'CHECKS_PASSED', 'method': 'XAMPP native client, explicit socket, bounded metadata SELECT; exact journal GRANT only if separately authorized',
                 'grant_executed': args.grant_journal, 'migration_executed': args.run_migrations,
                 'import_service_change': False, 'result': result}
        with report.open('x') as f:
            json.dump(proof, f, indent=2, ensure_ascii=False)
            f.write('\n')
        print(json.dumps({'status': proof['status'], 'phase': args.phase, 'grant_executed': args.grant_journal,
                          'migration_executed': args.run_migrations}))
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
        code = str(error) if isinstance(error, RuntimeError) else 'IO_OR_FORMAT_ERROR'
        print(json.dumps({'status': 'STOP_INSPECT_STATE_NO_AUTO_RETRY', 'code': code,
                          'grant_requested': args.grant_journal, 'migration_requested': args.run_migrations,
                          'automatic_cleanup': False}))
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
