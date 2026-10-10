"""Install this bounded optimization into the existing local runtime.

Default: validate and prepare a reviewable plan only. --apply backs up existing
files, verifies known starting hashes, then copies additions and changed files.
Only the dedicated Apache 8080 instance receives a graceful configuration reload.
No database, .env, FPM configuration, service stop or production operation.
"""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
RUNTIME = Path('/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236')
HTTPD = '/Applications/XAMPP/xamppfiles/bin/httpd'
CHANGED = ['app/Services/Catalogue.php','app/Providers/AppServiceProvider.php','app/Services/ResponsiveMedia.php',
           'resources/js/app.js','resources/views/components/product-image.blade.php','resources/views/catalogue/product.blade.php',
           'public/.htaccess','.gitignore','pint.json','scripts/profile_catalogue.php','scripts/prepare_responsive_media.py',
           'scripts/test_prepare_responsive_media.py','scripts/verify_optimization_http.py','scripts/install_local_optimization.py',
           'tests/Unit/ResponsiveMediaTest.php']


def digest(path):
    if path.is_symlink(): raise ValueError('Symlinks forbidden')
    return hashlib.sha256(path.read_bytes()).hexdigest()


def atomic_copy(source, target):
    if target.is_symlink(): raise ValueError('Target symlink forbidden')
    target.parent.mkdir(parents=True,exist_ok=True)
    pending = target.with_name(target.name+'.homya-optimization-pending')
    if pending.exists(): raise ValueError('Pending file collision')
    shutil.copy2(source,pending)
    if digest(pending)!=digest(source): raise ValueError('Copy mismatch')
    pending.replace(target)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--apply',action='store_true')
    args=parser.parse_args()
    evidence=ROOT/'output/qa-optimization'
    before=json.loads((evidence/'before.json').read_text())
    expected={r['path']:r['sha256'] for r in before['source_files']}
    protected=before['protected']
    for path,value in protected.items():
        if digest(ROOT/path)!=value or digest(TARGET/path)!=value: raise ValueError('Protected file changed')
    runtime_before=json.loads((evidence/'runtime-before.json').read_text())
    for path,value in runtime_before.items():
        if digest(RUNTIME/path)!=value: raise ValueError('Runtime configuration changed')
    apache_pid=(RUNTIME/'apache.pid').read_text().strip()
    fpm_pid=(RUNTIME/'php-fpm.pid').read_text().strip()
    if not apache_pid.isdigit() or not fpm_pid.isdigit(): raise ValueError('Invalid PID')
    command=subprocess.run(['/bin/ps','-p',apache_pid,'-o','command='],capture_output=True,text=True,check=True).stdout
    if str(RUNTIME/'httpd.conf') not in command: raise ValueError('Unowned Apache')
    files=list(CHANGED)
    files += [str(p.relative_to(ROOT)) for p in (ROOT/'public/build/assets').glob('*') if p.is_file()]
    files += [str(p.relative_to(ROOT)) for p in (ROOT/'public/media/catalogue-display').glob('*') if p.is_file()]
    # Publish the manifest only after its new assets are present.
    files.append('public/build/manifest.json')
    plan=[]
    for path in files:
        source=ROOT/path;target=TARGET/path
        source_hash=digest(source)
        target_hash=digest(target) if target.exists() else None
        if target_hash==source_hash: continue
        if target_hash is not None and target_hash!=expected.get(path): raise ValueError('Unexpected target collision: '+path)
        plan.append({'path':path,'source_sha256':source_hash,'target_before_sha256':target_hash})
    candidate=evidence/'httpd-candidate.conf'
    check=subprocess.run([HTTPD,'-t','-f',str(candidate)],capture_output=True,text=True)
    if check.returncode!=0: raise ValueError('Apache candidate invalid')
    (evidence/'installation-plan.json').write_text(json.dumps({'files':plan,'config_before':runtime_before['httpd.conf'],
        'config_after':digest(candidate),'apache_pid':apache_pid,'fpm_pid':fpm_pid},indent=2)+'\n')
    if not args.apply:
        print(json.dumps({'phase':'prepared','files':len(plan),'apache_syntax':'OK'}));return
    tests=json.loads(Path('/tmp/homya-optimization-tests-final.json').read_text())
    if tests.get('result')!='passed': raise ValueError('Application tests not passed')
    stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    backup=RUNTIME/('optimization-backup-'+stamp)
    backup.mkdir(mode=0o700)
    # Finish and verify every backup before any runtime mutation.
    for item in plan:
        if item['target_before_sha256'] is None: continue
        path=item['path'];saved=backup/'files'/path
        saved.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(TARGET/path,saved)
        if digest(saved)!=item['target_before_sha256']: raise ValueError('Backup mismatch')
    shutil.copy2(RUNTIME/'httpd.conf',backup/'httpd.conf')
    if digest(backup/'httpd.conf')!=runtime_before['httpd.conf']: raise ValueError('Config backup mismatch')
    (backup/'plan.json').write_text(json.dumps(plan,indent=2)+'\n')
    installed=[]
    for item in plan:
        path=item['path'];target=TARGET/path
        if digest(ROOT/path)!=item['source_sha256']: raise ValueError('Source changed during installation')
        current=digest(target) if target.exists() else None
        if current!=item['target_before_sha256']: raise ValueError('Target changed during installation')
        atomic_copy(ROOT/path,target)
        installed.append(path)
    if digest(RUNTIME/'httpd.conf')!=runtime_before['httpd.conf']: raise ValueError('Config changed during installation')
    atomic_copy(candidate,RUNTIME/'httpd.conf')
    os.chmod(RUNTIME/'httpd.conf',0o600)
    subprocess.run([HTTPD,'-t','-f',str(RUNTIME/'httpd.conf')],capture_output=True,check=True)
    subprocess.run([HTTPD,'-f',str(RUNTIME/'httpd.conf'),'-k','graceful'],capture_output=True,check=True)
    if (RUNTIME/'apache.pid').read_text().strip()!=apache_pid or (RUNTIME/'php-fpm.pid').read_text().strip()!=fpm_pid:
        raise ValueError('Unexpected process identity change')
    for item in plan:
        if digest(TARGET/item['path'])!=item['source_sha256']: raise ValueError('Installed hash mismatch')
    for path,value in protected.items():
        if digest(ROOT/path)!=value or digest(TARGET/path)!=value: raise ValueError('Protected file changed after installation')
    if digest(RUNTIME/'php-fpm.conf')!=runtime_before['php-fpm.conf']: raise ValueError('FPM configuration changed')
    result={'backup':str(backup),'installed_files':len(installed),'apache_pid':apache_pid,'fpm_pid':fpm_pid,
            'protected_files_unchanged':True,'sql_writes':False,'services_stopped':False,'apache_graceful_reload':True}
    (evidence/'installation.json').write_text(json.dumps(result,indent=2)+'\n')
    print(json.dumps(result))


if __name__=='__main__':main()
