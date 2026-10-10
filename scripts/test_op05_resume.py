import copy
import json
import unittest
from unittest.mock import patch

import op05_resume as recovery


class RecoveryTests(unittest.TestCase):
    def setUp(self):
        self.state = json.loads((recovery.QA / 'op05-etat-partiel-fige.json').read_text())
        self.data = self.state['metadata']
        patcher = patch.object(recovery.original, 'sql', side_effect=AssertionError('REAL_SQL_FORBIDDEN'))
        patcher.start()
        self.addCleanup(patcher.stop)

    def with_references(self, count):
        data = copy.deepcopy(self.data)
        for table in recovery.PARENTS[:count]:
            data['privileges'].append({'grantee': "'homya_test_schema'@'localhost'", 'schema': 'homya_test',
                                       'table': table, 'privilege': 'REFERENCES', 'grantable': 'NO'})
            for row in data['table_records']:
                if row['user'] == 'homya_test_schema' and row['table'] == table:
                    row['privileges'] += ',References'
        data['privileges'].sort(key=lambda p: (p['grantee'], p['schema'], p['table'], p['privilege']))
        return data

    def test_three_exact_privilege_stages_are_accepted(self):
        for count in range(3):
            with self.subTest(count=count):
                recovery.validate_metadata(self.with_references(count), count)

    def test_unapproved_or_missing_references_are_rejected(self):
        for actual, expected in ((0, 1), (1, 0), (2, 1)):
            with self.subTest(actual=actual, expected=expected):
                with self.assertRaisesRegex(RuntimeError, 'REFERENCES_DRIFT'):
                    recovery.validate_metadata(self.with_references(actual), expected)

    def test_extra_objects_in_either_schema_are_rejected(self):
        for schema in recovery.original.SCHEMAS:
            data = copy.deepcopy(self.data)
            data['tables'].append({'schema': schema, 'table': 'unexpected'})
            with self.assertRaisesRegex(RuntimeError, 'PARTIAL_OBJECTS_DRIFT'):
                recovery.validate_metadata(data, 0)

    def test_unexpected_global_and_table_rights_are_rejected(self):
        data = copy.deepcopy(self.data)
        data['unexpected_rights']['schema'] = 1
        with self.assertRaisesRegex(RuntimeError, 'RIGHTS_OUT_OF_SCOPE'):
            recovery.validate_metadata(data, 0)
        data = self.with_references(2)
        data['privileges'][-1]['grantable'] = 'YES'
        with self.assertRaises(RuntimeError):
            recovery.validate_metadata(data, 2)

    def test_missing_receipt_prevents_any_read_or_write(self):
        events = []
        with self.assertRaises(OSError):
            recovery.execute(str(recovery.QA / 'NO_RECOVERY_APPROVAL_EXISTS.json'), 'invalid',
                             events.append, lambda *a: events.append(a), events.append)
        self.assertEqual(events, [])

    def test_exact_grants_precede_one_targeted_php_call(self):
        events = []
        access = str(recovery.ROOT / 'storage/app/private/database-access/synthetic/.env.homya_test_schema')
        with patch.object(recovery, 'validate_receipt'):
            recovery.execute('synthetic', access, events.append, lambda *a: events.append(a), events.append)
        grants = [event for event in events if isinstance(event, str)]
        self.assertEqual(grants, ["GRANT REFERENCES ON homya_test." + table + " TO 'homya_test_schema'@'localhost'" for table in recovery.PARENTS])
        commands = [event for event in events if isinstance(event, list)]
        self.assertEqual(len(commands), 1)
        self.assertEqual(commands[0][:3], ['/opt/homebrew/bin/php', str(recovery.ROOT / 'scripts/op05_resume.php'), '--execute'])
        self.assertEqual(events[-1], (2, True))

    def test_postgrant_error_stops_without_second_grant_or_ddl(self):
        events = []
        def checker(*stage):
            if stage == (1,):
                raise RuntimeError('SYNTHETIC_POSTCHECK_FAILURE')
        with patch.object(recovery, 'validate_receipt'):
            with self.assertRaisesRegex(RuntimeError, 'SYNTHETIC_POSTCHECK_FAILURE'):
                recovery.execute('synthetic', str(recovery.ROOT / 'storage/app/private/database-access/synthetic/.env.homya_test_schema'), events.append, checker, events.append)
        self.assertEqual(events, ["GRANT REFERENCES ON homya_test.archive_lots TO 'homya_test_schema'@'localhost'"])

    def test_changed_partial_ddl_is_refused(self):
        def sql(query):
            if query.startswith('SELECT COUNT'):
                return '0'
            if query.startswith('SELECT JSON_OBJECT'):
                return '\n'.join(json.dumps(row) for row in self.state['journal'])
            return 'CHANGED_PHYSICAL_DDL'
        with patch.object(recovery.original, 'inspect', return_value=self.data), patch.object(recovery.original, 'sql', side_effect=sql):
            with self.assertRaisesRegex(RuntimeError, 'PARTIAL_STATE_CHANGED'):
                recovery.inspect(0)


if __name__ == '__main__':
    unittest.main()
