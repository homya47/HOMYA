"""OP-07. Default is offline. Execute requires a future, separately recorded human agreement.

No import-time connection, no reuse, no cleanup/retry, no dump execution as root.
"""
import argparse
import datetime
import fcntl
import hashlib
import io
import json
import os
from pathlib import Path
import re
import secrets
import stat
import subprocess
import time
import uuid

import op05_xampp_admin as base
import op06_xampp_admin as recipe
from op07_dump import TABLES, PACKET, require, validate_dump

ROOT, QA = base.ROOT, base.QA
SOURCE, TARGET, USER = 'homya_test', 'homya_restore_op07', 'homya_op07_restore'
MYSQL = '/Applications/XAMPP/xamppfiles/bin/mysql'
DUMP = '/Applications/XAMPP/xamppfiles/bin/mysqldump'
PHP = '/opt/homebrew/bin/php'
PLAN = ROOT / 'docs/OP-07-SAUVEGARDE-RESTAURATION-PREPARATION.md'
REGISTRY = Path('/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md')
AGREEMENT = QA / 'op07-execution-enregistrement-accord.json'  # Must not be issued by this tool.
BACKUPS = ROOT / 'storage/app/private/database-backups/op07'
LOT = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809'
FINAL = QA / 'op06-run-20261008T121403Z-a43af607-46fa-4eb1-8356-91138466d6c3/final-admin.json'
EXPECTED_COUNTS = {'archive_lots': 5, 'archive_categories': 67, 'archive_products': 3711,
                   'archive_translations': 23890, 'migrations': 4}
MAX_SECONDS, RESERVE_BYTES = 1800, 256 * 1024 * 1024
FLAGS = ('--single-transaction', '--quick', '--skip-lock-tables', '--skip-add-locks',
         '--skip-add-drop-table', '--skip-disable-keys', '--skip-extended-insert', '--complete-insert',
         '--hex-blob', '--skip-triggers', '--skip-events', '--skip-routines', '--no-tablespaces',
         '--default-character-set=utf8mb4', '--max-allowed-packet=16777216',
         '--skip-comments', '--skip-dump-date', '--tz-utc')
ARTIFACTS = ('scripts/op07_xampp_backup.py', 'scripts/op07_dump.py', 'scripts/op07_snapshot.php',
             'scripts/test_op07_dump.py', 'scripts/test_op07_xampp_backup.py',
             'tests/Unit/Op07SnapshotTest.php', 'scripts/op05_xampp_admin.py', 'scripts/op05_resume.py',
             'scripts/op06_xampp_admin.py', 'tests/Support/Op06Recipe.php', 'tests/Support/CatalogueArchiveFixture.php',
             'app/Services/CatalogueArchivePayload.php', 'app/Services/CatalogueCapsule.php',
             'app/Services/StrictJson.php', 'app/Services/PrivateLocalFiles.php', 'app/Services/ArchiveDatabaseGuard.php',
             'config/homya.php', 'config/database.php', 'config/catalogue.php', 'composer.lock', '.gitignore',
             'output/qa-import/op06-reprise-synthese-verification.json',
             'output/qa-import/op05-reprise-controle-independant.json', str(FINAL.relative_to(ROOT)),
             'output/qa-import/op07-outils-enregistrement-accord.json', 'output/qa-import/op07-plan-valide-v01.md',
             'output/qa-import/op07-preparation-inspection.json', 'output/qa-import/op07-options-client-controlees.json',
             'output/qa-import/op07-php-runtime-controle.json',
             'storage/app/private/catalogue-archives/'+LOT+'/manifest.json')
GRANTS = tuple("GRANT SELECT, INSERT, CREATE" + (", REFERENCES" if t in ('archive_lots', 'archive_categories') else '') +
               " ON `" + TARGET + "`.`" + t + "` TO '" + USER + "'@'localhost'" for t in TABLES)
