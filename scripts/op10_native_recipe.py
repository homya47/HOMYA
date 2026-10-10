"""OP-10 native read-only checks. Default/check-receipt never connects or loads credentials.

No authority issuer, schema change, import, backup, switch, service control or cleanup.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys

sys.dont_write_bytecode = True
from op07_dump import tokens

ROOT = Path('/Users/akm/Documents/ChatGPT/HOMYA')
QA = ROOT/'output/qa-import'
PLAN = ROOT/'docs/OP-10-RECETTE-NATIVE-LECTURE-SEULE.md'
PROGRAM = QA/'op10-recette-native-reprise-programme.json'
AGREEMENT = QA/'op10-recette-native-reprise-enregistrement-accord.json'
REGISTRY = Path('/Users/akm/Codex-Workspace/specification/references/DECISION-AND-CHANGE-LOG.md')
MYSQL = '/Applications/XAMPP/xamppfiles/bin/mysql'
PHP = '/opt/homebrew/bin/php'
SOCKET = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock'
EXPECTED_TOOLS = {MYSQL, PHP, '/usr/bin/python3'}
LOT = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809'


def require(condition, code):
    if not condition:
        raise RuntimeError(code)


def digest(path):
    h = hashlib.sha256()
    with Path(path).open('rb') as stream:
        for chunk in iter(lambda: stream.read(1048576), b''):
            h.update(chunk)
    return h.hexdigest()


def unique(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, 'JSON_DUPLICATE_KEY')
        result[key] = value
    return result


def private_json(path):
    path = Path(path)
    require(path.is_absolute() and path.parent == QA and path.resolve() == path, 'PRIVATE_PATH')
    s = path.lstat()
    require(stat.S_ISREG(s.st_mode) and stat.S_IMODE(s.st_mode) == 0o600 and s.st_uid == os.getuid()
            and s.st_nlink == 1 and s.st_size <= 262144, 'PRIVATE_FILE')
    # No ACL permission grant may extend the restrictive mode on macOS.
    acl = subprocess.run(['/bin/ls', '-lde', str(path)], capture_output=True, text=True, check=True)
    require(not re.search(r'^\s*\d+:', acl.stdout, re.M), 'PRIVATE_ACL')
    with os.fdopen(os.open(path, os.O_RDONLY | os.O_NOFOLLOW), 'r') as stream:
        before = os.fstat(stream.fileno())
        require((before.st_dev, before.st_ino) == (s.st_dev, s.st_ino), 'PRIVATE_CHANGED')
        data = json.load(stream, object_pairs_hook=unique)
    after = path.lstat()
    fields = ('st_dev', 'st_ino', 'st_mode', 'st_uid', 'st_nlink', 'st_size', 'st_mtime_ns', 'st_ctime_ns')
    require(all(getattr(s, k) == getattr(after, k) for k in fields), 'PRIVATE_CHANGED')
    return data


def validate_query(query):
    require(isinstance(query, str) and (query.startswith('SELECT ') or query.startswith('SHOW CREATE TABLE ')), 'READONLY_QUERY_REQUIRED')
    parsed = tokens(query)
    require(not any((kind == 'sym' and value == ';') or (kind == 'word' and value in ('INTO', 'OUTFILE', 'DUMPFILE', 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK', 'LOAD_FILE', 'FOR', 'LOCK'))
                    for kind, value in parsed), 'READONLY_QUERY_REQUIRED')
    return query


def validate_receipt(path):
    receipt = private_json(Path(path))
    require(set(receipt) == {'operation', 'decision', 'native_read_authorized', 'activation_authorized', 'agreement_sha256', 'programme_sha256'}, 'RECEIPT_FIELDS')
    require(receipt['operation'] == 'OP-10-READONLY' and receipt['native_read_authorized'] is True
            and receipt['activation_authorized'] is False and re.fullmatch(r'DECISION-[0-9]{3,}', receipt['decision'])
            and int(receipt['decision'].split('-')[1]) > 43, 'DISTINCT_NATIVE_AGREEMENT_REQUIRED')
    agreement = private_json(AGREEMENT)
    require(digest(AGREEMENT) == receipt['agreement_sha256'] and agreement.get('operation') == receipt['operation']
            and agreement.get('decision') == receipt['decision'] and agreement.get('native_read_authorized') is True
            and agreement.get('activation_authorized') is False and agreement.get('registry_readback_verified') is True
            and digest(REGISTRY) == agreement.get('registry_after_sha256'), 'NATIVE_AGREEMENT_REQUIRED')
    require(digest(PROGRAM) == receipt['programme_sha256'] == agreement.get('programme_sha256'), 'APPROVED_PROGRAM_REQUIRED')
    program = private_json(PROGRAM)
    require(program['operation'] == 'OP-10-READONLY' and program['native_read_authorized'] is False
            and program['activation_authorized'] is False and program['source_active'] == 'files'
            and program['lot_key'] == LOT and program['reader'] == 'homya_local_reader'
            and program['schema'] == 'homya_local' and program['plan_sha256'] == digest(PLAN) == agreement.get('plan_sha256'), 'PROGRAM_SCOPE')
    require(set(program['tools']) == EXPECTED_TOOLS, 'TOOL_LIST')
    for p, h in program['tools'].items():
        require(digest(p) == h, 'TOOL_CHANGED')
    for name, h in program['artifacts'].items():
        p = ROOT/name
        require(not Path(name).is_absolute() and p.resolve() == p and digest(p) == h, 'ARTIFACT_CHANGED')
    for p, h in program['preservation'].items():
        require(Path(p).is_absolute() and Path(p).resolve() == Path(p) and digest(p) == h, 'PROTECTED_FILE_CHANGED')
    require(not (ROOT/'bootstrap/cache/config.php').exists(), 'CONFIGURATION_CACHE_PRESENT')
    sections = REGISTRY.read_text().split('#### '+receipt['decision']+'\n')
    require(len(sections) == 2, 'REGISTRY_ENTRY_REQUIRED')
    entry = sections[1].split('#### ')[0]
    require('HOMYA' in entry and 'OP-10-READONLY' in entry and 'VALIDÉ' in entry and LOT in entry
            and 'homya_local_reader' in entry and 'BASCULE EXCLUE' in entry
            and program['plan_sha256'] in entry and receipt['programme_sha256'] in entry, 'REGISTRY_NATIVE_SCOPE')
    for item in program['admin_queries']:
        validate_query(item['sql'])
    return program


def parse_server_response(text):
    """Strict native tabular result; no dependency on SQL JSON boolean serialization."""
    fields = text.split('\t')
    require('\n' not in text and '\r' not in text and len(fields) == 10, 'NATIVE_SERVER_ROW_INVALID')
    names = ('version', 'identity', 'port', 'socket', 'datadir', 'bind', 'events', 'packet', 'general_log', 'slow_log')
    server = dict(zip(names, fields))
    for name in ('port', 'packet', 'general_log', 'slow_log'):
        require(bool(re.fullmatch(r'[0-9]{1,12}', server[name])), 'NATIVE_SERVER_ROW_INVALID')
        server[name] = int(server[name])
    wanted = {'version': '10.4.28-MariaDB', 'identity': 'root@localhost', 'port': 3307,
              'socket': SOCKET, 'datadir': '/Applications/XAMPP/xamppfiles/var/mysql/',
              'bind': '127.0.0.1', 'events': 'OFF', 'general_log': 0, 'slow_log': 0}
    require(all(server.get(k) == v for k, v in wanted.items()) and server['packet'] >= 16777216, 'XAMPP_SERVER_TARGET')
    return server


def audit(receipt):
    program = validate_receipt(receipt)
    records = []
    for item in program['admin_queries']:
        validate_receipt(receipt)
        result = subprocess.run([MYSQL, '--no-defaults', '--protocol=SOCKET', '--socket='+SOCKET,
                                 '--user=root', '--skip-password', '--batch', '--raw', '--skip-column-names', '--connect-timeout=5'],
                                input=item['sql']+';\n', capture_output=True, text=True, timeout=20,
                                env={'PATH': '/usr/bin:/bin', 'LC_ALL': 'C', 'HOME': str(Path.home())})
        require(result.returncode == 0 and len(result.stdout.encode()) <= 2097152, 'NATIVE_AUDIT_FAILED_DETAILS_WITHHELD')
        text = result.stdout.rstrip('\n')
        if item['id'] == 'server':
            parse_server_response(text)
        if 'expected' in item:
            require(text == item['expected'], 'NATIVE_REFERENCE_DIVERGENT')
        records.append({'id': item['id'], 'sha256': hashlib.sha256(text.encode()).hexdigest(), 'lines': len(text.splitlines())})
    return {'status': 'NATIVE_READONLY_AUDIT_COMPLETED', 'records': records, 'activation': False}


def main(arguments=None):
    parser = argparse.ArgumentParser()
    parser.add_argument('--check-receipt', action='store_true')
    parser.add_argument('--audit', action='store_true')
    parser.add_argument('--receipt')
    args = parser.parse_args(arguments)
    if not any((args.check_receipt, args.audit, args.receipt)):
        print(json.dumps({'status': 'OP10_NATIVE_TOOLS_PREPARED_NO_SQL', 'native_read_authorized': False, 'activation_authorized': False}))
        return 0
    try:
        require(args.receipt and args.check_receipt != args.audit, 'EXPLICIT_MODE_AND_RECEIPT_REQUIRED')
        result = audit(args.receipt) if args.audit else (validate_receipt(args.receipt) and {'status': 'NATIVE_RECEIPT_VERIFIED_NO_SQL'})
        print(json.dumps(result))
        return 0
    except Exception:
        print(json.dumps({'status': 'STOP_INSPECT_NO_RETRY', 'details_withheld': True, 'activation': False}))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
