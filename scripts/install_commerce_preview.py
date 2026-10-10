"""Reviewable local file sync. Does not touch SQL, services, archives or original media."""
import argparse
import datetime
import hashlib
import json
import os
import re
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
PLAN = ROOT / 'output/qa-commerce/preview-installation-plan.json'


def digest(path):
    if path.is_symlink():
        raise RuntimeError('SYMLINK_REFUSED')
    return hashlib.sha256(path.read_bytes()).hexdigest()


def require(value, message):
    if not value:
        raise RuntimeError(message)


def names(args):
    return subprocess.run(['git'] + args, cwd=ROOT, text=True, capture_output=True, check=True).stdout.splitlines()


def env_values(path):
    return dict(line.split('=', 1) for line in path.read_text().splitlines() if re.match(r'^[A-Z][A-Z0-9_]*=', line))


def prepare(open_deferred=False):
    require(TARGET.is_dir() and not TARGET.is_symlink(), 'LOCAL_TARGET_MISSING')
    require(not (TARGET / 'bootstrap/cache/config.php').exists(), 'CONFIG_CACHE_PRESENT')
    source_env, target_env = env_values(ROOT / '.env'), env_values(TARGET / '.env')
    require(source_env['APP_KEY'] == target_env['APP_KEY'], 'ENCRYPTION_KEY_MISMATCH')
    source_paths = names(['diff', '--name-only', 'HEAD']) + names(['ls-files', '--others', '--exclude-standard'])
    allowed = ('app/', 'bootstrap/', 'config/', 'database/migrations/commerce/', 'docs/', 'lang/', 'resources/', 'routes/', 'scripts/', 'tests/', 'public/branding/')
    require(all(name.startswith(allowed) or name in ('.env.example', 'README.md', 'composer.json', 'composer.lock', 'phpunit.xml', 'public/favicon.ico', 'vite.config.js') for name in source_paths), 'UNEXPECTED_SOURCE_PATH')
    previous_hashes = {}
    for backup in (TARGET.parent.parent / 'homya-local').glob('commerce-backup-*/plan.json'):
        for item in json.loads(backup.read_text())['files']:
            previous_hashes.setdefault(item['path'], set()).add(item['source_sha256'])
    for name in source_paths:
        target = TARGET / name
        if target.exists():
            if digest(target) == digest(ROOT / name) or digest(target) in previous_hashes.get(name, set()):
                continue
            baseline = subprocess.run(['git', 'show', 'HEAD:' + name], cwd=ROOT, capture_output=True)
            require(baseline.returncode == 0 and digest(target) == hashlib.sha256(baseline.stdout).hexdigest(), 'TARGET_SOURCE_COLLISION:' + name)
    vendor = [str(path.relative_to(ROOT)) for folder in ('vendor/stripe', 'vendor/composer', 'vendor/dompdf', 'vendor/masterminds', 'vendor/sabberworm') for path in (ROOT / folder).rglob('*') if path.is_file()]
    vendor.append('vendor/autoload.php')
    assets = [str(path.relative_to(ROOT)) for path in (ROOT / 'public/build/assets').glob('*') if path.is_file()]
    ordered = vendor + assets + [name for name in source_paths if name != 'routes/web.php'] + ['routes/web.php', 'public/build/manifest.json']
    files = []
    for name in dict.fromkeys(ordered):
        before = digest(TARGET / name) if (TARGET / name).exists() else None
        after = digest(ROOT / name)
        if before != after:
            files.append(dict(path=name, source_sha256=after, target_sha256=before))
    keys = sorted(key for key in source_env if key.startswith(('HOMYA_COMMERCE_', 'HOMYA_BANK_')) or key in ('HOMYA_INVENTORY_ENABLED', 'HOMYA_CHECKOUT_ENABLED', 'HOMYA_STRIPE_MODE', 'HOMYA_DEFERRED_ORDERS_ENABLED'))
    require(source_env['HOMYA_COMMERCE_LEGAL_READY'] == 'false', 'LEGAL_GATE_CHANGED')
    require(source_env['HOMYA_CHECKOUT_ENABLED'] == ('true' if open_deferred else 'false'), 'CHECKOUT_GATE_CHANGED')
    if open_deferred:
        require(source_env.get('HOMYA_DEFERRED_ORDERS_ENABLED') == 'true' and source_env.get('APP_ENV') == 'local'
                and source_env.get('APP_URL') in ('http://127.0.0.1:8080', 'http://127.0.0.1:8000')
                and target_env.get('APP_ENV') == 'local'
                and target_env.get('APP_URL') in ('http://127.0.0.1:8080', 'http://127.0.0.1:8000')
                and source_env.get('HOMYA_BANK_TRANSFER_ENABLED', 'false') == 'false'
                and not source_env.get('STRIPE_SECRET_KEY', '').strip('"\''), 'DEFERRED_MODE_MISMATCH')
    plan = dict(open_deferred=open_deferred, target=str(TARGET), files=files, env_keys=keys, source_env_sha256=digest(ROOT / '.env'), target_env_sha256=digest(TARGET / '.env'), services_changed=False, sql=False)
    PLAN.write_text(json.dumps(plan, indent=2) + '\n')
    print(json.dumps({'phase': 'prepared', 'files': len(files), 'plan_sha256': digest(PLAN)}))


