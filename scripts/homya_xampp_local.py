"""Install a verified complete copy and prepare an isolated local XAMPP Apache.

No SQL or global XAMPP configuration changes. Default is inert.
--install requires recorded human DECISION-046. Ports are fixed loopback.
"""
import argparse
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import pwd
import grp
import shutil
import socket
import stat
import subprocess
import uuid
from op10_local_switch import require_private

SOURCE = Path('/Users/akm/Documents/ChatGPT/HOMYA')
XAMPP = Path('/Applications/XAMPP/xamppfiles')
TARGET = XAMPP / 'htdocs/HOMYA'
REGISTRY = Path('/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md')
PHP_FPM = '/opt/homebrew/sbin/php-fpm'
HTTPD = str(XAMPP / 'bin/httpd')


def emit(stage, **fields):
    print(json.dumps(dict(stage=stage, **fields)), flush=True)


def manifest(root):
    root_stat = root.lstat()
    result = {'.': {'type': 'directory', 'mode': stat.S_IMODE(root_stat.st_mode),
                    'uid': root_stat.st_uid, 'gid': root_stat.st_gid}}
    for current, dirs, files in os.walk(root, followlinks=False):
        for name in sorted(dirs + files):
            path = Path(current) / name
            s = path.lstat()
            item = {'mode': stat.S_IMODE(s.st_mode), 'uid': s.st_uid, 'gid': s.st_gid}
            if stat.S_ISLNK(s.st_mode):
                item.update(type='link', target=os.readlink(path))
            elif stat.S_ISDIR(s.st_mode):
                item.update(type='directory')
            elif stat.S_ISREG(s.st_mode):
                h = hashlib.sha256()
                with path.open('rb') as stream:
                    for chunk in iter(lambda: stream.read(1024 * 1024), b''): h.update(chunk)
                item.update(type='file', bytes=s.st_size, sha256=h.hexdigest())
            else:
                raise RuntimeError('SPECIAL_FILE_REFUSED')
            result[str(path.relative_to(root))] = item
    return result


def write(path, data):
    with path.open('x') as stream:
        stream.write(data if isinstance(data, str) else json.dumps(data, indent=2) + '\n')
    path.chmod(0o600)


def restore_source_groups(stage, expected):
    """macOS cloned files may inherit the destination group despite cp -p."""
    changed = 0
    for relative, item in expected.items():
        if Path(relative).is_absolute() or '..' in Path(relative).parts:
            raise RuntimeError('MANIFEST_PATH_INVALID')
        path = stage / relative
        actual = path.lstat()
        if actual.st_uid != item['uid'] or stat.S_IMODE(actual.st_mode) != item['mode']:
            raise RuntimeError('COPY_METADATA_DIVERGED')
        if actual.st_gid != item['gid']:
            if actual.st_uid != os.getuid() or item['gid'] not in [os.getgid(), *os.getgroups()]:
                raise RuntimeError('COPY_GROUP_NOT_OWNED')
            os.chown(path, -1, item['gid'], follow_symlinks=False)
            changed += 1
    return changed


def configs(run):
    user, group = pwd.getpwuid(os.getuid()).pw_name, grp.getgrgid(os.getgid()).gr_name
    modules = ['authz_core', 'authz_host', 'unixd', 'dir', 'mime', 'log_config', 'alias', 'proxy', 'proxy_fcgi']
    loads = '\n'.join(f'LoadModule {name}_module "{XAMPP}/modules/mod_{name}.so"' for name in modules)
    apache = f'''ServerRoot "{XAMPP}"
Listen 127.0.0.1:8080
ServerName 127.0.0.1:8080
ServerTokens Prod
ServerSignature Off
{loads}
User {user}
Group {group}
PidFile "{run}/apache.pid"
ErrorLog "{run}/apache-error.log"
LogLevel warn
TypesConfig "{XAMPP}/etc/mime.types"
DocumentRoot "{TARGET}/public"
ProxyRequests Off
<Directory />
    AllowOverride None
    Require all denied
</Directory>
<Directory "{TARGET}">
    AllowOverride None
    Require all denied
</Directory>
<Directory "{TARGET}/public">
    AllowOverride None
    Options -Indexes -MultiViews +FollowSymLinks
    Require local
    DirectoryIndex index.php
    FallbackResource /index.php
    <FilesMatch "\\.php$">
        SetHandler "proxy:fcgi://127.0.0.1:19085"
    </FilesMatch>
</Directory>
<FilesMatch "^\\.">
    Require all denied
</FilesMatch>
RedirectMatch ^/HOMYA/?$ /fr
'''
    fpm = f'''[global]
pid = {run}/php-fpm.pid
error_log = {run}/php-fpm-error.log
daemonize = no
[homya-local]
user = {user}
group = {group}
listen = 127.0.0.1:19085
listen.allowed_clients = 127.0.0.1
pm = ondemand
pm.max_children = 2
pm.process_idle_timeout = 15s
pm.max_requests = 100
clear_env = yes
security.limit_extensions = .php
php_admin_value[memory_limit] = 128M
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_log] = {run}/php-error.log
'''
    write(run / 'httpd.conf', apache)
    write(run / 'php-fpm.conf', fpm)


def run_checked(args, log):
    result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    write(log, result.stdout.decode('utf-8', errors='replace'))
    if result.returncode:
        raise RuntimeError('COMMAND_FAILED')


