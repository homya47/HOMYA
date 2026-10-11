"""Reviewed local retirement of Stripe; retain all orders and existing bank settings."""
import argparse
import datetime
import hashlib
import json
import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
STAGE = ROOT / 'output/qa-commerce/stripe-retirement-stage'
PLAN = ROOT / 'output/qa-commerce/stripe-retirement-plan.json'
FILES = [
    'app/Http/Controllers/AdminManagementController.php', 'app/Http/Controllers/CheckoutController.php',
    'app/Services/Admin/PrivateSettings.php', 'app/Services/Admin/Deliveries.php',
    'app/Services/Commerce/CheckoutReadiness.php', 'app/Services/Commerce/OrderStore.php',
    'app/Services/Commerce/BankTransfer.php', 'app/Http/Middleware/CommerceBoundary.php',
    'bootstrap/app.php', 'config/commerce.php', 'resources/js/app.js', 'resources/css/app.css',
    'resources/views/admin/settings.blade.php', 'resources/views/commerce/card-payment.blade.php',
    'resources/views/commerce/checkout.blade.php', 'resources/views/commerce/bank-transfer.blade.php',
    'resources/views/components/card-payment-preview.blade.php', 'README.md',
    'docs/ADMINISTRATION-HOMYA.md', 'docs/PARCOURS-ACHAT-HOMYA.md', 'composer.json', 'composer.lock',
    'vendor/autoload.php', 'public/build/manifest.json',
] + [f'lang/{locale}/{name}.php' for locale in ['fr', 'en', 'de'] for name in ['commerce', 'pages', 'legal']]
REMOVED = ['app/Http/Controllers/StripeWebhookController.php', 'app/Services/Commerce/StripeCheckout.php', 'resources/js/card-payment.js']


def digest(path):
    if path.is_symlink():
        raise RuntimeError('SYMLINK_REFUSED')
    return hashlib.sha256(path.read_bytes()).hexdigest()


def strip_env(content):
    return ''.join(line for line in content.splitlines(True) if not re.match(r'(?:HOMYA_STRIPE_|STRIPE_)', line))


def prepare():
    STAGE.mkdir(parents=True, exist_ok=True)
    paths = FILES + [str(p.relative_to(ROOT)) for folder in ['vendor/composer', 'public/build/assets'] for p in (ROOT / folder).glob('*') if p.is_file()]
    payloads = {name: (ROOT / name).read_bytes() for name in paths}
    # Apply only payment retirement to shared installed files; preserve concurrent SEO work.
    name = 'routes/web.php'
    before = (TARGET / name).read_text()
    after = before.replace('use App\\Http\\Controllers\\StripeWebhookController;\n', '')
    after = re.sub(r"Route::post\('/paiement/stripe/webhook'.*?\n    ->middleware[^\n]*\n\n", '', after, flags=re.S)
    after = ''.join(line for line in after.splitlines(True) if "name('order.card-session')" not in line)
    if "name('order.card-fallback')" not in after:
        line = next(line for line in (ROOT / name).read_text().splitlines(True) if "name('order.card-fallback')" in line)
        anchor = next(line for line in after.splitlines(True) if "name('order.card')" in line)
        after = after.replace(anchor, anchor + line)
    payloads[name] = after.encode()
    name = 'app/Http/Middleware/SecurityHeaders.php'
    before = (TARGET / name).read_text()
    after = re.sub(r"        if \(\$request->routeIs\('order.card',.*?\n        \}\n", '', before, flags=re.S).replace("'order.*', 'stripe.*',", "'order.*',")
    payloads[name] = after.encode()
    name = 'app/Providers/AppServiceProvider.php'
    before = (TARGET / name).read_text()
    after = re.sub(r'        \$this->app->scoped\(StripeClient::class,.*?\n        \]\)\);\n', '', before, flags=re.S).replace('use Stripe\\StripeClient;\n', '')
    payloads[name] = after.encode()
    files = []
    for name, payload in payloads.items():
        path = STAGE / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(payload)
        old = digest(TARGET / name) if (TARGET / name).exists() else None
        if old != digest(path):
            files.append({'path': name, 'before': old, 'after': digest(path)})
    deleted = REMOVED + [str(p.relative_to(TARGET)) for p in (TARGET / 'vendor/stripe').rglob('*') if p.is_file()]
    # Retired card-specific hashed bundles must no longer remain publicly executable.
    manifest = json.loads((ROOT / 'public/build/manifest.json').read_text())
    active_assets = {'public/build/' + entry['file'] for entry in manifest.values()}
    deleted += [str(p.relative_to(TARGET)) for p in (TARGET / 'public/build/assets').glob('*.js') if 'createEmbeddedCheckoutPage' in p.read_text() and str(p.relative_to(TARGET)) not in active_assets]
    removals = [{'path': name, 'before': digest(TARGET / name)} for name in dict.fromkeys(deleted) if (TARGET / name).exists()]
    plan = {'files': files, 'removals': removals, 'source_env_sha256': digest(ROOT / '.env'), 'target_env_sha256': digest(TARGET / '.env'), 'sql': False, 'services_changed': False}
    PLAN.write_text(json.dumps(plan, indent=2) + '\n')
    print(json.dumps({'phase': 'prepared', 'writes': len(files), 'retired_files': len(removals), 'plan_sha256': digest(PLAN)}))


