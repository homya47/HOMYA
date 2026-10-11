"""Stage reviewed SEO/catalogue changes, preserving installed payments, private settings and data."""
import argparse
import datetime
import hashlib
import json
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
QA = ROOT / 'output/qa-seo-catalogue'
STAGE = QA / 'install-stage'
PLAN = QA / 'install-plan.json'
FILES = [
    'app/Services/Catalogue.php', 'app/Services/CatalogueContent.php', 'app/Services/Seo.php',
    'app/Services/MerchantProducts.php', 'app/Http/Controllers/CatalogueController.php',
    'app/Http/Controllers/SeoController.php', 'app/Http/Controllers/MerchantFeedController.php',
    'app/Console/Commands/GoogleReadinessAudit.php', 'config/seo.php', 'config/merchant.php',
    'lang/catalogue-content.json', 'resources/js/catalogue-variants.js',
    'resources/views/catalogue/product.blade.php', 'resources/views/catalogue/index.blade.php',
    'resources/views/promotions.blade.php', 'resources/views/components/layout.blade.php',
    'public/.htaccess', 'public/robots.txt', 'docs/SEO-GOOGLE-HOMYA.md', 'docs/CATALOGUE-MERCHANT-XAMPP.md',
] + [f'lang/{locale}/{name}.php' for locale in ['fr', 'en', 'de'] for name in ['seo', 'catalogue']]


def digest(path):
    if path.is_symlink():
        raise RuntimeError('SYMLINK_REFUSED')
    return hashlib.sha256(path.read_bytes()).hexdigest()


def replace_once(text, before, after):
    if text.count(before) != 1:
        raise RuntimeError('MERGE_ANCHOR_CHANGED')
    return text.replace(before, after, 1)


def prepare():
    STAGE.mkdir(parents=True, exist_ok=True)
    source_hashes = {name: digest(ROOT / name) for name in FILES}
    payloads = {name: (ROOT / name).read_bytes() for name in FILES}
    name = 'routes/web.php'
    installed = (TARGET / name).read_text()
    source = (ROOT / name).read_text()
    routes = [line for line in source.splitlines(True) if "->name('seo." in line or "->name('merchant.feed')" in line]
    if len(routes) != 5:
        raise RuntimeError('SEO_ROUTE_COUNT')
    if "name('seo.robots')" not in installed:
        installed = replace_once(installed, 'use App\\Http\\Controllers\\HomeController;\n',
                                 'use App\\Http\\Controllers\\HomeController;\nuse App\\Http\\Controllers\\SeoController;\nuse App\\Http\\Controllers\\MerchantFeedController;\n')
        installed = replace_once(installed, "Route::redirect('/', '/fr');", "Route::redirect('/', '/fr', 301);\n" + ''.join(routes).rstrip())
    elif any(line not in installed for line in routes):
        raise RuntimeError('INSTALLED_SEO_ROUTES_DIVERGED')
    payloads[name] = installed.encode()
    name = 'app/Http/Middleware/SecurityHeaders.php'
    installed = (TARGET / name).read_text()
    source = (ROOT / name).read_text()
    if 'use App\\Services\\Seo;' not in installed:
        installed = replace_once(installed, 'use Closure;', 'use App\\Services\\Seo;\nuse Closure;')
    start = source.index("        $robots = $request->attributes->get('seo.robots');")
    end = source.index('        $admin =', start)
    if "        $robots = $request->attributes->get('seo.robots');" not in installed:
        installed = replace_once(installed, '        $admin =', source[start:end] + '        $admin =')
    else:
        before_start = installed.index("        $robots = $request->attributes->get('seo.robots');")
        before_end = installed.index('        $admin =', before_start)
        installed = installed[:before_start] + source[start:end] + installed[before_end:]
    payloads[name] = installed.encode()
    # Compile only a merged installed JS entry. Keep the installed CSS, admin bundle and payment modules.
    protected = ['.env', 'resources/css/app.css', 'resources/js/admin.js', 'vite.config.js']
    build_root = QA / 'build-source'
    (build_root / 'resources/js').mkdir(parents=True, exist_ok=True)
    for path in (TARGET / 'resources/js').glob('*.js'):
        if path.is_file():
            shutil.copy2(path, build_root / 'resources/js' / path.name)
            if path.name != 'app.js':
                protected.append(str(path.relative_to(TARGET)))
    entry = (TARGET / 'resources/js/app.js').read_text()
    if "import './catalogue-variants'" not in entry:
        entry = "import './catalogue-variants';\n" + entry
    payloads['resources/js/app.js'] = entry.encode()
    (build_root / 'resources/js/app.js').write_text(entry)
    shutil.copy2(ROOT / 'resources/js/catalogue-variants.js', build_root / 'resources/js/catalogue-variants.js')
    build_output = QA / 'merged-build'
    config = QA / 'merged-vite.config.mjs'
    config.write_text("import {defineConfig} from 'vite';\nexport default defineConfig(" + json.dumps({
        'root': str(build_root), 'base': '/build/', 'build': {'outDir': str(build_output), 'emptyOutDir': False,
        'manifest': True, 'rollupOptions': {'input': 'resources/js/app.js', 'output': {'entryFileNames': 'assets/app-[hash].js'}}}
    }) + ");\n")
    subprocess.run(['node', str(ROOT / 'node_modules/vite/bin/vite.js'), 'build', '--config', str(config)], cwd=ROOT, check=True)
    built = json.loads((build_output / '.vite/manifest.json').read_text())
    manifest = json.loads((TARGET / 'public/build/manifest.json').read_text())
    manifest['resources/js/app.js'] = built['resources/js/app.js']
    for entry in built.values():
        asset = build_output / entry['file']
        payloads['public/build/' + entry['file']] = asset.read_bytes()
    payloads['public/build/manifest.json'] = (json.dumps(manifest, indent=2) + '\n').encode()
    files = []
    for name, payload in payloads.items():
        staged = STAGE / name
        staged.parent.mkdir(parents=True, exist_ok=True)
        staged.write_bytes(payload)
        before = digest(TARGET / name) if (TARGET / name).exists() else None
        if before != digest(staged):
            files.append({'path': name, 'before': before, 'after': digest(staged)})
    protected += [str(path.relative_to(TARGET)) for path in (TARGET / 'storage/app/catalogue').rglob('*.json') if path.is_file()]
    plan = {'target': str(TARGET), 'files': files, 'source_hashes': source_hashes,
            'preserved_hashes': {name: digest(TARGET / name) for name in protected},
            'sql': False, 'services_changed': False, 'payment_changed': False}
    PLAN.write_text(json.dumps(plan, indent=2) + '\n')
    print(json.dumps({'phase': 'prepared', 'files': len(files), 'plan_sha256': digest(PLAN)}))


