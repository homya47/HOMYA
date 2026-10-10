"""Restart only the approved local HOMYA processes; no database writes.

Default reports status. --start leaves existing verified processes untouched.
Never stops a service or uses an occupied port belonging to another process.
"""
import argparse
import json
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.request
from op10_local_switch import require_private

RUN = Path('/Applications/XAMPP/xamppfiles/homya-local/20261008T180754Z-1afc52ac-d21f-4530-a1ac-83890af9b236')
SERVICES = [
    ('php-fpm', 19085, 'php-fpm.conf', ['/opt/homebrew/sbin/php-fpm', '--daemonize', '--fpm-config']),
    ('apache', 8080, 'httpd.conf', ['/Applications/XAMPP/xamppfiles/bin/httpd', '-f']),
]


def verified_pid(name, config):
    file = RUN / (name+'.pid')
    if not file.is_file() or file.is_symlink():
        return None
    text = file.read_text().strip()
    if not text.isdigit() or int(text) < 2:
        raise RuntimeError('PID_INVALID')
    result = subprocess.run(['/bin/ps', '-p', text, '-o', 'command='], capture_output=True)
    if result.returncode != 0:
        return None
    if str(RUN / config) not in result.stdout.decode(errors='replace'):
        # After reboot a stale PID may belong to another program. Do not touch it;
        # the port probe below still refuses to start over an occupied listener.
        return None
    return int(text)


def wait_ready(timeout=90):
    """Wait for MariaDB and the storefront without exposing response bodies."""
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    deadline = time.monotonic() + timeout
    database_socket = Path('/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock')
    while time.monotonic() < deadline:
        try:
            if database_socket.is_socket():
                with opener.open('http://127.0.0.1:8080/fr/catalogue', timeout=3) as response:
                    if response.status == 200:
                        return
        except (OSError, ValueError):
            pass
        time.sleep(2)
    raise RuntimeError('LOCAL_DEPENDENCIES_NOT_READY')


def main(argv=None):
    parser = argparse.ArgumentParser()
    parser.add_argument('--start', action='store_true')
    parser.add_argument('--wait-ready', action='store_true')
    args = parser.parse_args(argv)
    try:
        os.umask(0o077)
        require_private(RUN, True)
        facts = {}
        for name, port, config, command in SERVICES:
            require_private(RUN / config)
            pid = verified_pid(name, config)
            if pid is None and args.start:
                with socket.socket() as probe:
                    probe.bind(('127.0.0.1', port))
                arguments = command + [str(RUN / config)] + (['-k', 'start'] if name == 'apache' else [])
                with (RUN / (name+'-startup.log')).open('ab') as log:
                    if subprocess.run(arguments, stdout=log, stderr=log).returncode != 0:
                        raise RuntimeError('START_FAILED')
                deadline = time.monotonic() + 3
                while time.monotonic() < deadline:
                    pid = verified_pid(name, config)
                    if pid is not None: break
                    time.sleep(0.05)
                if pid is None: raise RuntimeError('START_NOT_READY')
            facts[name] = {'running': pid is not None, 'pid': pid}
        if args.wait_ready:
            wait_ready()
        print(json.dumps({'url': 'http://127.0.0.1:8080/fr', 'services': facts, 'sql_executed': False}))
        return 0
    except Exception:
        print('{"status":"LOCAL_RUNTIME_UNAVAILABLE_NO_OTHER_SERVICE_CHANGED"}')
        return 1


if __name__ == '__main__': raise SystemExit(main())