RIGHTS = {(t, p) for t in TABLES for p in ('SELECT', 'INSERT', 'CREATE')} | {
    ('archive_lots', 'REFERENCES'), ('archive_categories', 'REFERENCES')}
READ_RIGHTS = {(t, 'SELECT') for t in TABLES}


def digest(path):
    h = hashlib.sha256()
    with Path(path).open('rb') as f:
        for chunk in iter(lambda: f.read(1048576), b''):
            h.update(chunk)
    return h.hexdigest()


def stamp(meta):
    return tuple(getattr(meta, k) for k in ('st_dev', 'st_ino', 'st_mode', 'st_uid', 'st_gid',
                                           'st_nlink', 'st_size', 'st_mtime_ns', 'st_ctime_ns'))


def safe_json(path, parent, maximum=262144):
    path = Path(path)
    require(path.is_absolute() and path.parent == parent and path.resolve() == path, 'PRIVATE_PATH')
    meta = path.lstat()
    require(stat.S_ISREG(meta.st_mode) and stat.S_IMODE(meta.st_mode) == 0o600 and meta.st_uid == os.getuid()
            and meta.st_size <= maximum and meta.st_nlink == 1, 'PRIVATE_FILE')
    with os.fdopen(os.open(path, os.O_RDONLY | os.O_NOFOLLOW), 'r') as f:
        require(stamp(os.fstat(f.fileno())) == stamp(meta), 'PRIVATE_CHANGED')
        data = json.load(f, object_pairs_hook=base.unique_object)
    require(stamp(path.lstat()) == stamp(meta), 'PRIVATE_CHANGED')
    return data


def validate_receipt(path):
    receipt = safe_json(path, QA)
    fields = {'operation', 'source', 'target', 'user', 'decision', 'execution_authorized',
              'agreement_sha256', 'plan_sha256', 'artifacts', 'tools'}
    require(set(receipt) == fields, 'RECEIPT_FIELDS')
    require(receipt['operation'] == 'OP-07' and receipt['source'] == SOURCE and receipt['target'] == TARGET
            and receipt['user'] == USER and receipt['execution_authorized'] is True
            and re.fullmatch(r'DECISION-[0-9]{3,}', receipt['decision']) and receipt['decision'] != 'DECISION-034', 'RECEIPT_SCOPE')
    require(digest(PLAN) == receipt['plan_sha256'], 'PLAN_CHANGED')
    require(set(receipt['artifacts']) == set(ARTIFACTS), 'ARTIFACT_LIST')
    for name, expected in receipt['artifacts'].items():
        p = ROOT / name
        require(p.resolve() == p and digest(p) == expected, 'ARTIFACT_CHANGED')
    require(set(receipt['tools']) == {MYSQL, DUMP, PHP}, 'TOOL_LIST')
    for name, expected in receipt['tools'].items():
        require(digest(name) == expected, 'TOOL_CHANGED')
    agreement = safe_json(AGREEMENT, QA)
    require(digest(AGREEMENT) == receipt['agreement_sha256'] and agreement.get('operation') == 'OP-07'
            and agreement.get('decision') == receipt['decision'] and agreement.get('sql_authorized') is True
            and agreement.get('execution_authorized') is True and agreement.get('registry_readback_verified') is True
            and digest(REGISTRY) == agreement.get('registry_after_sha256'), 'EXECUTION_AGREEMENT_REQUIRED')
    entry = REGISTRY.read_text().split('#### '+receipt['decision']+'\n')
    require(len(entry) == 2 and re.search(r'\| Projet / périmètre \| HOMYA[^\n]*OP-07', entry[1].split('#### ')[0])
            and 'VALIDÉ' in entry[1].split('#### ')[0], 'REGISTRY_SCOPE')
    return receipt


def environment():
    return {k: v for k, v in os.environ.items() if not k.startswith(('MYSQL', 'MARIADB'))}


