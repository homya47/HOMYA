"""Start only the local HOMYA reservation scheduler. Default reports status; never stops existing services."""
import argparse
import json
import os
import subprocess
import time
from pathlib import Path

TARGET = Path('/Applications/XAMPP/xamppfiles/htdocs/HOMYA')
RUNTIME = Path('/Applications/XAMPP/xamppfiles/homya-local/commerce-scheduler')
PHP = '/opt/homebrew/bin/php'
COMMAND = [PHP, str(TARGET / 'artisan'), 'schedule:work', '--no-interaction']


def status():
    pidfile = RUNTIME / 'scheduler.pid'
    if not pidfile.exists():
        return None
    if pidfile.is_symlink():
        raise RuntimeError('UNEXPECTED_PID_FILE')
    pid = pidfile.read_text().strip()
    if not pid.isdigit() or int(pid) < 2:
        raise RuntimeError('INVALID_PID')
    process = subprocess.run(['/bin/ps', '-p', pid, '-o', 'command='], capture_output=True, text=True)
    if process.returncode != 0:
        return None
    if ' '.join(COMMAND) not in process.stdout:
        raise RuntimeError('PID_NOT_OWNED')
    return int(pid)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--start', action='store_true')
    args = parser.parse_args()
    pid = status()
    if args.start and pid is None:
        if RUNTIME.is_symlink() or not (TARGET / 'app/Console/Commands/ExpireBankTransfers.php').is_file():
            raise RuntimeError('SCHEDULER_NOT_INSTALLED')
        os.umask(0o077)
        RUNTIME.mkdir(mode=0o700, exist_ok=True)
        if RUNTIME.stat().st_mode & 0o077 or RUNTIME.stat().st_uid != os.geteuid():
            raise RuntimeError('PRIVATE_RUNTIME_REQUIRED')
        check = subprocess.run([PHP, str(TARGET / 'artisan'), 'homya:expire-bank-transfers'], cwd=TARGET, capture_output=True, text=True)
        if check.returncode != 0:
            raise RuntimeError('RESERVATION_TARGET_UNAVAILABLE')
        with (RUNTIME / 'scheduler.log').open('ab') as log:
            process = subprocess.Popen(COMMAND, cwd=TARGET, stdin=subprocess.DEVNULL, stdout=log, stderr=log, start_new_session=True)
        (RUNTIME / 'scheduler.log').chmod(0o600)
        (RUNTIME / 'scheduler.pid').write_text(str(process.pid) + '\n')
        (RUNTIME / 'scheduler.pid').chmod(0o600)
        time.sleep(0.3)
        if process.poll() is not None:
            raise RuntimeError('SCHEDULER_START_FAILED')
        pid = status()
    print(json.dumps({'running': pid is not None, 'pid': pid, 'checks': 'bank reservations every minute', 'other_services_changed': False}))


if __name__ == '__main__':
    try:
        main()
    except Exception:
        print('{"status":"RESERVATION_SCHEDULER_UNAVAILABLE"}')
        raise SystemExit(1)