def apply(expected):
    if not expected or digest(PLAN) != expected:
        raise RuntimeError('PLAN_CHANGED')
    plan = json.loads(PLAN.read_text())
    for base, label in [(ROOT, 'source'), (TARGET, 'target')]:
        if digest(base / '.env') != plan[label + '_env_sha256']:
            raise RuntimeError('ENV_CHANGED')
    for item in plan['files']:
        old = digest(TARGET / item['path']) if (TARGET / item['path']).exists() else None
        if old != item['before'] or digest(STAGE / item['path']) != item['after']:
            raise RuntimeError('FILE_CHANGED:' + item['path'])
    for item in plan['removals']:
        if digest(TARGET / item['path']) != item['before']:
            raise RuntimeError('RETIRED_FILE_CHANGED')
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup = TARGET.parent.parent / ('homya-local/stripe-retirement-backup-' + stamp)
    backup.mkdir(mode=0o700)
    for item in plan['files'] + plan['removals']:
        if item['before']:
            dest = backup / 'files' / item['path']
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(TARGET / item['path'], dest)
            if digest(dest) != item['before']:
                raise RuntimeError('BACKUP_MISMATCH')
    shutil.copy2(PLAN, backup / 'plan.json')
    # Private backups of both environment files; delete only the retired provider keys.
    for base, label in [(ROOT, 'source'), (TARGET, 'target')]:
        private_backup = backup / (label + '-env-before')
        shutil.copy2(base / '.env', private_backup)
        private_backup.chmod(0o600)
    ordered = sorted(plan['files'], key=lambda item: item['path'] in ['routes/web.php', 'public/build/manifest.json'])
    for item in ordered:
        dest = TARGET / item['path']
        old = digest(dest) if dest.exists() else None
        if old != item['before']:
            raise RuntimeError('CONCURRENT_TARGET_CHANGE')
        dest.parent.mkdir(parents=True, exist_ok=True)
        temporary = dest.with_name(dest.name + '.homya-retirement-pending')
        if temporary.exists():
            raise RuntimeError('PENDING_COLLISION')
        shutil.copy2(STAGE / item['path'], temporary)
        if digest(temporary) != item['after']:
            raise RuntimeError('COPY_MISMATCH')
        temporary.replace(dest)
    for item in plan['removals']:
        path = TARGET / item['path']
        if digest(path) != item['before']:
            raise RuntimeError('CONCURRENT_RETIRED_CHANGE')
        path.unlink()
    for base, label in [(ROOT, 'source'), (TARGET, 'target')]:
        path = base / '.env'
        if digest(path) != plan[label + '_env_sha256']:
            raise RuntimeError('CONCURRENT_ENV_CHANGE')
        before = path.read_text()
        after = strip_env(before)
        temporary = path.with_name('.env-retirement-pending')
        with temporary.open('x') as stream:
            temporary.chmod(0o600)
            stream.write(after)
        temporary.replace(path)
        if path.read_text() != strip_env((backup / (label + '-env-before')).read_text()):
            raise RuntimeError('OTHER_ENV_KEYS_CHANGED')
    if any(digest(TARGET / item['path']) != item['after'] for item in plan['files']) or any((TARGET / item['path']).exists() for item in plan['removals']):
        raise RuntimeError('INSTALLATION_MISMATCH')
    result = {'result': 'installed', 'written_files': len(plan['files']), 'retired_files': len(plan['removals']), 'backup': str(backup), 'other_private_settings_preserved': True, 'sql': False, 'services_changed': False, 'card_provider_configured': False}
    (ROOT / 'output/qa-commerce/stripe-retirement-result.json').write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--sha256')
    args = parser.parse_args()
    apply(args.sha256) if args.apply else prepare()
