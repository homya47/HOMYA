import copy
import json
from pathlib import Path
import tempfile
import subprocess
import unittest
from unittest.mock import patch

import op06_xampp_admin as recipe


class Op06EnvelopeTests(unittest.TestCase):
    def setUp(self):
        self.original = json.loads((recipe.QA / 'op05-reprise-controle-independant.json').read_text())['result']['metadata']
        guard = patch.object(recipe.base, 'sql', side_effect=AssertionError('REAL_SQL_FORBIDDEN'))
        guard.start()
        self.addCleanup(guard.stop)

    def metadata(self, stage):
        data = copy.deepcopy(self.original)
        data['column_privileges'], data['column_records'] = [], []
        for role, start, privileges in [('reader', 0, ['SELECT']), ('import', 4, ['INSERT', 'SELECT'])]:
            for i, table in enumerate(recipe.base.TABLES):
                if stage <= start + i:
                    continue
                for privilege in privileges:
                    data['privileges'].append(dict(grantee="'homya_test_" + role + "'@'localhost'", schema='homya_test', table=table, privilege=privilege, grantable='NO'))
                data['table_records'].append(dict(user='homya_test_' + role, host='localhost', schema='homya_test', table=table,
                                                   privileges=','.join(p.title() for p in privileges), column=''))
        if stage == 9:
            data['unexpected_rights']['column'] = 3
            for c in recipe.COLUMNS:
                data['column_privileges'].append(dict(grantee="'homya_test_import'@'localhost'", schema='homya_test', table='archive_lots', column=c, privilege='UPDATE', grantable='NO'))
                data['column_records'].append(dict(user='homya_test_import', host='localhost', schema='homya_test', table='archive_lots', column=c, privileges='Update'))
            for row in data['table_records']:
                if row['user'] == 'homya_test_import' and row['table'] == 'archive_lots':
                    row['column'] = 'Update'
        data['privileges'].sort(key=lambda p: (p['grantee'], p['schema'], p['table'], p['privilege']))
        return data

    def test_all_ten_exact_grant_stages(self):
        for stage in range(10):
            with self.subTest(stage=stage):
                recipe.validate_metadata(self.metadata(stage), stage)

    def test_table_update_is_not_a_column_grant(self):
        data = self.metadata(9)
        data['privileges'].append(dict(grantee="'homya_test_import'@'localhost'", schema='homya_test', table='archive_lots', privilege='UPDATE', grantable='NO'))
        with self.assertRaisesRegex(RuntimeError, 'DML_RIGHTS_DRIFT'):
            recipe.validate_metadata(data, 9)

    def test_column_names_scope_and_delegation_are_strict(self):
        for key, value in [('column', 'archive_document'), ('schema', 'homya_local'), ('grantable', 'YES')]:
            with self.subTest(key=key):
                data = self.metadata(9)
                data['column_privileges'][0][key] = value
                with self.assertRaisesRegex(RuntimeError, 'COLUMN_RIGHTS_DRIFT'):
                    recipe.validate_metadata(data, 9)

    def test_physical_summary_and_column_records_are_audited(self):
        data = self.metadata(9)
        data['column_records'][0]['privileges'] = 'Update,Select'
        with self.assertRaisesRegex(RuntimeError, 'PHYSICAL_COLUMN_DRIFT'):
            recipe.validate_metadata(data, 9)
        data = self.metadata(9)
        next(r for r in data['table_records'] if r['user'] == 'homya_test_import')['column'] = ''
        with self.assertRaisesRegex(RuntimeError, 'COLUMN_SUMMARY_DRIFT'):
            recipe.validate_metadata(data, 9)

    def test_unapproved_global_rights_and_extra_objects_are_rejected(self):
        for key in ('global', 'roles', 'schema', 'proxy'):
            data = self.metadata(9)
            data['unexpected_rights'][key] = 1
            with self.assertRaisesRegex(RuntimeError, 'RIGHTS_OUT_OF_SCOPE'):
                recipe.validate_metadata(data, 9)
        data = self.metadata(0)
        data['tables'].append(dict(schema='homya_local', table='unexpected'))
        with self.assertRaisesRegex(RuntimeError, 'OBJECTS_DRIFT'):
            recipe.validate_metadata(data, 0)

    def test_grants_are_exact_and_stop_after_failed_postcheck(self):
        writes, stages = [], []
        def check(stage):
            stages.append(stage)
            if stage == 2:
                raise RuntimeError('SYNTHETIC_FAILURE')
        with patch.object(recipe, 'validate_receipt'):
            with self.assertRaisesRegex(RuntimeError, 'SYNTHETIC_FAILURE'):
                recipe.grant('synthetic', checker=check, writer=writes.append)
        self.assertEqual(writes, list(recipe.GRANTS[:2]))
        self.assertEqual(stages, [0, 0, 1, 1, 2])

    def test_all_nine_grants_are_audited_without_ddl_or_root_dml(self):
        writes, records = [], []
        with patch.object(recipe, 'validate_receipt'):
            recipe.grant('synthetic', checker=lambda i: i, writer=writes.append, recorder=lambda i, d: records.append(i))
        self.assertEqual(writes, list(recipe.GRANTS))
        self.assertEqual(records, list(range(1, 10)))
        self.assertTrue(all(s.startswith('GRANT ') and '*' not in s and 'GRANT OPTION' not in s for s in writes))

    def test_missing_receipt_stops_before_checker_or_writer(self):
        events = []
        with self.assertRaises(OSError):
            recipe.grant(str(recipe.QA / 'NO_OP06_RECEIPT.json'), checker=events.append, writer=events.append)
        self.assertEqual(events, [])

    def test_resume_requires_exact_single_retained_verified_lot(self):
        key = 'b' * 64
        counts = dict(zip(recipe.base.TABLES, (1, 1, 1, 6)))
        frozen = dict(existing_counts=counts, allowed_existing_lot=key)
        state = dict(counts=counts)
        valid = dict(key=key, state='verified', verified=True, proof=True)
        checks = []
        with patch.object(recipe, 'resume_source', return_value=frozen):
            result = recipe.resume_initial('frozen', checker=lambda stage, empty: checks.append((stage, empty)) or state,
                                           reader=lambda query: json.dumps(valid))
            self.assertEqual(result, state)
            self.assertEqual(checks, [(9, False)])
            for rows in [[], [valid, valid], [dict(valid, state='prepared')], [dict(valid, key='c' * 64)]]:
                with self.assertRaisesRegex(RuntimeError, 'RESUME_LOTS_CHANGED'):
                    recipe.resume_initial('frozen', checker=lambda *args: state,
                                          reader=lambda query: '\n'.join(json.dumps(r) for r in rows))
            with self.assertRaisesRegex(RuntimeError, 'RESUME_COUNTS_CHANGED'):
                recipe.resume_initial('frozen', checker=lambda *args: dict(counts=dict(counts, archive_products=2)))

    def test_resume_missing_frozen_run_stops_before_sql(self):
        with self.assertRaises(OSError):
            recipe.resume_initial(str(recipe.QA / 'op06-run-missing'))

    def test_readonly_audit_links_original_execution_and_only_two_allowed_code_changes(self):
        original = json.loads((recipe.QA / 'op06-reprise-autorisation.json').read_text())
        current = copy.deepcopy(original)
        extra = {
            'output/qa-import/op06-reprise-enregistrement-accord.json': 'synthetic',
            'output/qa-import/op06-reprise-autorisation.json': 'original-receipt',
            'output/qa-import/op06-read-test-avant-isolation.php': original['artifacts']['tests/MariaDB/CatalogueArchiveReadRecipeTest.php'],
            'output/qa-import/op06-admin-avant-audit-test.py': original['artifacts']['scripts/op06_xampp_admin.py']}
        current['artifacts'].update(extra)
        for p in ('scripts/op06_xampp_admin.py', 'tests/MariaDB/CatalogueArchiveReadRecipeTest.php'):
            current['artifacts'][p] = 'new-synthetic'
        recorded = dict(decision='DECISION-033', execution_authorized=True, registry_readback_verified=True, receipt_sha256='original-receipt')
        recipe.audit_execution_link(current, original, recorded, 'original-receipt')
        for key in ('app/Services/CatalogueArchiveImporter.php', 'config/database.php'):
            mutated = copy.deepcopy(current)
            mutated['artifacts'][key] = 'unapproved'
            with self.assertRaisesRegex(RuntimeError, 'AUDIT_UNAPPROVED_CODE_DRIFT'):
                recipe.audit_execution_link(mutated, original, recorded, 'original-receipt')
        with self.assertRaisesRegex(RuntimeError, 'AUDIT_EXECUTION_SOURCE_CHANGED'):
            recipe.audit_execution_link(current, original, recorded, 'wrong-parent')

    def test_receipt_binding_and_symlinks_in_isolated_root(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory).resolve()
            (root / 'fixture.py').write_text('synthetic')
            plan = root / 'plan.md'
            plan.write_text('synthetic plan')
            path = root / 'receipt.json'
            scope = dict(operation='OP-06', schema='homya_test', decision='DECISION-999', execution_authorized=True,
                         sql_read_recipe_authorized=True, lot_key=recipe.LOT, plan_sha256=recipe.base.digest(plan), artifacts={'fixture.py': recipe.base.digest(root / 'fixture.py')})
            path.write_text(json.dumps(scope))
            path.chmod(0o600)
            with patch.object(recipe, 'ROOT', root), patch.object(recipe, 'QA', root), patch.object(recipe, 'PLAN', plan), patch.object(recipe, 'ARTIFACTS', ('fixture.py',)):
                recipe.validate_receipt(path)
                (root / 'link.json').symlink_to(path)
                with self.assertRaisesRegex(RuntimeError, 'RECEIPT_PATH'):
                    recipe.validate_receipt(root / 'link.json')
                (root / 'fixture.py').write_text('changed')
                with self.assertRaisesRegex(RuntimeError, 'ARTIFACT_CHANGED'):
                    recipe.validate_receipt(path)
                path.write_text(json.dumps(scope).replace('homya_test', 'homya_local'))
                with self.assertRaisesRegex(RuntimeError, 'RECEIPT_SCOPE'):
                    recipe.validate_receipt(path)

    def test_cli_default_and_missing_scope_never_boot_or_load_access(self):
        result = subprocess.run(['/opt/homebrew/bin/php', str(recipe.ROOT / 'scripts/op06_recipe.php'), '--prepare'], capture_output=True, text=True, check=True)
        programme = json.loads(result.stdout)
        self.assertFalse(programme['sql_executed'])
        self.assertEqual(len(programme['fixtures']), 9)
        for command in (['--execute'], ['--worker=concurrency-a'], ['--unknown'],
                        ['--execute', '--receipt=' + str(recipe.QA / 'NO_OP06_RECEIPT.json'), '--import-access=invalid', '--reader-access=invalid', '--run-dir=invalid']):
            with self.subTest(command=command):
                result = subprocess.run(['/opt/homebrew/bin/php', str(recipe.ROOT / 'scripts/op06_recipe.php'), *command], capture_output=True, text=True)
                self.assertEqual(result.returncode, 1)
                self.assertEqual(json.loads(result.stdout)['status'], 'STOP_STATUS_TO_INSPECT')


if __name__ == '__main__':
    unittest.main()