def apply(expected, open_deferred=False):
    require(expected and digest(PLAN) == expected, 'PLAN_DIGEST_MISMATCH')
    plan = json.loads(PLAN.read_text())
    require(plan.get('open_deferred', False) == open_deferred, 'OPENING_MODE_MISMATCH')
    require(digest(ROOT / '.env') == plan['source_env_sha256'] and digest(TARGET / '.env') == plan['target_env_sha256'], 'ENV_CHANGED')
    for item in plan['files']:
        require(digest(ROOT / item['path']) == item['source_sha256'], 'SOURCE_CHANGED')
        before = digest(TARGET / item['path']) if (TARGET / item['path']).exists() else None
        require(before == item['target_sha256'], 'TARGET_CHANGED')
    backup = TARGET.parent.parent / ('homya-local/commerce-backup-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
    backup.mkdir(mode=0o700)
    shutil.copy2(TARGET / '.env', backup / '.env-before')
    (backup / '.env-before').chmod(0o600)
    for item in plan['files']:
        if item['target_sha256'] is not None:
            dest = backup / 'files' / item['path']
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(TARGET / item['path'], dest)
            require(digest(dest) == item['target_sha256'], 'BACKUP_MISMATCH')
    shutil.copy2(PLAN, backup / 'plan.json')
    for item in plan['files']:
        dest = TARGET / item['path']
        dest.parent.mkdir(parents=True, exist_ok=True)
        temporary = dest.with_name(dest.name + '.homya-commerce-pending')
        require(not temporary.exists(), 'TEMPORARY_COLLISION')
        shutil.copy2(ROOT / item['path'], temporary)
        require(digest(temporary) == item['source_sha256'], 'COPY_MISMATCH')
        temporary.replace(dest)
    before = (TARGET / '.env').read_text()
    lines = before.splitlines()
    source = env_values(ROOT / '.env')
    for key in plan['env_keys']:
        require(sum(line.startswith(key + '=') for line in lines) <= 1, 'DUPLICATE_ENV_KEY')
        value = key + '=' + source[key]
        if any(line.startswith(key + '=') for line in lines):
            lines = [value if line.startswith(key + '=') else line for line in lines]
        else:
            lines.append(value)
    temporary = TARGET / '.env-commerce-pending'
    require(not temporary.exists(), 'ENV_TEMPORARY_COLLISION')
    with temporary.open('x') as stream:
        stream.write('\n'.join(lines) + '\n')
    temporary.chmod(0o600)
    require((TARGET / '.env').read_text() == before, 'ENV_CHANGED')
    temporary.replace(TARGET / '.env')
    target = env_values(TARGET / '.env')
    previous = env_values(backup / '.env-before')
    require(all(target.get(key) == value for key, value in previous.items() if key not in plan['env_keys']), 'PROTECTED_ENV_CHANGED')
    require(all(digest(TARGET / item['path']) == item['source_sha256'] for item in plan['files']), 'INSTALLED_FILE_MISMATCH')
    result = dict(result='installed', files=len(plan['files']), backup=str(backup), services_changed=False, sql=False, checkout_enabled=open_deferred, deferred_orders_enabled=open_deferred, protected_env_preserved=True)
    (ROOT / 'output/qa-commerce/preview-installation-result.json').write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--sha256')
    parser.add_argument('--open-deferred', action='store_true', help='Explicit authorized LOCAL order opening without an active payment method')
    args = parser.parse_args()
    if args.apply:
        apply(args.sha256, args.open_deferred)
    else:
        prepare(args.open_deferred)


if __name__ == '__main__':
    main()