def admin_sql(query, receipt):
    validate_receipt(receipt)
    cmd = [MYSQL, '--no-defaults', '--protocol=SOCKET', '--socket='+base.SOCKET,
           '--user=root', '--skip-password', '--batch', '--raw', '--skip-column-names', '--connect-timeout=5']
    result = subprocess.run(cmd, input=query+';\n', env=environment(), capture_output=True, text=True, timeout=20)
    if result.returncode:
        match = re.search(r'ERROR\s+(\d+)', result.stderr)
        raise RuntimeError('XAMPP_SQL_ERROR_'+(match[1] if match else 'WITHHELD'))
    return result.stdout


def event(phase):
    print(json.dumps({'operation': 'OP-07', 'phase': phase}), flush=True)


def save(path, value):
    require(path.resolve() == path and not path.exists(), 'OUTPUT_COLLISION')
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as f:
        json.dump(value, f, ensure_ascii=False, indent=2); f.write('\n'); f.flush(); os.fsync(f.fileno())


def source_audit(receipt):
    # Existing auditors remain unchanged; every native query is routed through the OP-07 receipt guard.
    original = base.sql
    try:
        base.sql = lambda q: admin_sql(q, receipt)
        actual = recipe.inspect(9, empty=False)
    finally:
        base.sql = original
    frozen = json.loads(FINAL.read_text())
    require(actual['counts'] == frozen['counts'] and actual['physical_ddl'] == frozen['physical_ddl'], 'SOURCE_DRIFT')
    server = actual['metadata']['server']
    require(server.get('general_log') == 0 and server.get('slow_log') == 0 and server.get('log_bin') == 0, 'SERVER_LOGGING_CHANGED')
    return actual


def collisions(receipt):
    a = admin_sql("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='"+TARGET+"'", receipt)
    b = admin_sql("SELECT COUNT(*) FROM mysql.user WHERE User='"+USER+"'", receipt)
    require(a.strip() == '0' and b.strip() == '0', 'TARGET_OR_ACCOUNT_COLLISION')


def account_rights(receipt):
    grantee = "CONCAT(CHAR(39),'"+USER+"',CHAR(39),'@',CHAR(39),'localhost',CHAR(39))"
    lines = admin_sql("SELECT JSON_OBJECT('schema',TABLE_SCHEMA,'table',TABLE_NAME,'right',PRIVILEGE_TYPE,'grantable',IS_GRANTABLE) FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE="+grantee+" ORDER BY TABLE_SCHEMA,TABLE_NAME,PRIVILEGE_TYPE", receipt)
    result = set()
    for line in lines.splitlines():
        row = json.loads(line)
        require(row['schema'] == TARGET and row['grantable'] == 'NO' and (row['table'], row['right']) in RIGHTS, 'RESTORE_RIGHTS_DRIFT')
        result.add((row['table'], row['right']))
    # All namespaces, not just intended database/table, for this one new account. Never read auth hashes.
    checks = ["SELECT COUNT(*) FROM mysql.user WHERE User='"+USER+"' AND Host!='localhost'",
              "SELECT COUNT(*) FROM information_schema.USER_PRIVILEGES WHERE GRANTEE="+grantee+" AND (PRIVILEGE_TYPE!='USAGE' OR IS_GRANTABLE='YES')",
              "SELECT COUNT(*) FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE="+grantee,
              "SELECT COUNT(*) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE="+grantee,
              "SELECT COUNT(*) FROM mysql.procs_priv WHERE User='"+USER+"'",
              "SELECT COUNT(*) FROM mysql.roles_mapping WHERE User='"+USER+"' OR Role='"+USER+"'",
              "SELECT COUNT(*) FROM mysql.proxies_priv WHERE User='"+USER+"' OR Proxied_user='"+USER+"'",
              "SELECT COUNT(*) FROM mysql.user WHERE User='"+USER+"' AND (is_role!='N' OR default_role!='')"]
    require(all(admin_sql(q, receipt).strip() == '0' for q in checks), 'RESTORE_EXTRA_RIGHTS')
    return result