def apply(expected):
    if not expected or digest(PLAN) != expected:
        raise RuntimeError('PLAN_CHANGED')
    plan = json.loads(PLAN.read_text())
    for name, sha in plan['source_hashes'].items():
        if digest(ROOT / name) != sha:
            raise RuntimeError('SOURCE_CHANGED:' + name)
    for name, sha in plan['preserved_hashes'].items():
        if digest(TARGET / name) != sha:
            raise RuntimeError('PRESERVED_FILE_CHANGED:' + name)
    for item in plan['files']:
        dest = TARGET / item['path']
        before = digest(dest) if dest.exists() else None
        if before != item['before'] or digest(STAGE / item['path']) != item['after']:
            raise RuntimeError('FILE_CHANGED:' + item['path'])
    backup = TARGET.parent.parent / ('homya-local/seo-catalogue-backup-' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
    backup.mkdir(mode=0o700)
    for item in plan['files']:
        if item['before']:
            dest = backup / 'files' / item['path']
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(TARGET / item['path'], dest)
            if digest(dest) != item['before']:
                raise RuntimeError('BACKUP_MISMATCH')
    shutil.copy2(PLAN, backup / 'plan.json')
    # Services before views; routes and asset manifest last. No deletion of old assets or caches.
    priority = lambda row: (2 if row['path'] in ['routes/web.php', 'public/build/manifest.json'] else 1 if row['path'].startswith('resources/views/') else 0)
    changed = []
    try:
        for item in sorted(plan['files'], key=priority):
            dest = TARGET / item['path']
            before = digest(dest) if dest.exists() else None
            if before != item['before']:
                raise RuntimeError('CONCURRENT_TARGET_CHANGE')
            dest.parent.mkdir(parents=True, exist_ok=True)
            temporary = dest.with_name(dest.name + '.homya-seo-pending')
            if temporary.exists():
                raise RuntimeError('PENDING_COLLISION')
            shutil.copy2(STAGE / item['path'], temporary)
            if digest(temporary) != item['after']:
                raise RuntimeError('COPY_MISMATCH')
            temporary.replace(dest)
            changed.append(item)
        if any(digest(TARGET / name) != sha for name, sha in plan['preserved_hashes'].items()):
            raise RuntimeError('PRESERVED_FILE_CHANGED')
        if any(digest(TARGET / item['path']) != item['after'] for item in plan['files']):
            raise RuntimeError('INSTALLATION_MISMATCH')
    except Exception:
        for item in reversed(changed):
            dest = TARGET / item['path']
            # Never overwrite a concurrent edit while restoring our own writes.
            if dest.exists() and digest(dest) == item['after']:
                if item['before']:
                    temporary = dest.with_name(dest.name + '.homya-seo-restore')
                    shutil.copy2(backup / 'files' / item['path'], temporary)
                    temporary.replace(dest)
                else:
                    dest.unlink()
        raise
    result = {'result': 'installed', 'files': len(plan['files']), 'backup': str(backup),
              'private_env_unchanged': True, 'sql': False, 'services_changed': False, 'payment_changed': False}
    (QA / 'install-result.json').write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--sha256')
    args = parser.parse_args()
    apply(args.sha256) if args.apply else prepare()
