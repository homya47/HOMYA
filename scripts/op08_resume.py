"""OP-08 targeted resumption. Offline by default; separate future approval required.

Initial tools, receipt and run stay unchanged. No journal creation or first GRANT replay.
"""
import argparse
from contextlib import contextmanager
import json
import os
from pathlib import Path
import re
import time

import op08_xampp_admin as initial

ROOT, QA = initial.ROOT, initial.QA
PLAN = ROOT/'docs/OP-08-REPRISE-CIBLEE.md'
AGREEMENT = QA/'op08-reprise-enregistrement-accord.json'
STOP = QA/'op08-arret-controle-independant.json'
PREVIOUS = QA/'op08-run-20261008T144507Z-1c6cacec-2c0b-43bd-a1f2-2fa352e27410'
GRANTS = tuple(q.replace('GRANT REFERENCES ', 'GRANT CREATE, REFERENCES ') for q in initial.GRANTS[1:])
ARTIFACTS = initial.ARTIFACTS + ('scripts/op08_resume.py', 'scripts/op08_resume.php', 'scripts/test_op08_resume.py',
    'docs/OP-08-SCHEMA-LOCAL-PREPARATION.md', 'output/qa-import/op08-arret-controle-independant.json', 'output/qa-import/op08-plan-valide-v02.md',
    'output/qa-import/op08-initial-execution-accord-038.json', 'output/qa-import/op08-initial-execution-recu-038.json') + tuple(
    str((PREVIOUS/p).relative_to(ROOT)) for p in ('before.json', 'journal.json', 'grant-1.json', 'receipt.json',
                                                'failure.json', 'runtime-before.json', 'protected-before.json', 'media-before.json'))
require, digest, save = initial.require, initial.digest, initial.save


def validate_receipt(path):
    r = initial.prior.safe_json(path, QA)
    require(set(r) == {'operation', 'schema', 'decision', 'resume_from', 'execution_authorized',
                      'agreement_sha256', 'plan_sha256', 'artifacts', 'tools'}, 'RECEIPT_FIELDS')
    require(r['operation'] == 'OP-08' and r['schema'] == 'homya_local' and r['resume_from'] == 'DECISION-038'
            and r['execution_authorized'] is True and re.fullmatch(r'DECISION-[0-9]{3,}', r['decision'])
            and r['decision'] not in ('DECISION-037', 'DECISION-038'), 'NEW_RESUMPTION_AGREEMENT_REQUIRED')
    require(digest(PLAN) == r['plan_sha256'] and set(r['artifacts']) == set(ARTIFACTS), 'PLAN_OR_ARTIFACT_LIST')
    for name, h in r['artifacts'].items():
        p = ROOT/name
        require(p.resolve() == p and digest(p) == h, 'ARTIFACT_CHANGED')
    require(set(r['tools']) == {initial.MYSQL, initial.PHP}
            and all(digest(p) == h for p, h in r['tools'].items()), 'TOOL_CHANGED')
    a = initial.prior.safe_json(AGREEMENT, QA)
    require(digest(AGREEMENT) == r['agreement_sha256'] and a.get('operation') == 'OP-08'
            and a.get('decision') == r['decision'] and a.get('resume_from') == 'DECISION-038'
            and a.get('execution_authorized') is True and a.get('sql_authorized') is True
            and a.get('registry_readback_verified') is True
            and digest(initial.prior.REGISTRY) == a.get('registry_after_sha256'), 'RESUMPTION_AGREEMENT_REQUIRED')
    entry = initial.prior.REGISTRY.read_text().split('#### '+r['decision']+'\n')
    require(len(entry) == 2 and re.search(r'\| Projet / périmètre \| HOMYA[^\n]*OP-08[^\n]*reprise', entry[1].split('#### ')[0])
            and 'VALIDÉ' in entry[1].split('#### ')[0], 'REGISTRY_RESUMPTION_SCOPE')
    return r


@contextmanager
def operation_guard():
    """Reuse receipt-gated native queries/audits and the same operation lock, in one CLI process."""
    previous_guard, previous_workflow = initial.validate_receipt, initial.workflow
    try:
        initial.validate_receipt, initial.workflow = validate_receipt, workflow
        yield
    finally:
        initial.validate_receipt, initial.workflow = previous_guard, previous_workflow


def stopped_state():
    state = json.loads(STOP.read_text(), object_pairs_hook=initial.base.unique_object)
    require(state.get('decision') == 'DECISION-038' and state.get('status') == 'PARTIAL_JOURNAL_ONLY_VERIFIED_NO_RETRY'
            and state.get('local_table_names') == ['migrations'] and state.get('local_journal_rows') == 0
            and state.get('grant_success_count') == 1 and state.get('archive_tables_created') == 0
            and state.get('migration_executed') is False and state.get('import_executed') is False
            and state.get('activated') is False and state.get('test_restore_rows_unchanged') is True
            and state.get('runtime_witnesses_unchanged') is True, 'STOP_STATE_REQUIRED')
    return state