def reduce_rights(receipt):
    actual = account_rights(receipt)
    for table in TABLES:
        rights = sorted(p for t, p in actual - READ_RIGHTS if t == table)
        if rights:
            admin_sql('REVOKE '+', '.join(rights)+' ON `'+TARGET+'`.`'+table+"` FROM '"+USER+"'@'localhost'", receipt)
    remaining = account_rights(receipt)
    require(remaining <= READ_RIGHTS, 'RESTORE_REDUCTION_FAILED')
    return sorted(remaining)


def private_directory(path):
    require(path.resolve() == path, 'BACKUP_PATH')
    if not path.exists():
        path.mkdir(mode=0o700)
    meta = path.lstat()
    require(stat.S_ISDIR(meta.st_mode) and stat.S_IMODE(meta.st_mode) == 0o700 and meta.st_uid == os.getuid(), 'BACKUP_DIRECTORY')


def make_run():
    private_directory(BACKUPS.parent)
    private_directory(BACKUPS)
    run = BACKUPS / (datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+str(uuid.uuid4()))
    require(not run.exists(), 'RUN_COLLISION')
    private_directory(run)
    return run


def snapshot(receipt, run, role, deadline=None):
    validate_receipt(receipt)
    cmd = [PHP, '-d', 'memory_limit=512M', str(ROOT/'scripts/op07_snapshot.php'),
           '--receipt='+str(receipt), '--run-dir='+str(run), '--role='+role]
    budget = 300 if deadline is None else min(300, deadline-time.monotonic())
    require(budget > 0, 'OPERATION_DEADLINE')
    result = subprocess.run(cmd, env=environment(), capture_output=True, text=True, timeout=budget)
    require(result.returncode == 0 and not result.stderr, 'SNAPSHOT_FAILED_DETAILS_WITHHELD')
    data = json.loads(result.stdout, object_pairs_hook=base.unique_object)
    require(set(data.get('tables', {})) == set(TABLES), 'SNAPSHOT_RESULT')
    require({t: v['rows'] for t, v in data['tables'].items()} == EXPECTED_COUNTS, 'SNAPSHOT_COUNTS')
    return data


def comparable_snapshot(data):
    return {k: v for k, v in data.items() if k != 'peak_php_bytes'}


def runtime_witnesses():
    paths = []
    for name in ('app', 'config', 'routes', 'resources', 'lang', 'database'):
        paths.extend(p for p in (ROOT/name).rglob('*') if p.is_file())
    paths += [ROOT/'.env', Path('/Applications/XAMPP/xamppfiles/etc/my.cnf')]
    require(all(p.resolve() == p for p in paths), 'RUNTIME_PATH')
    return {str(p): digest(p) for p in sorted(paths)}


def freeze_references(run, receipt):
    approved = validate_receipt(receipt)['artifacts']
    require(set(approved) == set(ARTIFACTS), 'ARTIFACT_LIST')
    parent = run/'reference-files'
    private_directory(parent)
    for name, expected in approved.items():
        current = parent
        for part in Path(name).parts[:-1]:
            current /= part
            private_directory(current)
        target = parent/name
        fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
        with (ROOT/name).open('rb') as source, os.fdopen(fd, 'wb') as out:
            for chunk in iter(lambda: source.read(1048576), b''):
                out.write(chunk)
            out.flush(); os.fsync(out.fileno())
        require(digest(target) == expected, 'REFERENCE_CHANGED')
        os.chmod(target, 0o400)
    require(validate_receipt(receipt)['artifacts'] == approved, 'REFERENCE_CHANGED')
    return approved


