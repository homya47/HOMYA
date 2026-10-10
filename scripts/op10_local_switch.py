"""Source-only local switch. No SQL, service, migration or secret output.

Default is inert. --apply uses the recorded DECISION-045; --rollback --run
changes back only if the complete current config still matches this operation.
"""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import tempfile
import uuid
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
REGISTRY = Path('/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md')
QA = ROOT / 'output/qa-import'
KEY = b'HOMYA_CATALOGUE_SOURCE'
PATTERN = re.compile(rb'(?m)^HOMYA_CATALOGUE_SOURCE[ \t]*=([^\r\n]*)(\r?\n|$)')


def digest(data):
    return hashlib.sha256(data).hexdigest()


def replace_source(data, expected, target):
    matches = list(PATTERN.finditer(data))
    # Reject ambiguous/exported assignments instead of silently adding a key.
    if len(matches) > 1 or re.search(rb'(?m)^\s*(?:export\s+)?HOMYA_CATALOGUE_SOURCE\b', PATTERN.sub(b'', data)):
        raise RuntimeError('SOURCE_AMBIGUOUS')
    if not matches:
        if expected != 'files':
            raise RuntimeError('SOURCE_MISSING')
        separator = b'' if not data or data.endswith(b'\n') else b'\n'
        return data + separator + KEY + b'=' + target.encode() + b'\n'
    match = matches[0]
    if match[1].strip() not in [expected.encode(), b'"'+expected.encode()+b'"', b"'"+expected.encode()+b"'"]:
        raise RuntimeError('SOURCE_UNEXPECTED')
    return data[:match.start(1)] + target.encode() + data[match.end(1):]


def require_private(path, directory=False):
    for part in [path, *path.parents]:
        if part.is_symlink():
            raise RuntimeError('PATH_SYMLINK')
    s = path.lstat()
    if s.st_uid != os.getuid() or stat.S_IMODE(s.st_mode) != (0o700 if directory else 0o600):
        raise RuntimeError('PRIVATE_MODE')
    if (not directory and (not stat.S_ISREG(s.st_mode) or s.st_nlink != 1)) or (directory and not stat.S_ISDIR(s.st_mode)):
        raise RuntimeError('PRIVATE_TYPE')
    acl = subprocess.run(['/bin/ls', '-lde', str(path)], capture_output=True, check=True).stdout
    if re.search(rb'^\s*\d+:', acl, re.M) or b'+' in acl.split(maxsplit=1)[0]:
        raise RuntimeError('PRIVATE_ACL')


def atomic_change(path, before, after):
    require_private(path)
    witness = path.stat()
    if path.read_bytes() != before:
        raise RuntimeError('CONFIG_CHANGED')
    fd, name = tempfile.mkstemp(prefix='.homya-source-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as out:
            out.write(after)
            out.flush()
            os.fsync(out.fileno())
        require_private(Path(name))
        if path.stat() != witness or path.read_bytes() != before:
            raise RuntimeError('CONFIG_CHANGED')
        os.replace(name, path)
        require_private(path)
        if path.read_bytes() != after:
            raise RuntimeError('CONFIG_VERIFY_FAILED')
    finally:
        if os.path.exists(name):
            os.unlink(name)  # This operation's uncommitted private temporary file only.


def save(path, value):
    with path.open('x') as out:
        json.dump(value, out, indent=2)
        out.write('\n')
    path.chmod(0o600)


def apply():
    if (ROOT / 'bootstrap/cache/config.php').exists() or (ROOT / 'public/hot').exists():
        raise RuntimeError('CACHE_OR_HOT_CONFLICT')
    env = ROOT / '.env'
    require_private(env)
    before = env.read_bytes()
    after = replace_source(before, 'files', 'sql')
    run = QA / ('op10-local-switch-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + str(uuid.uuid4()))
    run.mkdir(mode=0o700)
    require_private(run, True)
    # .env filename remains ignored by Git even inside the private operation folder.
    backup = run / '.env.before'
    with backup.open('xb') as out:
        out.write(before)
    backup.chmod(0o600)
    require_private(backup)
    if backup.read_bytes() != before:
        raise RuntimeError('BACKUP_VERIFY_FAILED')
    meta = {'decision': 'DECISION-045', 'operation': 'OP-10-LOCAL-SWITCH',
            'before_sha256': digest(before), 'after_sha256': digest(after),
            'registry_sha256': digest(REGISTRY.read_bytes()), 'sql_writes': False,
            'only_source_changed': after == replace_source(before, 'files', 'sql')}
    save(run / 'prepared.json', meta)
    atomic_change(env, before, after)
    save(run / 'applied.json', dict(meta, source='sql', http_verified=False))
    print(json.dumps({'status': 'SOURCE_SQL_APPLIED_HTTP_PENDING', 'run': str(run), 'backup_verified': True, 'sql_executed': False}))


def rollback(run):
    if run.parent != QA or not run.name.startswith('op10-local-switch-'):
        raise RuntimeError('RUN_INVALID')
    require_private(run, True)
    meta_path, backup = run / 'prepared.json', run / '.env.before'
    require_private(meta_path)
    require_private(backup)
    meta = json.loads(meta_path.read_text())
    before, current = backup.read_bytes(), (ROOT / '.env').read_bytes()
    if meta['decision'] != 'DECISION-045' or digest(before) != meta['before_sha256'] or digest(current) != meta['after_sha256']:
        raise RuntimeError('ROLLBACK_WITNESS_CHANGED')
    if current != replace_source(before, 'files', 'sql'):
        raise RuntimeError('ROLLBACK_SCOPE_INVALID')
    atomic_change(ROOT / '.env', current, before)
    save(run / 'rollback.json', {'status': 'SOURCE_FILES_RESTORED', 'only_source_changed': True})
    print(json.dumps({'status': 'SOURCE_FILES_RESTORED', 'sql_executed': False}))


def main(argv=None):
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--rollback', action='store_true')
    parser.add_argument('--run', type=Path)
    args = parser.parse_args(argv)
    if not args.apply and not args.rollback:
        print('{"status":"PREPARED_NO_MUTATION_NO_SQL"}')
        return 0
    try:
        os.umask(0o077)
        text = REGISTRY.read_text()
        section = text.split('#### DECISION-045', 1)[1].split('\n#### ', 1)[0]
        if 'VALIDÉ' not in section or 'bascule locale OP-10 vers SQL' not in section or '| Clôture' in section:
            raise RuntimeError('DECISION_NOT_ACTIVE')
        if args.apply == args.rollback or (args.rollback and args.run is None) or (args.apply and args.run is not None):
            raise RuntimeError('MODE_INVALID')
        # Same common operation lock as schema/import/archive tools.
        fd = os.open(QA / '.op08.lock', os.O_RDWR | os.O_NOFOLLOW)
        with os.fdopen(fd, 'r+') as lock:
            require_private(QA / '.op08.lock')
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            rollback(args.run) if args.rollback else apply()
        return 0
    except Exception:
        print('{"status":"STOP_INSPECT_NO_RETRY","secret_output":false}')
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
