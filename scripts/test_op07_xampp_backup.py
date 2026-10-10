import copy
from contextlib import ExitStack
import io
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import unittest
from unittest.mock import Mock, patch

import op07_xampp_backup as backup
from op07_dump import TABLES


class EnvelopeTests(unittest.TestCase):
    def setUp(self):
        self.safety = patch.object(backup.subprocess, 'run', side_effect=AssertionError('NATIVE_PROCESS_FORBIDDEN'))
        self.safety.start(); self.addCleanup(self.safety.stop)
        self.popen = patch.object(backup.subprocess, 'Popen', side_effect=AssertionError('NATIVE_PROCESS_FORBIDDEN'))
        self.popen.start(); self.addCleanup(self.popen.stop)

    def test_offline_preparation_never_calls_sql_or_loads_credentials(self):
        with patch.object(backup.base, 'sql', side_effect=AssertionError('SQL_FORBIDDEN')), \
             patch.object(backup, 'safe_json', side_effect=AssertionError('CREDENTIALS_FORBIDDEN')):
            result = backup.prepare()
        self.assertFalse(result['execution_authorized'])
        self.assertFalse(result['sql_executed'])
        self.assertEqual(len(result['rights_initial']), 17)
        self.assertEqual(len(result['rights_final']), 5)
        self.assertEqual(result['target'], 'homya_restore_op07')

    def test_exact_table_rights_not_schema_or_global(self):
        self.assertEqual(len(backup.GRANTS), 5)
        for table, grant in zip(TABLES, backup.GRANTS):
            self.assertIn('`homya_restore_op07`.`'+table+'`', grant)
            self.assertNotIn('*', grant)
            self.assertNotIn('ALL', grant)
            self.assertNotIn('ALTER', grant)
        self.assertEqual(sum('REFERENCES' in g for g in backup.GRANTS), 2)

    def test_dump_command_bounded_to_source_five_tables(self):
        cmd = backup.dump_command()
        self.assertEqual(cmd[-6:], ['homya_test', *TABLES])
        self.assertEqual(cmd[1], '--no-defaults')
        self.assertIn('--skip-add-drop-table', cmd)
        self.assertNotIn('--all-databases', cmd)

    def test_invalid_receipt_stops_execute_before_sql_or_directory(self):
        with patch.object(backup, 'validate_receipt', side_effect=RuntimeError('EXECUTION_AGREEMENT_REQUIRED')), \
             patch.object(backup, 'make_run', side_effect=AssertionError('WRITE_FORBIDDEN')), \
             patch.object(backup, 'admin_sql', side_effect=AssertionError('SQL_FORBIDDEN')):
            with self.assertRaisesRegex(RuntimeError, 'EXECUTION_AGREEMENT_REQUIRED'):
                backup.execute('/private/tmp/not-approved.json')

    def receipt_fixture(self):
        return {'operation': 'OP-07', 'source': backup.SOURCE, 'target': backup.TARGET, 'user': backup.USER,
                'decision': 'DECISION-035', 'execution_authorized': True, 'agreement_sha256': 'hash',
                'plan_sha256': 'hash', 'artifacts': dict.fromkeys(backup.ARTIFACTS, 'hash'),
                'tools': dict.fromkeys([backup.MYSQL, backup.DUMP, backup.PHP], 'hash')}

    def validate_mock_receipt(self, receipt, agreement=None):
        agreement = agreement or {'operation': 'OP-07', 'decision': 'DECISION-035', 'sql_authorized': True,
                                   'execution_authorized': True, 'registry_readback_verified': True,
                                   'registry_after_sha256': 'hash'}
        with patch.object(backup, 'safe_json', side_effect=[receipt, agreement]), \
             patch.object(backup, 'digest', return_value='hash'), \
             patch.object(Path, 'read_text', return_value='#### DECISION-035\n| Projet / périmètre | HOMYA — OP-07 exécution |\nVALIDÉ'):
            return backup.validate_receipt('/unused')

    def test_future_receipt_requires_separate_scope_and_agreement(self):
        self.assertEqual(self.validate_mock_receipt(self.receipt_fixture())['decision'], 'DECISION-035')
        for field, wrong in [('source', 'homya_local'), ('target', 'homya_test'), ('user', 'root'),
                             ('execution_authorized', False), ('decision', 'DECISION-034')]:
            receipt = self.receipt_fixture(); receipt[field] = wrong
            with self.subTest(field=field), self.assertRaises(RuntimeError):
                self.validate_mock_receipt(receipt)
        agreement = {'operation': 'OP-07', 'decision': 'DECISION-034', 'sql_authorized': False}
        with self.assertRaises(RuntimeError):
            self.validate_mock_receipt(self.receipt_fixture(), agreement)

    def test_artifact_and_tool_hash_and_plan_changes_refused(self):
        for target in ['plan_sha256', 'artifacts', 'tools']:
            receipt = self.receipt_fixture()
            if target == 'plan_sha256':
                receipt[target] = 'wrong'
            else:
                receipt[target][next(iter(receipt[target]))] = 'wrong'
            with self.subTest(target=target), self.assertRaises(RuntimeError):
                self.validate_mock_receipt(receipt)

    def test_private_json_mode_link_duplicate_keys_and_atime(self):
        with tempfile.TemporaryDirectory() as tmp:
            directory = Path(tmp).resolve(); path = directory/'receipt.json'
            path.write_text('{"value":1}'); path.chmod(0o600)
            self.assertEqual(backup.safe_json(path, directory), {'value': 1})
            path.chmod(0o644)
            with self.assertRaisesRegex(RuntimeError, 'PRIVATE_FILE'):
                backup.safe_json(path, directory)
            path.chmod(0o600); path.write_text('{"value":1,"value":2}')
            with self.assertRaisesRegex(RuntimeError, 'DUPLICATE_JSON_KEY'):
                backup.safe_json(path, directory)
            link = directory/'link.json'; link.symlink_to(path)
            with self.assertRaisesRegex(RuntimeError, 'PRIVATE_PATH'):
                backup.safe_json(link, directory)
            hard = directory/'hard.json'; os.link(path, hard)
            with self.assertRaisesRegex(RuntimeError, 'PRIVATE_FILE'):
                backup.safe_json(path, directory)

    def test_collisions_even_empty_schema_stop(self):
        for responses in [['1\n', '0\n'], ['0\n', '1\n']]:
            with patch.object(backup, 'admin_sql', side_effect=responses):
                with self.assertRaisesRegex(RuntimeError, 'TARGET_OR_ACCOUNT_COLLISION'):
                    backup.collisions('receipt')

    def test_reference_files_copied_exactly_private_and_not_overwritten(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp).resolve(); (root/'scripts').mkdir()
            src = root/'scripts/source.txt'; src.write_text('synthetic provenance')
            run = root/'run'; run.mkdir(mode=0o700)
            with patch.object(backup, 'ROOT', root), patch.object(backup, 'ARTIFACTS', ('scripts/source.txt',)), \
                 patch.object(backup, 'validate_receipt', return_value={'artifacts': {'scripts/source.txt': backup.digest(src)}}):
                backup.freeze_references(run, 'receipt')
                dest = run/'reference-files/scripts/source.txt'
                self.assertEqual(dest.read_bytes(), src.read_bytes())
                self.assertEqual(dest.stat().st_mode & 0o777, 0o400)
                self.assertEqual(dest.parent.stat().st_mode & 0o777, 0o700)
                with self.assertRaises(FileExistsError):
                    backup.freeze_references(run, 'receipt')

    def test_exact_rights_audit_rejects_cross_schema_delegation_and_extra_global(self):
        def lines(rights=backup.RIGHTS):
            return '\n'.join(json.dumps({'schema': backup.TARGET, 'table': t, 'right': p, 'grantable': 'NO'}) for t, p in sorted(rights))
        with patch.object(backup, 'admin_sql', side_effect=[lines(), *(['0\n']*8)]):
            self.assertEqual(backup.account_rights('receipt'), backup.RIGHTS)
        for text in [lines().replace('"NO"', '"YES"', 1), lines().replace(backup.TARGET, 'homya_test', 1),
                     lines(backup.RIGHTS | {('migrations', 'DROP')})]:
            with patch.object(backup, 'admin_sql', return_value=text), self.assertRaises(RuntimeError):
                backup.account_rights('receipt')
        with patch.object(backup, 'admin_sql', side_effect=[lines(), '0\n', '1\n']), self.assertRaisesRegex(RuntimeError, 'RESTORE_EXTRA_RIGHTS'):
            backup.account_rights('receipt')

    def test_reduction_revokes_only_present_allowed_rights_and_keeps_select(self):
        with patch.object(backup, 'account_rights', side_effect=[backup.RIGHTS, backup.READ_RIGHTS]), \
             patch.object(backup, 'admin_sql') as sql:
            self.assertEqual(len(backup.reduce_rights('receipt')), 5)
            self.assertEqual(sql.call_count, 5)
            for call in sql.call_args_list:
                self.assertNotIn('SELECT', call.args[0]); self.assertNotIn('*', call.args[0])
        with patch.object(backup, 'account_rights', side_effect=[{('migrations', 'CREATE')}, set()]), \
             patch.object(backup, 'admin_sql') as sql:
            backup.reduce_rights('receipt')
            self.assertEqual(sql.call_count, 1)

    def test_admin_secret_is_stdin_and_error_output_is_expurgated(self):
        secret_query = "CREATE USER 'synthetic' IDENTIFIED BY 'test-only-not-a-secret'"
        with patch.object(backup, 'validate_receipt'), \
             patch.object(backup.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, 'ok\n', '')) as native:
            self.assertEqual(backup.admin_sql(secret_query, 'receipt'), 'ok\n')
            self.assertNotIn(secret_query, native.call_args.args[0])
            self.assertEqual(native.call_args.kwargs['input'], secret_query+';\n')
        with patch.object(backup, 'validate_receipt'), \
             patch.object(backup.subprocess, 'run', return_value=subprocess.CompletedProcess([], 1, '', 'ERROR 1045 sensitive text')):
            with self.assertRaisesRegex(RuntimeError, '^XAMPP_SQL_ERROR_1045$'):
                backup.admin_sql(secret_query, 'receipt')

    def test_native_restore_does_not_inherit_options_or_expose_password(self):
        with tempfile.TemporaryDirectory() as tmp:
            run = Path(tmp).resolve(); path = run/'catalogue.sql'; path.write_text('synthetic'); path.chmod(0o400)
            backup.save(run/'restore-access.json', {'database': backup.TARGET, 'user': backup.USER, 'password': 'a'*64})
            cnf = run/'restore-client.cnf'; cnf.write_text('[client]\npassword='+'a'*64+'\n'); cnf.chmod(0o600)
            with patch.object(backup, 'validate_receipt'), patch.object(backup.subprocess, 'run', return_value=subprocess.CompletedProcess([], 0, b'', b'')) as native:
                backup.native_restore(run, path, 'receipt', backup.digest(path), time.monotonic()+10)
                args = native.call_args.args[0]
                self.assertEqual(args[1], '--defaults-file='+str(cnf))
                self.assertNotIn('a'*64, ' '.join(args))
                self.assertIn('--binary-mode', args); self.assertIn('--local-infile=0', args)
            cnf.write_text('[client]\npassword=changed\n')
            with patch.object(backup, 'validate_receipt'), self.assertRaisesRegex(RuntimeError, 'RESTORE_ACCESS_CHANGED'):
                backup.native_restore(run, path, 'receipt', backup.digest(path), time.monotonic()+10)

    def simulated_execution(self, break_at=None):
        """Every IO boundary to SQL/CLI/media is doubled; only synthetic private files are written."""
        with tempfile.TemporaryDirectory() as tmp:
            top = Path(tmp).resolve(); qa = top/'qa'; qa.mkdir(); run = top/'run'; run.mkdir(mode=0o700)
            receipt = qa/'receipt.json'; receipt.write_text('{}')
            ddl = {t: t+'\tCREATE TABLE `'+t+'` (\n  `value` longtext DEFAULT NULL\n) ENGINE=InnoDB' for t in TABLES}
            audit = {'physical_ddl': ddl}; report = {'tables': {t: {'rows': 1, 'sha256': 'same'} for t in TABLES}, 'raw_value_bytes': 32}
            queries = []
            def admin(query, _receipt):
                queries.append(query)
                if break_at == 'grant' and query.startswith('GRANT'):
                    raise RuntimeError('SYNTHETIC_GRANT_STOP')
                return ''
            def dump(path, *_args):
                if break_at == 'dump':
                    raise RuntimeError('SYNTHETIC_DUMP_STOP')
                path.write_text('\n'.join(ddl[t].split('\t',1)[1]+";\nINSERT INTO `"+t+"` (`value`) VALUES ('x');" for t in TABLES))
                path.chmod(0o600)
            snapshots = []
            def snap(*_args, **_kwargs):
                if break_at == 'source-before':
                    raise RuntimeError('SYNTHETIC_SNAPSHOT_STOP')
                answer = copy.deepcopy(report)
                snapshots.append(_args[-1])
                if break_at == 'source-drift' and len(snapshots) == 2:
                    answer['tables']['migrations']['sha256'] = 'changed'
                if break_at == 'restore-mismatch' and _args[-1] == 'restored':
                    answer['tables']['migrations']['sha256'] = 'changed'
                return answer
            patches = [patch.object(backup, 'QA', qa), patch.object(backup, 'BACKUPS', top),
                       patch.object(backup, 'validate_receipt', return_value={'tools': {}}),
                       patch.object(backup, 'tool_context', return_value={'client_versions': {}, 'php_version': 'synthetic'}),
                       patch.object(backup, 'source_audit', return_value=audit), patch.object(backup, 'collisions'),
                       patch.object(backup, 'make_run', return_value=run), patch.object(backup, 'snapshot', side_effect=snap),
                       patch.object(backup, 'freeze_references', return_value={}),
                       patch.object(backup, 'runtime_witnesses', return_value={'synthetic-runtime': 'hash'}),
                       patch.object(backup, 'native_dump', side_effect=dump), patch.object(backup, 'verify_media', return_value={'media_included': False}),
                       patch.object(backup, 'EXPECTED_COUNTS', dict.fromkeys(TABLES, 1)),
                       patch.object(backup, 'admin_sql', side_effect=admin),
                       patch.object(backup, 'account_rights', side_effect=[backup.RIGHTS, backup.READ_RIGHTS] if break_at is None else None, return_value=backup.RIGHTS),
                       patch.object(backup, 'native_restore', side_effect=RuntimeError('SYNTHETIC_RESTORE_STOP') if break_at == 'restore' else None),
                       patch.object(backup, 'target_ddl'), patch.object(backup, 'event')]
            with ExitStack() as contexts:
                for context in patches:
                    contexts.enter_context(context)
                reduction = contexts.enter_context(patch.object(backup, 'reduce_rights', return_value=list(backup.READ_RIGHTS)))
                if break_at is None:
                    backup.execute(receipt)
                    status = json.loads((run/'status.json').read_text())
                    self.assertTrue(status['restoration_tested']); self.assertFalse(status['activated'])
                    self.assertFalse(status['independent_disk_backup'])
                else:
                    with self.assertRaises(RuntimeError):
                        backup.execute(receipt)
                    status = json.loads((run/'failure.json').read_text())
                    self.assertFalse(status['cleanup']); self.assertFalse(status['source_restore'])
                    self.assertEqual(reduction.called, break_at not in ['source-before', 'dump', 'source-drift'])
                self.assertTrue(all('homya_test' not in q and 'DROP' not in q for q in queries))
                self.assertEqual(sum(q.startswith('CREATE DATABASE') for q in queries), 0 if break_at in ['source-before', 'dump', 'source-drift'] else 1)

    def test_full_workflow_simulation_not_a_real_restoration(self):
        self.simulated_execution()

    def test_failures_before_create_and_after_grant_restore_or_comparison_never_retry(self):
        for stage in ['source-before', 'dump', 'source-drift', 'grant', 'restore', 'restore-mismatch']:
            with self.subTest(stage=stage):
                self.simulated_execution(stage)


if __name__ == '__main__':
    unittest.main()