def verify_media(deadline=None):
    capsule = ROOT / ('storage/app/private/catalogue-archives/'+LOT)
    manifest = capsule/'manifest.json'
    require(digest(manifest) == LOT, 'CAPSULE_MANIFEST_CHANGED')
    data = json.loads(manifest.read_text(), object_pairs_hook=base.unique_object)
    require(set(data) == {'format_version', 'files'} and data['format_version'] == 1, 'CAPSULE_FORMAT')
    count, size, seen = 0, 0, set()
    for item in data['files']:
        require(deadline is None or time.monotonic() < deadline, 'OPERATION_DEADLINE')
        require(set(item) == {'path', 'bytes', 'sha256'} and isinstance(item['path'], str)
                and re.fullmatch(r'[a-f0-9]{64}', item['sha256'])
                and isinstance(item['bytes'], int) and item['bytes'] >= 0, 'CAPSULE_ENTRY')
        rel = Path(item['path'])
        require(not rel.is_absolute() and '\\' not in item['path'] and '\0' not in item['path']
                and all(p not in ('.', '..') for p in item['path'].split('/')) and item['path'] not in seen, 'CAPSULE_PATH')
        seen.add(item['path']); p = capsule/'files'/rel
        require(p.resolve() == p and p.is_file(), 'CAPSULE_FILE_CHANGED')
        meta = p.lstat()
        require(meta.st_size == item['bytes'] and stat.S_IMODE(meta.st_mode) == 0o400
                and meta.st_uid == os.getuid() and meta.st_nlink == 1, 'CAPSULE_FILE_CHANGED')
        hasher = hashlib.sha256()
        with os.fdopen(os.open(p, os.O_RDONLY | os.O_NOFOLLOW), 'rb') as f:
            require(stamp(os.fstat(f.fileno())) == stamp(meta), 'CAPSULE_FILE_CHANGED')
            for chunk in iter(lambda: f.read(1048576), b''):
                hasher.update(chunk)
            require(stamp(os.fstat(f.fileno())) == stamp(meta), 'CAPSULE_FILE_CHANGED')
        require(hasher.hexdigest() == item['sha256'] and stamp(p.lstat()) == stamp(meta), 'CAPSULE_FILE_CHANGED')
        count += 1; size += item['bytes']
    require(count == 22239 and size == 3477829369, 'CAPSULE_COUNTS')
    return {'files': count, 'bytes': size, 'manifest_sha256': LOT, 'media_included': False,
            'required_capsule': str(capsule.relative_to(ROOT)), 'all_files_verified': True}


def dump_command():
    return [DUMP, '--no-defaults', '--protocol=SOCKET', '--socket='+base.SOCKET, '--user=root',
            '--skip-password', *FLAGS, SOURCE, *TABLES]


def tool_context():
    inspections = json.loads((QA/'op07-preparation-inspection.json').read_text())['tools']
    for item in inspections.values():
        require(digest(item['path']) == item['binary_sha256'], 'INSPECTED_CLIENT_CHANGED')
    php = json.loads((QA/'op07-php-runtime-controle.json').read_text())
    require(digest(PHP) == php['php_binary_sha256'], 'INSPECTED_PHP_CHANGED')
    return {'client_versions': {k: v['version'] for k, v in inspections.items()}, 'php_version': php['php']}


def native_dump(path, receipt, cap, deadline):
    validate_receipt(receipt)
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600), 'wb') as out:
        process = subprocess.Popen(dump_command(), env=environment(), stdout=out, stderr=subprocess.PIPE)
        try:
            while process.poll() is None:
                require(time.monotonic() < deadline and path.stat().st_size <= cap, 'DUMP_LIMIT')
                time.sleep(0.1)
            _stdout, errors = process.communicate(timeout=5)
            require(process.returncode == 0 and not errors and 0 < path.stat().st_size <= cap, 'DUMP_FAILED_DETAILS_WITHHELD')
            out.flush(); os.fsync(out.fileno())
        finally:
            if process.poll() is None:
                process.kill(); process.communicate(timeout=5)


