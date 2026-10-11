"""Install only the reviewed security files into HOMYA's existing local copy.

Default validates the plan. --apply --sha256 requires its exact digest and a
passing full test report. No database, secret values, service or production change.
"""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
PLAN = ROOT / 'output/qa-security/install-plan.json'
FILES = {'bootstrap/app.php', 'app/Http/Middleware/SecurityHeaders.php',
         'app/Http/Middleware/AdminBoundary.php', 'resources/views/components/layout.blade.php',
         'config/session.php', 'config/filesystems.php', 'public/.htaccess', '.env.example',
         'app/Services/ProductionSecurity.php', 'app/Http/Middleware/ProductionGuard.php',
         'app/Console/Commands/SecurityReadinessAudit.php', 'docs/AUDIT-SECURITE-HOMYA.md'}


def require(condition, reason):
    if not condition:
        raise RuntimeError(reason)


def safe(path, root):
    require(path.is_relative_to(root), 'PATH_OUTSIDE_SCOPE')
    require(not root.is_symlink(), 'ROOT_SYMLINK')
    for parent in [path] + list(path.parents):
        if parent == root:
            break
        require(not parent.is_symlink(), 'SYMLINK_REFUSED')


def digest(path):
    require(not path.is_symlink(), 'SYMLINK_REFUSED')
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else None


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--sha256')
    args = parser.parse_args()
    plan = json.loads(PLAN.read_text())
    require(plan['target'] == str(TARGET) and not TARGET.is_symlink(), 'TARGET_INVALID')
    require({i['path'] for i in plan['files']} == FILES and len(plan['files']) == len(FILES), 'FILE_SCOPE_INVALID')
    protected = json.loads((ROOT / 'output/qa-security/protected.json').read_text())
    for name, expected in protected.items():
        require(digest(Path(name)) == expected, 'PROTECTED_FILE_CHANGED')
    for item in plan['files']:
        for root in [ROOT, TARGET]:
            safe(root / item['path'], root)
        require(digest(ROOT / item['path']) == item['source_sha256'], 'SOURCE_CHANGED')
        require(digest(TARGET / item['path']) == item['target_sha256'], 'TARGET_CHANGED')
    private = TARGET / 'storage/app/private'
    safe(private, TARGET)
    require(private.is_dir() and private.stat().st_uid == os.getuid()
            and (private.stat().st_mode & 0o777) == 0o755, 'PRIVATE_DIRECTORY_CHANGED')
    if not args.apply:
        print(json.dumps({'prepared': True, 'files': len(FILES), 'sha256': digest(PLAN)}))
        return
    require(args.sha256 == digest(PLAN), 'PLAN_CHANGED')
    tests = ET.parse(ROOT / 'output/qa-security/junit.xml').getroot()
    suite = tests if tests.tag == 'testsuite' else tests.find('testsuite')
    require(suite is not None and int(suite.attrib['tests']) > 0
            and int(suite.attrib['failures']) == 0 and int(suite.attrib['errors']) == 0, 'TESTS_NOT_PASSED')
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup = TARGET.parent.parent / ('homya-local/security-backup-' + stamp)
    backup.mkdir(mode=0o700)
    shutil.copy2(PLAN, backup / 'plan.json')
    for item in plan['files']:
        if item['target_sha256'] is not None:
            saved = backup / 'files' / item['path']
            saved.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(TARGET / item['path'], saved)
            require(digest(saved) == item['target_sha256'], 'BACKUP_INVALID')
    for item in plan['files']:
        source, target = ROOT / item['path'], TARGET / item['path']
        require(digest(source) == item['source_sha256'] and digest(target) == item['target_sha256'], 'FILE_CHANGED')
        target.parent.mkdir(parents=True, exist_ok=True)
        pending = target.with_name(target.name + '.security-pending')
        safe(pending, TARGET)
        with pending.open('xb') as stream:
            stream.write(source.read_bytes())
            stream.flush()
            os.fsync(stream.fileno())
        pending.chmod(source.stat().st_mode & 0o777)
        require(digest(pending) == item['source_sha256'], 'COPY_INVALID')
        pending.replace(target)
    private.chmod(0o700)
    for item in plan['files']:
        require(digest(TARGET / item['path']) == item['source_sha256'], 'INSTALL_INVALID')
    for name, expected in protected.items():
        require(digest(Path(name)) == expected, 'PROTECTED_FILE_CHANGED')
    result = subprocess.run(['/opt/homebrew/bin/php', str(TARGET / 'artisan'), 'view:clear'],
                            cwd=TARGET, text=True, capture_output=True)
    require(result.returncode == 0, 'VIEW_CACHE_CLEAR_FAILED')
    report = {'installed': True, 'files': len(FILES), 'backup': str(backup),
              'private_directory_mode': '0700', 'protected_unchanged': True,
              'database_changes': False, 'services_changed': False}
    (ROOT / 'output/qa-security/install-result.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report))


if __name__ == '__main__':
    main()
