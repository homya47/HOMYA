"""Isolated OP-05 guard tests. Native SQL entry point is never called."""
import copy
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

import op05_xampp_admin as module


class Op05Tests(unittest.TestCase):
    def setUp(self):
        self.data = json.loads((module.QA / 'op05-precontrole-xampp.json').read_text())['result']
        self.sql_patch = patch.object(module, 'sql', side_effect=AssertionError('REAL_SQL_FORBIDDEN'))
        self.sql_patch.start()
        self.addCleanup(self.sql_patch.stop)

    def test_current_empty_snapshot_is_valid(self):
        module.validate(self.data, 'empty')

    def test_identity_capacity_and_accounts_drift_are_rejected(self):
        for section, key, value in [('server', 'port', 3306), ('server', 'socket', '/other'),
                                    ('server', 'packet_session', 1024), ('server', 'checks', 0)]:
            with self.subTest(key=key):
                data = copy.deepcopy(self.data)
                data[section][key] = value
                with self.assertRaises(RuntimeError):
                    module.validate(data, 'empty')
        self.data['accounts'].append(dict(self.data['accounts'][0], host='%'))
        with self.assertRaisesRegex(RuntimeError, 'ACCOUNTS_DRIFT'):
            module.validate(self.data, 'empty')

    def test_no_additional_rights_or_delegation_are_permitted(self):
        for kind in self.data['unexpected_rights']:
            with self.subTest(kind=kind):
                data = copy.deepcopy(self.data)
                data['unexpected_rights'][kind] = 1
                with self.assertRaisesRegex(RuntimeError, 'RIGHTS_OUT_OF_SCOPE'):
                    module.validate(data, 'empty')
        self.data['privileges'][0]['grantable'] = 'YES'
        with self.assertRaisesRegex(RuntimeError, 'TABLE_RIGHTS_DRIFT'):
            module.validate(self.data, 'empty')

    def test_collisions_in_either_database_are_rejected(self):
        for schema in module.SCHEMAS:
            with self.subTest(schema=schema):
                data = copy.deepcopy(self.data)
                data['tables'].append({'schema': schema, 'table': 'unexpected'})
                with self.assertRaisesRegex(RuntimeError, 'OBJECTS_DRIFT'):
                    module.validate(data, 'empty')

    def test_physical_rights_and_innodb_are_checked_independently(self):
        data = copy.deepcopy(self.data)
        data['table_records'][0]['privileges'] += ',Drop'
        with self.assertRaisesRegex(RuntimeError, 'PHYSICAL_RIGHTS_DRIFT'):
            module.validate(data, 'empty')
        self.data['engines'][0]['transactions'] = 'NO'
        with self.assertRaisesRegex(RuntimeError, 'INNODB_UNAVAILABLE'):
            module.validate(self.data, 'empty')

    def test_grant_refuses_missing_receipt_before_any_inspection_or_write(self):
        with patch.object(module, 'checked_inspection') as checker, patch.object(module, 'sql') as runner:
            with self.assertRaises(OSError):
                module.grant_journal(module.QA / 'OP05_NOT_AUTHORIZED_DOES_NOT_EXIST.json', runner, checker)
            checker.assert_not_called()
            runner.assert_not_called()

    def test_grant_refuses_collision_before_write(self):
        with patch.object(module, 'validate_receipt'), patch.object(module, 'sql') as runner:
            def checker(phase):
                raise RuntimeError('OBJECTS_DRIFT')
            with self.assertRaisesRegex(RuntimeError, 'OBJECTS_DRIFT'):
                module.grant_journal('synthetic', runner, checker)
            runner.assert_not_called()

    def test_grant_has_one_exact_statement_and_two_checks(self):
        calls = []
        with patch.object(module, 'validate_receipt') as receipt:
            result = module.grant_journal('synthetic', calls.append, lambda phase: calls.append(phase))
        self.assertEqual(calls, ['journal', module.GRANT, 'granted'])
        self.assertEqual(receipt.call_count, 2)
        self.assertEqual(result['write_sql'], [module.GRANT])

    def test_postgrant_error_is_never_retried_or_cleaned(self):
        calls = []
        def checker(phase):
            if phase == 'granted':
                raise RuntimeError('POSTCHECK_FAILURE')
        with patch.object(module, 'validate_receipt'):
            with self.assertRaisesRegex(RuntimeError, 'POSTCHECK_FAILURE'):
                module.grant_journal('synthetic', calls.append, checker)
        self.assertEqual(calls, [module.GRANT])

    def test_migration_wrapper_has_one_exact_invocation_between_checks(self):
        calls = []
        access = str(module.ROOT / 'storage/app/private/database-access/synthetic/.env.homya_test_schema')
        with patch.object(module, 'validate_receipt'):
            result = module.run_migrations('synthetic', access, calls.append, lambda phase: calls.append(phase))
        self.assertEqual(calls[0], 'granted')
        self.assertEqual(calls[2], 'final')
        self.assertEqual(calls[1][:5], ['/opt/homebrew/bin/php', str(module.ROOT / 'artisan'), 'homya:catalogue-schema', '--execute', '--operation=OP-05'])
        self.assertIn('--connection=homya_test_schema', calls[1])
        self.assertTrue(result['physical_ddl_review_pending'])

    def test_failed_migration_is_not_replayed_or_cleaned(self):
        calls = []
        def runner(arguments):
            calls.append(arguments)
            raise RuntimeError('SYNTHETIC_PARTIAL_DDL')
        with patch.object(module, 'validate_receipt'):
            with self.assertRaisesRegex(RuntimeError, 'SYNTHETIC_PARTIAL_DDL'):
                module.run_migrations('synthetic', str(module.ROOT / 'storage/app/private/database-access/synthetic/.env.homya_test_schema'), runner, lambda phase: calls.append(phase))
        self.assertEqual(len(calls), 2)
        self.assertEqual(calls[0], 'granted')

    def test_receipt_scope_modes_duplicates_and_hashes(self):
        with tempfile.TemporaryDirectory(prefix='homya-op05-receipt-fixture-') as directory:
            root = Path(directory)
            qa = root / 'output/qa-import'
            qa.mkdir(parents=True)
            for name in (*module.ARTIFACTS, 'docs/OP-05-SCHEMA-TEST-PREPARATION.md'):
                path = root / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('Synthetic fixture, no SQL')
            receipt = {'operation': 'OP-05', 'schema': 'homya_test', 'account': 'homya_test_schema@localhost',
                       'decision': 'DECISION-999', 'execution_authorized': True,
                       'plan_sha256': module.digest(root / 'docs/OP-05-SCHEMA-TEST-PREPARATION.md'),
                       'artifacts': {name: module.digest(root / name) for name in module.ARTIFACTS}}
            path = qa / 'synthetic.json'
            path.write_text(json.dumps(receipt))
            path.chmod(0o600)
            with patch.object(module, 'ROOT', root), patch.object(module, 'QA', qa):
                self.assertEqual(module.validate_receipt(path), receipt)
                path.chmod(0o644)
                with self.assertRaisesRegex(RuntimeError, 'RECEIPT_MODE'):
                    module.validate_receipt(path)
                path.chmod(0o600)
                path.write_text('{"operation":"OP-05","operation":"OP-08"}')
                with self.assertRaisesRegex(RuntimeError, 'DUPLICATE_JSON_KEY'):
                    module.validate_receipt(path)
                for key, value in [('schema', 'homya_local'), ('execution_authorized', 1), ('plan_sha256', 'changed')]:
                    path.write_text(json.dumps(dict(receipt, **{key: value})))
                    with self.assertRaises(RuntimeError):
                        module.validate_receipt(path)
                path.write_text(json.dumps(receipt))
                (root / module.ARTIFACTS[0]).write_text('Changed helper')
                with self.assertRaisesRegex(RuntimeError, 'ARTIFACT_CHANGED'):
                    module.validate_receipt(path)


if __name__ == '__main__':
    unittest.main()
