"""Install the explicitly requested HOMYA login and MariaDB boot jobs.

Default only prints the plan. System installation requires macOS administrator
authentication. Existing launch jobs are never replaced and no service is stopped.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import plistlib
import shutil
import subprocess
import tempfile
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
USER_JOB = Path('/Users/akm/Library/LaunchAgents/local.homya.autostart.plist')
SYSTEM_JOB = Path('/Library/LaunchDaemons/local.homya.xampp-mariadb.plist')
LOGS = Path('/Users/akm/Library/Logs/HOMYA')
SYSTEM_LOG = Path('/var/log/homya-xampp-mariadb.log')
PREVIOUS_STARTER_SHA = '80adcaf80fdd2b465116f91d3263702051fa4311b76e9443f7b262e592aca62f'


def configurations():
    common = {
        'RunAtLoad': True,
        'AbandonProcessGroup': True,
        'KeepAlive': {'SuccessfulExit': False},
        'ThrottleInterval': 60,
        'EnvironmentVariables': {'PATH': '/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin', 'PYTHONDONTWRITEBYTECODE': '1'},
    }
    return {
        'user': dict(common, Label='local.homya.autostart',
                     ProgramArguments=['/usr/bin/python3', str(TARGET / 'scripts/start_homya_local.py'), '--start', '--wait-ready'],
                     WorkingDirectory=str(TARGET), LimitLoadToSessionType='Aqua',
                     StandardOutPath=str(LOGS / 'startup.log'), StandardErrorPath=str(LOGS / 'startup.log')),
        'system': dict(common, Label='local.homya.xampp-mariadb',
                       ProgramArguments=['/Applications/XAMPP/xamppfiles/xampp', 'startmysql'],
                       WorkingDirectory='/Applications/XAMPP/xamppfiles',
                       StandardOutPath=str(SYSTEM_LOG), StandardErrorPath=str(SYSTEM_LOG)),
    }


def require_absent(path):
    if path.exists() or path.is_symlink():
        raise RuntimeError('EXISTING_FILE_PRESERVED')


def write_new_plist(path, value):
    require_absent(path)
    data = plistlib.dumps(value)
    with path.open('xb') as file:
        file.write(data)
    path.chmod(0o644)
    if plistlib.loads(path.read_bytes()) != value:
        raise RuntimeError('PLIST_VERIFY_FAILED')
    subprocess.run(['/usr/bin/plutil', '-lint', str(path)], check=True)


def private_directory(path):
    if path.is_symlink():
        raise RuntimeError('DIRECTORY_SYMLINK')
    path.mkdir(mode=0o700, exist_ok=True)
    if path.stat().st_uid != os.getuid() or path.stat().st_mode & 0o077:
        raise RuntimeError('PRIVATE_DIRECTORY_REQUIRED')


def private_log(path):
    require_absent(path)
    with path.open('xb'):
        pass
    path.chmod(0o600)


def install_system():
    if os.geteuid() != 0:
        raise RuntimeError('ADMIN_AUTHENTICATION_REQUIRED')
    require_absent(SYSTEM_JOB)
    require_absent(SYSTEM_LOG)
    private_log(SYSTEM_LOG)
    write_new_plist(SYSTEM_JOB, configurations()['system'])
    os.chown(SYSTEM_JOB, 0, 0)
    subprocess.run(['/bin/launchctl', 'enable', 'system/local.homya.xampp-mariadb'], check=True)
    subprocess.run(['/bin/launchctl', 'bootstrap', 'system', str(SYSTEM_JOB)], check=True)
    print(json.dumps({'installed': str(SYSTEM_JOB), 'no_existing_service_stopped': True}))


def install_user():
    if os.getuid() != 501 or os.geteuid() == 0:
        raise RuntimeError('AKM_SESSION_REQUIRED')
    require_absent(USER_JOB)
    installed = TARGET / 'scripts/start_homya_local.py'
    if installed.is_symlink() or not installed.is_file():
        raise RuntimeError('STARTER_NOT_REGULAR')
    before = installed.read_bytes()
    if hashlib.sha256(before).hexdigest() != PREVIOUS_STARTER_SHA:
        raise RuntimeError('INSTALLED_STARTER_CHANGED')
    after = (ROOT / 'scripts/start_homya_local.py').read_bytes()
    private_directory(LOGS)
    backup = LOGS / ('installation-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
    private_directory(backup)
    with (backup / 'start_homya_local.before.py').open('xb') as file:
        file.write(before)
    (backup / 'start_homya_local.before.py').chmod(0o600)
    if (backup / 'start_homya_local.before.py').read_bytes() != before:
        raise RuntimeError('BACKUP_VERIFY_FAILED')
    descriptor, temporary = tempfile.mkstemp(dir=installed.parent, prefix='.homya-startup-')
    with os.fdopen(descriptor, 'wb') as file:
        file.write(after)
    shutil.copystat(installed, temporary)
    if installed.read_bytes() != before:
        raise RuntimeError('INSTALLED_STARTER_CHANGED')
    os.replace(temporary, installed)
    if installed.read_bytes() != after:
        raise RuntimeError('STARTER_VERIFY_FAILED')
    private_log(LOGS / 'startup.log')
    write_new_plist(USER_JOB, configurations()['user'])
    subprocess.run(['/bin/launchctl', 'enable', 'gui/501/local.homya.autostart'], check=True)
    subprocess.run(['/bin/launchctl', 'bootstrap', 'gui/501', str(USER_JOB)], check=True)
    print(json.dumps({'installed': str(USER_JOB), 'starter_backup': str(backup), 'no_existing_service_stopped': True}))


def main():
    parser = argparse.ArgumentParser()
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--install-system', action='store_true')
    modes.add_argument('--install-user', action='store_true')
    args = parser.parse_args()
    os.umask(0o077)
    if args.install_system:
        install_system()
    elif args.install_user:
        install_user()
    else:
        print(json.dumps(configurations(), indent=2))


if __name__ == '__main__':
    main()
