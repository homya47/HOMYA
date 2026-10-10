"""Installer guards: no SQL, account creation or real registry access."""
import contextlib
import io
import tempfile
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch
import install_commerce_local as installer


class InstallerGuards(unittest.TestCase):
    def test_review_digests_are_required_before_any_side_effect(self):
        plan = dict(schema_sha256='schema', inventory_sha256='inventory', plan_sha256='plan')
        args = SimpleNamespace(schema_sha256='different', inventory_sha256='inventory', plan_sha256='plan')
        with patch.object(installer, 'sql') as sql, patch.object(installer, 'record_decision') as record:
            with self.assertRaisesRegex(RuntimeError, 'REVIEW_DIGEST_MISMATCH'):
                installer.apply(args, plan)
            sql.assert_not_called()
            record.assert_not_called()

    def test_offline_command_cannot_execute_sql(self):
        with patch('sys.argv', ['installer']), patch.object(installer, 'digest', return_value='offline'), \
                patch.object(installer, 'sql') as sql, patch.object(installer, 'apply') as apply:
            with contextlib.redirect_stdout(io.StringIO()) as output:
                installer.main()
            self.assertIn('schema_sha256', output.getvalue())
            sql.assert_not_called()
            apply.assert_not_called()

    def test_env_update_preserves_unrelated_keys_and_saves_original(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            env = root / '.env'
            original = 'APP_URL=http://127.0.0.1:8080\nDB_USERNAME=preserved_reader\nHOMYA_CHECKOUT_ENABLED=false\n'
            env.write_text(original)
            installer.update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, root)
            self.assertEqual((root / 'source-env-before').read_text(), original)
            self.assertEqual(env.read_text(), original + 'HOMYA_INVENTORY_ENABLED=true\n')
            self.assertEqual(env.stat().st_mode & 0o777, 0o600)

    def test_duplicate_env_key_and_symlink_are_refused(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            env = root / '.env'
            env.write_text('HOMYA_INVENTORY_ENABLED=false\nHOMYA_INVENTORY_ENABLED=true\n')
            with self.assertRaisesRegex(RuntimeError, 'DUPLICATE_ENV_KEY'):
                installer.update_env(env, {'HOMYA_INVENTORY_ENABLED': 'true'}, root)
            link = root / '.env-link'
            link.symlink_to(env)
            with self.assertRaisesRegex(RuntimeError, 'ENV_SYMLINK'):
                installer.update_env(link, {}, root)

    def test_sql_failures_never_reveal_stderr_or_password(self):
        result = SimpleNamespace(returncode=1, stderr='fixture_secret_password', stdout='')
        with patch('subprocess.run', return_value=result):
            with self.assertRaisesRegex(RuntimeError, '^LOCAL_SQL_REFUSED_DETAILS_WITHHELD$'):
                installer.sql('SELECT 1')


if __name__ == '__main__':
    unittest.main()
