import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
import install_homya_autostart as tool


class AutostartSafetyTest(unittest.TestCase):
    def test_login_job_retains_daemon_children_and_retries_only_failure(self):
        value = tool.configurations()['user']
        self.assertTrue(value['RunAtLoad'])
        self.assertTrue(value['AbandonProcessGroup'])
        self.assertEqual(value['KeepAlive'], {'SuccessfulExit': False})
        self.assertEqual(value['ProgramArguments'][-2:], ['--start', '--wait-ready'])

    def test_system_job_starts_only_the_xampp_database(self):
        value = tool.configurations()['system']
        self.assertEqual(value['ProgramArguments'], ['/Applications/XAMPP/xamppfiles/xampp', 'startmysql'])
        self.assertNotIn('UserName', value)

    def test_existing_launch_job_is_preserved(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'existing.plist'
            path.write_bytes(b'existing work')
            with patch.object(tool.subprocess, 'run', side_effect=AssertionError('unexpected')):
                with self.assertRaisesRegex(RuntimeError, 'EXISTING_FILE_PRESERVED'):
                    tool.write_new_plist(path, tool.configurations()['user'])
            self.assertEqual(path.read_bytes(), b'existing work')

    def test_symlink_launch_job_is_refused(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'link.plist'
            path.symlink_to(Path(directory) / 'absent')
            with self.assertRaisesRegex(RuntimeError, 'EXISTING_FILE_PRESERVED'):
                tool.require_absent(path)

    def test_generated_plists_are_valid_for_macos(self):
        with tempfile.TemporaryDirectory() as directory:
            for name, value in tool.configurations().items():
                path = Path(directory) / (name + '.plist')
                tool.write_new_plist(path, value)
                self.assertEqual(path.stat().st_mode & 0o777, 0o644)


if __name__ == '__main__':
    unittest.main()