def install():
    section = REGISTRY.read_text().split('#### DECISION-046', 1)[1].split('\n#### ', 1)[0]
    if 'VALIDÉ' not in section or 'copie intégrale' not in section or '| Clôture' in section:
        raise RuntimeError('INSTALL_AUTHORITY_REQUIRED')
    if SOURCE.is_symlink() or TARGET.is_symlink() or not TARGET.is_dir() or SOURCE == TARGET:
        raise RuntimeError('PATH_INVALID')
    if not (SOURCE / '.htaccess').is_file() or (SOURCE / 'bootstrap/cache/config.php').exists():
        raise RuntimeError('UNSAFE_PROJECT')
    parent = XAMPP / 'homya-local'
    if parent.exists() and (parent.is_symlink() or parent.stat().st_uid != os.getuid() or stat.S_IMODE(parent.stat().st_mode) != 0o700):
        raise RuntimeError('PRIVATE_PARENT_INVALID')
    parent.mkdir(mode=0o700, exist_ok=True)
    require_private(parent, True)
    run = parent / (datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + str(uuid.uuid4()))
    run.mkdir(mode=0o700)
    # No service is started or existing port owner stopped by this installer.
    for port in [8080, 19085]:
        with socket.socket() as check:
            check.bind(('127.0.0.1', port))
    configs(run)
    run_checked([HTTPD, '-t', '-f', str(run / 'httpd.conf')], run / 'apache-syntax.log')
    run_checked([PHP_FPM, '-t', '-y', str(run / 'php-fpm.conf')], run / 'fpm-syntax.log')
    emit('HASH_SOURCE', run=str(run))
    before = manifest(SOURCE)
    size = sum(item.get('bytes', 0) for item in before.values())
    if shutil.disk_usage(XAMPP).free < size * 1.2:
        raise RuntimeError('INSUFFICIENT_SPACE')
    write(run / 'source-manifest.json', before)
    stage = run / 'HOMYA-current'
    emit('COPY_COMPLETE_PROJECT', files=sum(v['type'] == 'file' for v in before.values()), bytes=size)
    # APFS clone where supported; preserves metadata and relative symlinks.
    run_checked(['/bin/cp', '-cRp', str(SOURCE), str(stage)], run / 'copy.log')
    groups_restored = restore_source_groups(stage, before)
    write(run / 'group-restoration.json', {'files_adjusted': groups_restored, 'source_changed': False})
    emit('VERIFY_COPY_AND_SOURCE')
    if manifest(stage) != before or manifest(SOURCE) != before:
        raise RuntimeError('COPY_OR_SOURCE_DIVERGED')
    for folder in ['storage/app/private/database-access',
                   'storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307',
                   'storage/app/private/catalogue-profiles']:
        require_private(stage / folder, True)
    require_private(stage / '.env')
    require_private(stage / 'storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_local_reader')
    profile = stage / 'storage/app/private/catalogue-profiles/48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809.json'
    # Profile stays immutable0400; inspect ownership/link and ACL separately.
    if profile.is_symlink() or profile.stat().st_uid != os.getuid() or profile.stat().st_nlink != 1 or stat.S_IMODE(profile.stat().st_mode) != 0o400:
        raise RuntimeError('PROFILE_METADATA_INVALID')
    acl = subprocess.run(['/bin/ls', '-lde', str(profile)], capture_output=True, check=True).stdout
    import re
    if re.search(rb'^\s*\d+:', acl, re.M) or b'+' in acl.split(maxsplit=1)[0]:
        raise RuntimeError('PROFILE_ACL_INVALID')
    identity = TARGET.stat()
    archived = run / 'HOMYA-previous'
    write(run / 'installation-prepared.json', {'target': str(TARGET), 'archive': str(archived),
          'previous_inode': identity.st_ino, 'previous_device': identity.st_dev, 'copy_verified': True})
    # Same-filesystem directory rename preserves all original content, not a merge.
    TARGET.rename(archived)
    if archived.stat().st_ino != identity.st_ino or archived.stat().st_dev != identity.st_dev:
        raise RuntimeError('PREVIOUS_IDENTITY_CHANGED')
    try:
        stage.rename(TARGET)
    except Exception:
        # Only put this operation's original directory back into an absent target.
        # Never overwrite a concurrent replacement or delete a partially copied tree.
        if not TARGET.exists() and not TARGET.is_symlink() and archived.stat().st_ino == identity.st_ino:
            archived.rename(TARGET)
            write(run / 'installation-return.json', {'previous_directory_returned': True})
        raise
    result = {'status': 'FULL_COPY_INSTALLED_VERIFIED_SERVERS_NOT_STARTED',
              'source': str(SOURCE), 'target': str(TARGET), 'previous': str(archived), 'run': str(run),
              'files': sum(v['type'] == 'file' for v in before.values()), 'bytes': size,
              'all_content_types_modes_owners_verified': True, 'source_preserved': True,
              'previous_directory_identity_preserved': True, 'global_xampp_changed': False,
              'private_access_modes_owners_links_acl_verified': True,
              'all_extended_attributes_or_hardlink_topology_certified': False,
              'sql_executed': False, 'url': 'http://127.0.0.1:8080/fr'}
    write(run / 'installation.json', result)
    emit('INSTALLED', **result)


def main(argv=None):
    parser = argparse.ArgumentParser()
    parser.add_argument('--install', action='store_true')
    args = parser.parse_args(argv)
    if not args.install:
        emit('PREPARED_NO_MUTATION_NO_SQL')
        return 0
    os.umask(0o077)
    try:
        install()
        return 0
    except Exception:
        emit('STOP_INSPECT_PRESERVE_FILES_NO_RETRY', sql_executed=False)
        return 1


if __name__ == '__main__': raise SystemExit(main())
