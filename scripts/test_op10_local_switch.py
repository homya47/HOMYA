"""Synthetic files only, no SQL or live environment access."""
import contextlib
import io
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import op10_local_switch as tool


class SourceSwitchTest(unittest.TestCase):
    def test_preserves_every_other_byte_including_synthetic_secret_and_crlf(self):
        raw = b'APP_ENV=local\r\nDB_PASSWORD=fake-only\r\nHOMYA_CATALOGUE_SOURCE="files"\r\nTAIL=ok\r\n'
        self.assertEqual(tool.replace_source(raw, 'files', 'sql'), raw.replace(b'"files"', b'sql'))

    def test_missing_key_is_only_appended_and_original_bytes_kept(self):
        for raw in [b'', b'A=x', b'A=x\n']:
            after = tool.replace_source(raw, 'files', 'sql')
            self.assertTrue(after.startswith(raw))
            self.assertTrue(after.endswith(b'HOMYA_CATALOGUE_SOURCE=sql\n'))

    def test_ambiguous_unexpected_or_exported_source_is_refused(self):
        for raw in [b'HOMYA_CATALOGUE_SOURCE=files\nHOMYA_CATALOGUE_SOURCE=files\n', b'HOMYA_CATALOGUE_SOURCE=sql\n',
                    b'export HOMYA_CATALOGUE_SOURCE=files\n', b'  HOMYA_CATALOGUE_SOURCE=files\n']:
            with self.subTest(raw=raw), self.assertRaises(RuntimeError):
                tool.replace_source(raw, 'files', 'sql')

    def test_default_does_not_read_registry_or_config(self):
        with patch.object(Path, 'read_text', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main([]), 0)

    def test_atomic_change_refuses_concurrency_and_preserves_current_file(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            path = Path(directory) / '.env'
            path.write_bytes(b'current'); path.chmod(0o600)
            with self.assertRaisesRegex(RuntimeError, 'CONFIG_CHANGED'):
                tool.atomic_change(path, b'old', b'replacement')
            self.assertEqual(path.read_bytes(), b'current')

    def test_atomic_change_valid_and_private(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            path = Path(directory) / '.env'
            path.write_bytes(b'before'); path.chmod(0o600)
            tool.atomic_change(path, b'before', b'after')
            self.assertEqual(path.read_bytes(), b'after')
            tool.require_private(path)

    def test_links_and_unsafe_modes_refused(self):
        with tempfile.TemporaryDirectory(dir='/private/tmp') as directory:
            path = Path(directory) / '.env'
            path.write_bytes(b'fake'); path.chmod(0o644)
            with self.assertRaises(RuntimeError): tool.require_private(path)
            link = Path(directory) / 'link'; link.symlink_to(path)
            with self.assertRaises(RuntimeError): tool.require_private(link)


if __name__ == '__main__': unittest.main()
