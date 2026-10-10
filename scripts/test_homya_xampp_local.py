"""Synthetic filesystem/configuration tests; no XAMPP or SQL access."""
import contextlib
import io
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import homya_xampp_local as tool


class LocalInstallationTest(unittest.TestCase):
    def test_default_never_reads_registry_or_copies(self):
        with patch.object(Path, 'read_text', side_effect=AssertionError('unexpected')), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main([]), 0)

    def test_manifest_hashes_content_and_modes_without_following_symlinks(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as folder:
            root = Path(folder)
            (root / 'private').mkdir(mode=0o700)
            (root / 'private' / '.env').write_text('SYNTHETIC_ONLY')
            (root / 'private' / '.env').chmod(0o600)
            (root / 'link').symlink_to('private/.env')
            result = tool.manifest(root)
            self.assertEqual(result['private']['mode'], 0o700)
            self.assertEqual(result['private/.env']['mode'], 0o600)
            self.assertEqual(result['link']['target'], 'private/.env')
            self.assertNotIn('SYNTHETIC_ONLY', str(result))

    def test_configuration_has_public_root_fixed_loopback_and_dedicated_fpm(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as folder:
            root = Path(folder)
            tool.configs(root)
            apache, fpm = (root / 'httpd.conf').read_text(), (root / 'php-fpm.conf').read_text()
            self.assertIn('Listen 127.0.0.1:8080', apache)
            self.assertIn('htdocs/HOMYA/public', apache)
            self.assertIn('Require all denied', apache)
            self.assertIn('Require local', apache)
            self.assertNotIn('libphp', apache)
            self.assertIn('127.0.0.1:19085', fpm)
            self.assertIn('security.limit_extensions = .php', fpm)
            self.assertEqual((root / 'httpd.conf').stat().st_mode & 0o777, 0o600)

    def test_group_restoration_never_changes_foreign_owner_or_modes(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as folder:
            root = Path(folder)
            path = root / 'fixture'
            path.write_text('synthetic')
            expected = tool.manifest(root)
            self.assertEqual(tool.restore_source_groups(root, expected), 0)
            expected['fixture']['mode'] = 0o777
            with self.assertRaisesRegex(RuntimeError, 'COPY_METADATA_DIVERGED'):
                tool.restore_source_groups(root, expected)


if __name__ == '__main__': unittest.main()