def native_restore(run, dump_path, receipt, expected_hash, deadline):
    validate_receipt(receipt)
    require(not dump_path.is_symlink(), 'RESTORE_DUMP_CHANGED')
    meta = dump_path.lstat()
    require(stat.S_ISREG(meta.st_mode) and meta.st_uid == os.getuid() and stat.S_IMODE(meta.st_mode) == 0o400
            and meta.st_nlink == 1 and digest(dump_path) == expected_hash, 'RESTORE_DUMP_CHANGED')
    # --defaults-file excludes global/user option files; --defaults-extra-file would still inherit them.
    access = run/'restore-client.cnf'
    require(access.resolve() == access and stat.S_IMODE(access.stat().st_mode) == 0o600
            and access.stat().st_uid == os.getuid() and access.stat().st_nlink == 1, 'RESTORE_ACCESS_FILE')
    secret = safe_json(run/'restore-access.json', run)
    require(set(secret) == {'database', 'user', 'password'} and secret['database'] == TARGET and secret['user'] == USER
            and re.fullmatch(r'[a-f0-9]{64}', secret['password']), 'RESTORE_ACCESS_SCOPE')
    require(access.read_text() == '[client]\npassword='+secret['password']+'\n', 'RESTORE_ACCESS_CHANGED')
    access_stamp = stamp(access.stat())
    cmd = [MYSQL, '--defaults-file='+str(access), '--protocol=SOCKET',
           '--socket='+base.SOCKET, '--user='+USER, '--database='+TARGET, '--default-character-set=utf8mb4',
           '--max-allowed-packet=16777216', '--batch', '--binary-mode', '--local-infile=0', '--connect-timeout=5']
    with os.fdopen(os.open(dump_path, os.O_RDONLY | os.O_NOFOLLOW), 'rb') as inp:
        require(stamp(os.fstat(inp.fileno())) == stamp(meta), 'RESTORE_DUMP_CHANGED')
        result = subprocess.run(cmd, stdin=inp, env=environment(), capture_output=True, timeout=max(1, deadline-time.monotonic()))
        require(result.returncode == 0 and not result.stderr and not result.stdout, 'RESTORE_FAILED_DETAILS_WITHHELD')
        require(stamp(os.fstat(inp.fileno())) == stamp(meta) and stamp(dump_path.lstat()) == stamp(meta)
                and stamp(access.stat()) == access_stamp, 'RESTORE_DUMP_CHANGED')


