import contextlib
import io
import unittest
from unittest.mock import patch
import start_homya_local as tool


class LocalStartupTest(unittest.TestCase):
    def test_default_status_never_starts_processes(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=None), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main([]), 0)

    def test_start_never_restarts_existing_verified_services(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=12345), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main(['--start']), 0)


if __name__ == '__main__': unittest.main()