def workflow(receipt, run, deadline):
    validate_receipt(receipt)
    stopped = stopped_state()
    runtime = initial.prior.runtime_witnesses()
    require(runtime == json.loads((PREVIOUS/'runtime-before.json').read_text()), 'HISTORICAL_RUNTIME_CHANGED')
    require(not (ROOT/'bootstrap/cache/config.php').exists() and not list((ROOT/'database/schema').glob('*')), 'CACHED_CONFIG_OR_SCHEMA_DUMP')
    access = initial.access_files()
    save(run/'before.json', initial.inspect(1, 'journal', receipt))
    preserved = initial.preservation(receipt)
    require(preserved == stopped['protected_after'], 'PROTECTED_DATA_CHANGED_SINCE_STOP')
    save(run/'protected-before.json', preserved)
    media = initial.prior.verify_media(deadline)
    require(media == stopped['media'], 'CAPSULE_CHANGED_SINCE_STOP')
    save(run/'media-before.json', media); save(run/'runtime-before.json', runtime)
    require(os.statvfs(run).f_bavail*os.statvfs(run).f_frsize >= 256*1024*1024, 'SPACE_RESERVE')
    helper = str(ROOT/'scripts/op08_resume.php')
    initial.php_call([helper, '--probe', '--journal', '--role=schema', '--receipt='+str(receipt),
                      '--access='+access['schema']], receipt, deadline)
    # Same status as a successful role read, with exact empty journal verified by the helper.
    for i in range(1, 3):
        initial.inspect(i, 'journal', receipt)
        require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
        initial.sql(GRANTS[i-1], receipt)
        save(run/('grant-'+str(i+1)+'.json'), initial.inspect(i+1, 'journal', receipt))
    frozen = json.loads((QA/'op04-complement-schema-final.json').read_text())
    initial.inspect(3, 'journal', receipt)
    initial.php_call([str(ROOT/'artisan'), 'homya:catalogue-schema', '--execute', '--operation=OP-08',
                      '--connection=homya_local_schema', '--access='+access['schema'],
                      '--expected-ddl='+frozen['sha256'], '--expected-files='+frozen['files_sha256']],
                     receipt, deadline, migration=True)
    save(run/'migrated.json', initial.inspect(3, 'final', receipt))
    for i in range(3, 12):
        initial.inspect(i, 'final', receipt)
        require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
        initial.sql(GRANTS[i-1], receipt)
        save(run/('grant-'+str(i+1)+'.json'), initial.inspect(i+1, 'final', receipt))
    for role in ('schema', 'reader', 'import'):
        initial.php_call([helper, '--probe', '--role='+role, '--receipt='+str(receipt), '--access='+access[role]], receipt, deadline)
    save(run/'final.json', initial.inspect(12, 'final', receipt))
    after = initial.preservation(receipt); save(run/'protected-after.json', after)
    require(after == preserved and initial.prior.runtime_witnesses() == runtime
            and initial.prior.verify_media(deadline) == media, 'PRESERVATION_DRIFT')
    save(run/'runtime-after.json', runtime)
    validate_receipt(receipt); require(time.monotonic() < deadline, 'OPERATION_DEADLINE')
    save(run/'status.json', {'status': 'LOCAL_SCHEMA_VERIFIED_EMPTY_NO_IMPORT_NO_ACTIVATION',
                           'resume_from': 'DECISION-038', 'grant_count': 11, 'journal_recreated': False,
                           'import_executed': False, 'activated': False})


def prepare():
    stopped_state()
    return dict(operation='OP-08', resume_from='DECISION-038', status='RESUMPTION_PREPARED_NEW_PERMISSION_REQUIRED',
                execution_authorized=False, sql_executed=False, schema='homya_local', plan_sha256=digest(PLAN),
                artifacts={name:digest(ROOT/name) for name in ARTIFACTS},
                tools={p:digest(p) for p in (initial.MYSQL, initial.PHP)}, grants=GRANTS,
                new_tables=initial.base.TABLES, existing_journal_kept=True, first_grant_replayed=False,
                max_seconds=initial.MAX_SECONDS, imports=False, activated=False)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--execute', action='store_true'); parser.add_argument('--validate-receipt', action='store_true'); parser.add_argument('--receipt')
    args = parser.parse_args()
    try:
        require(not (args.execute and args.validate_receipt), 'ARGUMENT_SCOPE')
        if args.execute or args.validate_receipt:
            require(args.receipt is not None, 'NEW_RESUMPTION_RECEIPT_REQUIRED')
            validate_receipt(Path(args.receipt))
            if args.validate_receipt:
                print(json.dumps({'status':'RECEIPT_VALID_NO_SQL'}))
            else:
                with operation_guard(): run = initial.execute(args.receipt)
                print(json.dumps({'status':'OP08_RESUMED_NOT_ACTIVATED', 'run':str(run)}))
        else:
            require(args.receipt is None, 'OFFLINE_ACCESS_FORBIDDEN')
            print(json.dumps(prepare(), indent=2))
        return 0
    except Exception as error:
        code = str(error) if isinstance(error, RuntimeError) and re.fullmatch('[A-Z0-9_]+', str(error)) else 'DETAILS_WITHHELD'
        print(json.dumps({'status':'STOP_INSPECT_NO_RETRY', 'code':code, 'cleanup':False})); return 1


if __name__ == '__main__':
    raise SystemExit(main())