def target_ddl(receipt):
    objects = admin_sql("SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+TARGET+"' ORDER BY TABLE_NAME", receipt)
    wanted = '\n'.join(t+'\tBASE TABLE\tInnoDB\tutf8mb4_bin' for t in sorted(TABLES))
    require(objects.strip() == wanted, 'RESTORED_OBJECTS')
    for query in ["SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='"+TARGET+"'",
                  "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='"+TARGET+"'",
                  "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='"+TARGET+"'"]:
        require(admin_sql(query, receipt).strip() == '0', 'RESTORED_EXTRA_OBJECTS')
    actual = {t: admin_sql('SHOW CREATE TABLE `'+TARGET+'`.`'+t+'`', receipt).strip() for t in TABLES}
    require(actual == json.loads(FINAL.read_text())['physical_ddl'], 'RESTORED_DDL_CHANGED')
    return actual


def execute(receipt):
    receipt = Path(receipt)
    validate_receipt(receipt)  # No SQL or directory creation before the independent agreement guard.
    tools_context = tool_context()
    run, created_user = None, False
    deadline = time.monotonic() + MAX_SECONDS
    # OP-07 process lock only. Other source writers must stay idle; this is not an importer DB lock.
    lock = QA/'op07-execution.lock'
    fd = os.open(lock, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, 'w') as handle:
        meta = os.fstat(handle.fileno())
        require(stat.S_ISREG(meta.st_mode) and stat.S_IMODE(meta.st_mode) == 0o600
                and meta.st_uid == os.getuid() and meta.st_nlink == 1, 'LOCK_FILE')
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        try:
            event('preflight')
            source = source_audit(receipt); collisions(receipt)
            run = make_run()
            save(run/'receipt.json', json.loads(receipt.read_text()))
            references = freeze_references(run, receipt)
            runtime = runtime_witnesses(); save(run/'runtime-before.json', runtime)
            before = snapshot(receipt, run, 'source', deadline=deadline); save(run/'source-before.json', before)
            rows = sum(v['rows'] for v in before['tables'].values())
            cap = 2*before['raw_value_bytes'] + rows*8192 + 2*sum(len(s.encode()) for s in source['physical_ddl'].values()) + PACKET
            # Conservative operation allowance, not an estimate of production resources or an RTO.
            require(os.statvfs(BACKUPS).f_bavail*os.statvfs(BACKUPS).f_frsize >= 3*cap + RESERVE_BYTES, 'BACKUP_SPACE')
            save(run/'capacity.json', {'dump_cap_bytes': cap, 'required_free_bytes': 3*cap+RESERVE_BYTES,
                                      'reserve_bytes': RESERVE_BYTES, 'deadline_seconds': MAX_SECONDS})
            event('dump')
            partial = run/'catalogue.sql.partial'
            native_dump(partial, receipt, cap, deadline)
            with partial.open(encoding='utf8', newline='') as stream:
                checked = validate_dump(stream, source['physical_ddl'], EXPECTED_COUNTS)
            after = snapshot(receipt, run, 'source', deadline=deadline); save(run/'source-after-dump.json', after)
            require(comparable_snapshot(before) == comparable_snapshot(after), 'SOURCE_ROWS_CHANGED')
            require(source_audit(receipt)['physical_ddl'] == source['physical_ddl'], 'SOURCE_DDL_CHANGED')
            dump_path = run/'catalogue.sql'
            require(not dump_path.exists(), 'DUMP_COLLISION')
            os.link(partial, dump_path); partial.unlink(); os.chmod(dump_path, 0o400)
            media = verify_media(deadline); save(run/'media.json', media)
            manifest = {'operation': 'OP-07', 'source': SOURCE, 'target': TARGET, 'dump_sha256': digest(dump_path),
                        'dump_bytes': dump_path.stat().st_size, 'dump_validation': checked, 'capsule': media,
                        'media_included': False, 'source_snapshot': comparable_snapshot(before),
                        'physical_ddl': source['physical_ddl'], 'tools': validate_receipt(receipt)['tools'],
                        **tools_context, 'flags': FLAGS}
            manifest['reference_files'] = references
            save(run/'manifest.json', manifest)
            require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
            event('create-isolated-target')
            source_audit(receipt)  # Logs/settings rechecked before piping a newly generated credential.
            collisions(receipt)
            admin_sql('CREATE DATABASE `'+TARGET+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin', receipt)
            password = secrets.token_hex(32)
            save(run/'restore-access.json', {'database': TARGET, 'user': USER, 'password': password})
            fd = os.open(run/'restore-client.cnf', os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with os.fdopen(fd, 'w') as f:
                f.write('[client]\npassword='+password+'\n'); f.flush(); os.fsync(f.fileno())
            # Secret only in stdin pipe; server logs verified OFF; never command line/proofs.
            admin_sql("CREATE USER '"+USER+"'@'localhost' IDENTIFIED BY '"+password+"'", receipt)
            created_user = True
            for grant in GRANTS:
                admin_sql(grant, receipt)
            require(account_rights(receipt) == RIGHTS, 'RESTORE_RIGHTS_INCOMPLETE')
            event('restore')
            native_restore(run, dump_path, receipt, manifest['dump_sha256'], deadline)
            target_ddl(receipt)
            restored = snapshot(receipt, run, 'restored', deadline=deadline); save(run/'restored.json', restored)
            require(comparable_snapshot(before) == comparable_snapshot(restored), 'RESTORED_ROWS_CHANGED')
            save(run/'rights-final.json', {'rights': reduce_rights(receipt)})
            require(account_rights(receipt) == READ_RIGHTS, 'RESTORE_READER_RIGHTS')
            final = snapshot(receipt, run, 'source', deadline=deadline); save(run/'source-final.json', final)
            require(comparable_snapshot(before) == comparable_snapshot(final), 'SOURCE_CHANGED_AFTER_RESTORE')
            source_audit(receipt)
            require(verify_media(deadline) == media and digest(dump_path) == manifest['dump_sha256'], 'BACKUP_CHANGED')
            require(runtime_witnesses() == runtime, 'RUNTIME_CHANGED')
            save(run/'runtime-after.json', runtime)
            validate_receipt(receipt)
            require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
            save(run/'status.json', {'status': 'RESTORE_VERIFIED_TEST_ONLY_NOT_ACTIVATED',
                                     'restoration_tested': True, 'independent_disk_backup': False,
                                     'activated': False, 'duration_seconds': MAX_SECONDS-(deadline-time.monotonic())})
            event('verified-no-activation')
            return run
        except Exception as error:
            reduced = False
            if created_user:
                try:
                    reduce_rights(receipt); reduced = True
                except Exception:
                    pass
            if run is not None:
                code = str(error) if isinstance(error, RuntimeError) and re.fullmatch(r'[A-Z0-9_]+', str(error)) else 'ERROR_DETAILS_WITHHELD'
                save(run/'failure.json', {'status': 'INCOMPLETE_INSPECT_NO_RETRY', 'code': code,
                                         'rights_reduction_verified': reduced, 'source_restore': False, 'cleanup': False})
            raise


def prepare():
    """Offline proposal only. Never issues an executable authorization receipt."""
    for name in ARTIFACTS:
        require((ROOT/name).resolve() == ROOT/name, 'ARTIFACT_PATH')
    return {'operation': 'OP-07', 'status': 'TOOLS_PREPARED_EXECUTION_NOT_AUTHORIZED',
            'source': SOURCE, 'target': TARGET, 'user': USER, 'execution_authorized': False,
            'sql_executed': False, 'plan_sha256': digest(PLAN), 'artifacts': {n: digest(ROOT/n) for n in ARTIFACTS},
            **tool_context(),
            'tools': {p: digest(p) for p in (MYSQL, DUMP, PHP)}, 'dump_command': dump_command(),
            'grants': GRANTS, 'rights_initial': sorted(RIGHTS), 'rights_final': sorted(READ_RIGHTS),
            'restoration_command': [MYSQL, '--defaults-file=<private-run>/restore-client.cnf',
                                    '--protocol=SOCKET', '--socket='+base.SOCKET, '--user='+USER,
                                    '--database='+TARGET, '--default-character-set=utf8mb4',
                                    '--max-allowed-packet=16777216', '--batch', '--binary-mode', '--local-infile=0', '--connect-timeout=5'],
            'future_authorization_required': ['SQL preflight/export', 'one schema/account', '17 table privileges',
                                              'five CREATE TABLE/INSERT from checked dump', '12 rights revoked', 'full verification'],
            'max_seconds': MAX_SECONDS, 'reserve_bytes': RESERVE_BYTES, 'application_changed': False}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--execute', action='store_true')
    parser.add_argument('--receipt')
    parser.add_argument('--check-receipt')
    args = parser.parse_args()
    try:
        if args.check_receipt:
            require(not args.execute and not args.receipt, 'ARGUMENT_SCOPE')
            validate_receipt(args.check_receipt)
            print(json.dumps({'status': 'RECEIPT_VALID'}))
        elif args.execute:
            require(args.receipt is not None, 'EXECUTION_RECEIPT_REQUIRED')
            print(json.dumps({'run_dir': str(execute(args.receipt))}))
        else:
            require(args.receipt is None, 'ARGUMENT_SCOPE')
            print(json.dumps(prepare(), ensure_ascii=False, indent=2))
        return 0
    except Exception as error:
        code = str(error) if isinstance(error, RuntimeError) and re.fullmatch(r'[A-Z0-9_]+', str(error)) else 'ERROR_DETAILS_WITHHELD'
        print(json.dumps({'status': 'STOP_NO_RETRY', 'code': code}))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
