"""Proposed OP-05 recovery. Read-only inspection by default; no automatic retry."""
import argparse
import copy
import datetime
import fcntl
import json
import os
from pathlib import Path
import stat
import subprocess

import op05_xampp_admin as original

ROOT = original.ROOT
QA = original.QA
PLAN = ROOT / 'docs/OP-05-REPRISE-CIBLEE.md'
PARENTS = ('archive_lots', 'archive_categories')
ARTIFACTS = (*original.ARTIFACTS, 'scripts/op05_resume.py', 'scripts/op05_resume.php',
             'output/qa-import/op05-etat-partiel-fige.json', 'output/qa-import/op05-reprise-ddl.sql')


def validate_receipt(path):
    path = Path(path)
    original.require(path.is_absolute() and path.parent == QA and not path.is_symlink(), 'RECEIPT_PATH')
    meta = path.stat()
    original.require(stat.S_IMODE(meta.st_mode) == 0o600 and meta.st_uid == os.getuid(), 'RECEIPT_MODE')
    receipt = json.loads(path.read_text(), object_pairs_hook=original.unique_object)
    original.require(set(receipt) == {'operation', 'schema', 'decision', 'execution_authorized', 'plan_sha256', 'artifacts'}, 'RECEIPT_FIELDS')
    original.require(receipt['operation'] == 'OP-05-REPRISE' and receipt['schema'] == 'homya_test'
                     and receipt['execution_authorized'] is True
                     and original.re.fullmatch(r'DECISION-[0-9]{3,}', receipt['decision']), 'RECEIPT_SCOPE')
    original.require(receipt['plan_sha256'] == original.digest(PLAN), 'PLAN_CHANGED')
    original.require(set(receipt['artifacts']) == set(ARTIFACTS), 'ARTIFACT_LIST')
    for name in ARTIFACTS:
        original.require(not (ROOT / name).is_symlink() and original.digest(ROOT / name) == receipt['artifacts'][name], 'ARTIFACT_CHANGED')
    return receipt


def validate_metadata(data, references, final=False):
    original.require(references in (0, 1, 2), 'REFERENCE_STAGE')
    normalized = copy.deepcopy(data)
    reference_rows = [p for p in normalized['privileges'] if p['privilege'] == 'REFERENCES']
    expected = [{'grantee': "'homya_test_schema'@'localhost'", 'schema': 'homya_test', 'table': t,
                 'privilege': 'REFERENCES', 'grantable': 'NO'} for t in sorted(PARENTS[:references])]
    original.require(reference_rows == expected, 'REFERENCES_DRIFT')
    normalized['privileges'] = [p for p in normalized['privileges'] if p['privilege'] != 'REFERENCES']
    for row in normalized['table_records']:
        values = row['privileges'].split(',')
        target = row['user'] == 'homya_test_schema' and row['schema'] == 'homya_test' and row['table'] in PARENTS[:references]
        original.require(('References' in values) == target, 'PHYSICAL_REFERENCES_DRIFT')
        row['privileges'] = ','.join(p for p in values if p != 'References')
    if not final:
        expected_tables = [{'schema': 'homya_test', 'table': t, 'type': 'BASE TABLE', 'engine': 'InnoDB',
                            'collation': 'utf8mb4_bin'} for t in sorted((*PARENTS, 'migrations'))]
        original.require(data['tables'] == expected_tables, 'PARTIAL_OBJECTS_DRIFT')
        normalized['tables'] = [p for p in normalized['tables'] if p['table'] == 'migrations']
    original.validate(normalized, 'final' if final else 'granted')


def inspect(references, final=False):
    data = original.inspect()
    validate_metadata(data, references, final)
    frozen = json.loads((QA / 'op05-etat-partiel-fige.json').read_text())
    names = original.TABLES if final else PARENTS
    counts = {t: int(original.sql('SELECT COUNT(*) FROM homya_test.' + t).strip()) for t in names}
    original.require(all(v == 0 for v in counts.values()), 'ARCHIVE_NOT_EMPTY')
    journal = [json.loads(line) for line in original.sql("SELECT JSON_OBJECT('id',id,'migration',migration,'batch',batch) FROM homya_test.migrations ORDER BY id").splitlines()]
    ddl = {t: original.sql('SHOW CREATE TABLE homya_test.' + t).strip() for t in (*names, 'migrations')}
    if final:
        original.journal_check('final')
    else:
        original.require(journal == frozen['journal'] and ddl == frozen['physical_ddl'], 'PARTIAL_STATE_CHANGED')
    return {'metadata': data, 'archive_counts': counts, 'journal': journal, 'physical_ddl': ddl}


def execute(receipt, access, writer=original.sql, checker=inspect, process=original.migration_process):
    validate_receipt(receipt)
    original.require(isinstance(access, str) and access.startswith(str(ROOT / 'storage/app/private/database-access') + '/')
                     and Path(access).name == '.env.homya_test_schema', 'ACCESS_SCOPE')
    checker(0)
    for i, table in enumerate(PARENTS):
        validate_receipt(receipt)
        checker(i)
        writer("GRANT REFERENCES ON homya_test." + table + " TO 'homya_test_schema'@'localhost'")
        checker(i + 1)
    validate_receipt(receipt)
    process(['/opt/homebrew/bin/php', str(ROOT / 'scripts/op05_resume.php'), '--execute',
             '--receipt=' + receipt, '--access=' + access])
    return checker(2, True)


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--inspect', choices=('0', '1', '2', 'final'), default='0')
    p.add_argument('--execute', action='store_true')
    p.add_argument('--validate-receipt', action='store_true')
    p.add_argument('--receipt')
    p.add_argument('--access')
    p.add_argument('--report')
    args = p.parse_args()
    try:
        if args.validate_receipt:
            original.require(args.receipt and not args.execute and not args.report and not args.access, 'RECEIPT_SCOPE')
            validate_receipt(args.receipt)
            print(json.dumps({'status': 'RECEIPT_VALIDATED_NO_SQL'}))
            return 0
        original.require(args.report is not None, 'REPORT_REQUIRED')
        report = Path(args.report)
        original.require(report.is_absolute() and report.parent == QA and not report.exists() and not report.is_symlink(), 'REPORT_PATH')
        if args.execute:
            original.require(args.inspect == '0' and args.receipt and args.access, 'EXECUTION_SCOPE')
            validate_receipt(args.receipt)
            path = QA / '.op05.lock'
            original.require(not path.is_symlink(), 'LOCK_PATH')
            fd = os.open(path, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
            with os.fdopen(fd, 'r+') as lock:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
                data = execute(args.receipt, args.access)
        else:
            original.require(not args.receipt and not args.access, 'INSPECTION_SCOPE')
            data = inspect(2 if args.inspect == 'final' else int(args.inspect), args.inspect == 'final')
        proof = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                 'operation': 'OP-05-REPRISE', 'status': 'CHECKS_PASSED_NEEDS_PHYSICAL_DDL_REVIEW',
                 'sql_write_executed': args.execute, 'automatic_retry': False, 'result': data}
        with report.open('x') as f:
            json.dump(proof, f, ensure_ascii=False, indent=2)
            f.write('\n')
        print(json.dumps({'status': proof['status'], 'sql_write_executed': args.execute}))
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
        code = str(error) if isinstance(error, RuntimeError) else 'IO_OR_FORMAT_ERROR'
        print(json.dumps({'status': 'STOP_NO_RETRY_OR_CLEANUP', 'code': code}))
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
