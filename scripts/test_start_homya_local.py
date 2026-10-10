import contextlib
import io
import unittest
import tempfile
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch
import start_homya_local as tool


class LocalStartupTest(unittest.TestCase):
    def test_default_status_never_starts_processes(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=None), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main([]), 0)

    def test_start_never_restarts_existing_verified_services(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=12345), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main(['--start']), 0)

    def test_reused_pid_after_reboot_is_ignored_without_signalling_it(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'apache.pid').write_text('12345\n')
            with patch.object(tool, 'RUN', root), patch.object(tool.subprocess, 'run', return_value=SimpleNamespace(returncode=0, stdout=b'/unrelated/program')) as process:
                self.assertIsNone(tool.verified_pid('apache', 'httpd.conf'))
                self.assertEqual(process.call_args.args[0], ['/bin/ps', '-p', '12345', '-o', 'command='])

    def test_occupied_port_refuses_start_without_touching_its_owner(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=None), patch.object(tool.socket, 'socket') as probe, patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), contextlib.redirect_stdout(io.StringIO()):
            probe.return_value.__enter__.return_value.bind.side_effect = OSError('port occupied')
            self.assertEqual(tool.main(['--start']), 1)

    def test_login_wait_preserves_existing_services(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=12345), patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')), patch.object(tool, 'wait_ready') as wait, contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main(['--start', '--wait-ready']), 0)
            wait.assert_called_once()

    def test_database_unavailable_causes_retryable_failure(self):
        with patch.object(tool, 'require_private'), patch.object(tool, 'verified_pid', return_value=12345), patch.object(tool, 'wait_ready', side_effect=RuntimeError('not ready')), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(tool.main(['--start', '--wait-ready']), 1)

    def test_wait_handles_temporary_http_failure(self):
        with patch.object(tool.Path, 'is_socket', return_value=True), patch.object(tool.urllib.request, 'build_opener') as opener, patch.object(tool.time, 'sleep'):
            reply = opener.return_value.open.return_value.__enter__.return_value
            reply.status = 200
            opener.return_value.open.side_effect = [OSError('not ready'), opener.return_value.open.return_value]
            tool.wait_ready()
            self.assertEqual(opener.return_value.open.call_count, 2)

    def test_wait_for_database_is_bounded(self):
        with patch.object(tool.Path, 'is_socket', return_value=False), patch.object(tool.time, 'monotonic', side_effect=[0, 1, 3]), patch.object(tool.time, 'sleep'):
            with self.assertRaisesRegex(RuntimeError, 'LOCAL_DEPENDENCIES_NOT_READY'):
                tool.wait_ready(timeout=2)


if __name__ == '__main__': unittest.main()
