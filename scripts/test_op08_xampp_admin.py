"""OP-08 boundary tests. All processes/SQL are doubles, except offline refusal CLIs."""
import copy
from contextlib import ExitStack
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import unittest
from unittest.mock import patch, Mock

import op08_xampp_admin as op


class Op08Tests(unittest.TestCase):
    def test_rights_sequence_is_table_scoped_and_exact(self):
        self.assertEqual(12, len(op.GRANTS))
        self.assertEqual(('schema', 'archive_lots', ('REFERENCES',), ()), op.SPECS[1])
        self.assertEqual(('schema', 'archive_categories', ('REFERENCES',), ()), op.SPECS[2])
        for q in op.GRANTS:
            self.assertIn(' ON `homya_local`.`', q)
            self.assertNotIn('*', q)
            self.assertNotIn('GRANT OPTION', q)
        self.assertIn('UPDATE (load_state, verification_document, verified_at)', op.GRANTS[-1])

    def test_all_metadata_stages_and_final_counts(self):
        stages = [(0, 'empty')] + [(i, 'journal') for i in range(4)] + [(i, 'final') for i in range(3, 13)]
        for stage, phase in stages:
            with self.subTest(stage=stage, phase=phase):
                op.validate_metadata(op.expected_metadata(stage, phase), stage, phase)
        final = op.expected_metadata(12, 'final')
        self.assertEqual(52, len(final['privileges']))
        self.assertEqual(6, len(final['column_privileges']))
        local = [x for x in final['privileges'] if x['schema'] == 'homya_local']
        self.assertEqual(26, len(local))

    def test_wrong_stage_and_target_and_extra_rights_are_refused(self):
        for stage, phase in [(1, 'empty'), (4, 'journal'), (2, 'final'), (13, 'final')]:
            with self.assertRaises(RuntimeError):
                op.expected_metadata(stage, phase)
        mutators = [lambda d: d['server'].update(port=3306),
                    lambda d: d['server'].update(socket='/tmp/other.sock'),
                    lambda d: d['unexpected_rights'].update(global_delegation=1),
                    lambda d: d['unexpected_rights'].update(roles=1),
                    lambda d: d['privileges'][0].update(grantable='YES'),
                    lambda d: d['privileges'][0].update(privilege='DELETE'),
                    lambda d: d['column_privileges'][0].update(column='manifest_document'),
                    lambda d: d['accounts'][0].update(host='%'),
                    lambda d: d['tables'].append({'table': 'users'}),
                    lambda d: d['other_objects'].update(triggers=1)]
        for mutate in mutators:
            data = op.expected_metadata(12, 'final'); mutate(data)
            with self.assertRaises(RuntimeError):
                op.validate_metadata(data, 12, 'final')

    def receipt_fixture(self, stack, directory):
        root = Path(directory).resolve(); qa = root/'qa'; qa.mkdir()
        plan = root/'plan.md'; plan.write_text('reviewed plan')
        code = root/'tool.py'; code.write_text('reviewed code')
        mysql = root/'mysql'; mysql.write_text('native mysql double')
        php = root/'php'; php.write_text('native php double')
        reg = root/'registry.md'; reg.write_text('#### DECISION-999\n| Projet / périmètre | HOMYA OP-08 |\n| Statut | VALIDÉ |\n')
        agreement = qa/'agreement.json'
        a = dict(operation='OP-08', decision='DECISION-999', sql_authorized=True, execution_authorized=True,
                 registry_readback_verified=True, registry_after_sha256=op.digest(reg))
        agreement.write_text(json.dumps(a)); agreement.chmod(0o600)
        receipt = qa/'receipt.json'
        r = dict(operation='OP-08', schema='homya_local', decision='DECISION-999', execution_authorized=True,
                 agreement_sha256=op.digest(agreement), plan_sha256=op.digest(plan),
                 artifacts={'tool.py': op.digest(code)}, tools={str(mysql): op.digest(mysql), str(php): op.digest(php)})
        receipt.write_text(json.dumps(r)); receipt.chmod(0o600)
        for name, value in [('ROOT', root), ('QA', qa), ('PLAN', plan), ('AGREEMENT', agreement),
                            ('ARTIFACTS', ('tool.py',)), ('MYSQL', str(mysql)), ('PHP', str(php))]:
            stack.enter_context(patch.object(op, name, value))
        stack.enter_context(patch.object(op.prior, 'REGISTRY', reg))
        return receipt, r, a

    def test_receipt_is_bound_to_execution_agreement_and_every_witness(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            receipt, r, a = self.receipt_fixture(stack, directory)
            self.assertEqual(r, op.validate_receipt(receipt))
            for key, bad in [('schema', 'homya_test'), ('decision', 'DECISION-037'),
                             ('operation', 'OP-07'), ('execution_authorized', False),
                             ('plan_sha256', '0'*64), ('artifacts', {}), ('tools', {})]:
                broken = dict(r); broken[key] = bad; receipt.write_text(json.dumps(broken))
                with self.assertRaises(RuntimeError): op.validate_receipt(receipt)
            receipt.write_text(json.dumps(r))
            a['sql_authorized'] = False; op.AGREEMENT.write_text(json.dumps(a))
            r['agreement_sha256'] = op.digest(op.AGREEMENT); receipt.write_text(json.dumps(r))
            with self.assertRaisesRegex(RuntimeError, 'EXECUTION_AGREEMENT_REQUIRED'): op.validate_receipt(receipt)

    def test_receipt_file_security_and_artifact_drift(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            receipt, r, a = self.receipt_fixture(stack, directory)
            receipt.chmod(0o644)
            with self.assertRaises(RuntimeError): op.validate_receipt(receipt)
            receipt.chmod(0o600); receipt.write_text('{"operation":"OP-08","operation":"OP-08"}')
            with self.assertRaisesRegex(RuntimeError, 'DUPLICATE_JSON_KEY'): op.validate_receipt(receipt)
            receipt.write_text(json.dumps(r)); (op.ROOT/'tool.py').write_text('changed code')
            with self.assertRaisesRegex(RuntimeError, 'ARTIFACT_CHANGED'): op.validate_receipt(receipt)
            link = op.QA/'symlink.json'; link.symlink_to(receipt)
            with self.assertRaises(RuntimeError): op.validate_receipt(link)

    def test_no_native_process_before_receipt_validation(self):
        with patch.object(op, 'validate_receipt', side_effect=RuntimeError('NO_AUTH')), patch.object(op.subprocess, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, 'NO_AUTH'): op.sql('SELECT 1', 'missing')
            run.assert_not_called()

    def test_native_sql_uses_socket_stdin_and_filtered_environment(self):
        with patch.object(op, 'validate_receipt'), patch.dict(os.environ, {'MYSQL_PWD': 'synthetic-secret'}), patch.object(op.subprocess, 'run', return_value=Mock(returncode=0, stdout='1\n', stderr='')) as run:
            self.assertEqual('1\n', op.sql('SELECT 1', 'receipt'))
            args, kwargs = run.call_args
            self.assertIn('--no-defaults', args[0]); self.assertIn('--socket='+op.base.SOCKET, args[0])
            self.assertNotIn('--execute=SELECT 1', args[0]); self.assertEqual('SELECT 1;\n', kwargs['input'])
            self.assertNotIn('MYSQL_PWD', kwargs['env'])
            run.return_value = Mock(returncode=1, stdout='', stderr='ERROR 1045 synthetic-secret')
            with self.assertRaisesRegex(RuntimeError, '^XAMPP_SQL_ERROR_1045$'): op.sql('SELECT 1', 'receipt')

    def test_restore_empty_default_role_and_no_other_objects(self):
        def reply(q, receipt):
            if q.startswith('SELECT User,Host'): return op.prior.USER+'\tlocalhost\tmysql_native_password\tN\t\n'
            if q.startswith('SELECT TABLE_SCHEMA'): return '\n'.join('homya_restore_op07\t'+t+'\tSELECT\tNO' for t in sorted(op.TABLES))+'\n'
            if q.startswith('SELECT DEFAULT'): return 'utf8mb4\tutf8mb4_bin\n'
            if q.startswith('SELECT TABLE_NAME'): return '\n'.join(t+'\tBASE TABLE\tInnoDB\tutf8mb4_bin' for t in sorted(op.TABLES))+'\n'
            return '0\n'
        with patch.object(op, 'sql', side_effect=reply): op.restore_rights('receipt')
        with patch.object(op, 'sql', side_effect=lambda q,r: '1\n' if 'information_schema.TRIGGERS' in q else reply(q,r)):
            with self.assertRaisesRegex(RuntimeError, 'RESTORE_EXTRA_OBJECTS'): op.restore_rights('receipt')

    def test_row_hashes_are_framed_ordered_and_copy_drift_is_refused(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root = Path(directory); (root/'source-before.json').write_text(json.dumps({'tables': {t: {'columns': [{'name':'id'}, {'name':'document'}], 'primary_key':['id']} for t in op.TABLES}}))
            stack.enter_context(patch.object(op, 'SOURCE_RUN', root))
            stack.enter_context(patch.object(op.prior, 'EXPECTED_COUNTS', {t: 1 for t in op.TABLES}))
            run = stack.enter_context(patch.object(op, 'sql', return_value='a'*64+'\n'))
            op.preservation('receipt')
            self.assertEqual(10, run.call_count)
            for call in run.call_args_list:
                self.assertIn('SHA2(CONCAT(', call.args[0]); self.assertIn('OCTET_LENGTH', call.args[0])
                self.assertIn("THEN 'N;'", call.args[0]); self.assertIn('ORDER BY `id`', call.args[0])
            run.side_effect = lambda q,r: ('b' if 'homya_restore_op07' in q else 'a')*64+'\n'
            with self.assertRaisesRegex(RuntimeError, 'PROTECTED_COPY_DRIFT'): op.preservation('receipt')

    def workflow_fixture(self, stack, directory):
        root = Path(directory).resolve(); (root/'bootstrap/cache').mkdir(parents=True); (root/'database/schema').mkdir(parents=True)
        qa = root/'qa'; qa.mkdir(); (qa/'op04-complement-schema-final.json').write_text(json.dumps({'sha256':'ddl','files_sha256':'files'}))
        stack.enter_context(patch.object(op, 'ROOT', root)); stack.enter_context(patch.object(op, 'QA', qa))
        mocks = {}
        for name, value in [('validate_receipt', {}), ('access_files', {r:'synthetic/'+r for r in ('schema','reader','import')}),
                            ('save', None), ('inspect', {}), ('preservation', {'hash':'same'}), ('php_call', None), ('sql', '')]:
            mocks[name] = stack.enter_context(patch.object(op, name, return_value=value))
        stack.enter_context(patch.object(op.prior, 'runtime_witnesses', return_value={'runtime':'same'}))
        stack.enter_context(patch.object(op.prior, 'verify_media', return_value={'media':'same'}))
        return root, mocks

    def test_workflow_runs_once_in_order_without_import_or_default_connection(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root, mocks = self.workflow_fixture(stack, directory)
            op.workflow('receipt', root, time.monotonic()+100)
            self.assertEqual(list(op.GRANTS), [c.args[0] for c in mocks['sql'].call_args_list])
            self.assertEqual(6, mocks['php_call'].call_count)
            migration = mocks['php_call'].call_args_list[2]
            self.assertTrue(migration.kwargs['migration'])
            self.assertIn('--connection=homya_local_schema', migration.args[0])
            self.assertEqual(2, mocks['preservation'].call_count)

    def test_failures_at_each_write_boundary_never_retry_or_continue(self):
        for failed_grant in range(12):
            with self.subTest(grant=failed_grant), tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
                root, mocks = self.workflow_fixture(stack, directory)
                mocks['sql'].side_effect = ['']*failed_grant+[RuntimeError('SYNTHETIC_FAILURE')]
                with self.assertRaises(RuntimeError): op.workflow('receipt', root, time.monotonic()+100)
                self.assertEqual(failed_grant+1, mocks['sql'].call_count)
        for failed_helper in range(6):
            with self.subTest(helper=failed_helper), tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
                root, mocks = self.workflow_fixture(stack, directory)
                mocks['php_call'].side_effect = [None]*failed_helper+[RuntimeError('SYNTHETIC_FAILURE')]
                with self.assertRaises(RuntimeError): op.workflow('receipt', root, time.monotonic()+100)
                self.assertEqual(failed_helper+1, mocks['php_call'].call_count)

    def test_deadline_prevents_grants(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root, mocks = self.workflow_fixture(stack, directory)
            with self.assertRaisesRegex(RuntimeError, 'OPERATION_DEADLINE'): op.workflow('receipt', root, time.monotonic()-1)
            mocks['sql'].assert_not_called()

    def test_final_preservation_drift_refuses_success(self):
        for boundary in ('rows', 'runtime', 'media'):
            with self.subTest(boundary=boundary), tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
                root, mocks = self.workflow_fixture(stack, directory)
                if boundary == 'rows': mocks['preservation'].side_effect = [{'hash':'before'}, {'hash':'after'}]
                if boundary == 'runtime': stack.enter_context(patch.object(op.prior, 'runtime_witnesses', side_effect=[{'r':'before'}, {'r':'after'}]))
                if boundary == 'media': stack.enter_context(patch.object(op.prior, 'verify_media', side_effect=[{'m':'before'}, {'m':'after'}]))
                with self.assertRaisesRegex(RuntimeError, 'PRESERVATION_DRIFT'): op.workflow('receipt', root, time.monotonic()+100)
                self.assertFalse(any(c.args[0].name == 'status.json' for c in mocks['save'].call_args_list))

    def test_execute_fails_once_leaving_private_partial_report(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root = Path(directory).resolve()
            stack.enter_context(patch.object(op, 'QA', root))
            stack.enter_context(patch.object(op, 'validate_receipt'))
            receipt = root/'receipt.json'; receipt.write_text('{}')
            workflow = stack.enter_context(patch.object(op, 'workflow', side_effect=RuntimeError('SYNTHETIC_FAILURE')))
            with self.assertRaisesRegex(RuntimeError, 'SYNTHETIC_FAILURE'): op.execute(receipt)
            workflow.assert_called_once()
            runs = list(root.glob('op08-run-*')); self.assertEqual(1, len(runs))
            self.assertEqual(0o700, runs[0].stat().st_mode & 0o777)
            report = runs[0]/'failure.json'; self.assertEqual(0o600, report.stat().st_mode & 0o777)
            self.assertFalse(json.loads(report.read_text())['retry'])
            self.assertFalse(json.loads(report.read_text())['cleanup'])

    def test_same_operation_lock_refuses_concurrent_execution(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root = Path(directory).resolve(); lock = root/'.op08.lock'
            lock.touch(mode=0o600)
            stack.enter_context(patch.object(op, 'QA', root)); stack.enter_context(patch.object(op, 'validate_receipt'))
            workflow = stack.enter_context(patch.object(op, 'workflow'))
            with lock.open('r+') as held:
                op.fcntl.flock(held, op.fcntl.LOCK_EX | op.fcntl.LOCK_NB)
                with self.assertRaises(BlockingIOError): op.execute(root/'receipt.json')
            workflow.assert_not_called()
            self.assertEqual([], list(root.glob('op08-run-*')))

    def test_php_process_deadline_output_and_error_are_guarded(self):
        with patch.object(op, 'validate_receipt'), patch.object(op.subprocess, 'run') as run:
            with self.assertRaisesRegex(RuntimeError, 'OPERATION_DEADLINE'): op.php_call([], 'r', time.monotonic()-1)
            run.assert_not_called()
            run.return_value = Mock(returncode=0, stdout='wrong marker', stderr='')
            with self.assertRaisesRegex(RuntimeError, 'MIGRATOR_RESULT'): op.php_call(['artisan'], 'r', time.monotonic()+100, migration=True)
            self.assertEqual('yes\n', run.call_args.kwargs['input'])
            run.return_value = Mock(returncode=1, stdout='synthetic-secret', stderr='synthetic-secret')
            with self.assertRaisesRegex(RuntimeError, '^PHP_FAILED_DETAILS_WITHHELD$'): op.php_call([], 'r', time.monotonic()+100)

    def test_access_validation_does_not_read_contents(self):
        with tempfile.TemporaryDirectory() as directory, ExitStack() as stack:
            root = Path(directory).resolve(); access = root/'storage/app/private/database-access'
            access.mkdir(parents=True, mode=0o700); instance = access/'synthetic'; instance.mkdir(mode=0o700)
            for role in ('schema', 'reader', 'import'):
                file = instance/('.env.homya_local_'+role); file.write_text('synthetic fixture'); file.chmod(0o600)
            stack.enter_context(patch.object(op, 'ROOT', root)); stack.enter_context(patch.object(op, 'ACCESS_DIR', instance))
            stack.enter_context(patch.object(Path, 'read_text', side_effect=AssertionError('CONTENT_READ_FORBIDDEN')))
            self.assertEqual(3, len(op.access_files()))
            file.chmod(0o644)
            with self.assertRaisesRegex(RuntimeError, 'ACCESS_FILE'): op.access_files()

    def test_cli_execution_and_php_probe_without_receipt_refuse_offline(self):
        commands = [[ '/usr/bin/python3', str(op.ROOT/'scripts/op08_xampp_admin.py'), '--execute'],
                    [op.PHP, str(op.ROOT/'scripts/op08_journal.php'), '--probe', '--access=absent'],
                    [op.PHP, str(op.ROOT/'scripts/op08_journal.php'), '--create-journal', '--access=absent'],
                    [op.PHP, str(op.ROOT/'scripts/op08_journal.php'), '--unknown']]
        for command in commands:
            result = subprocess.run(command, capture_output=True, text=True, timeout=10)
            self.assertEqual(1, result.returncode)
            self.assertEqual('STOP_INSPECT_NO_RETRY', json.loads(result.stdout)['status'])
            self.assertEqual('', result.stderr)


if __name__ == '__main__':
    unittest.main()
