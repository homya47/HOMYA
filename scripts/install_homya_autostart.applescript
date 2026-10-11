-- Stage the reviewed installer as the current user: privileged AppleScript
-- helpers cannot read Documents on this Mac because of its privacy controls.
set taskDirectory to do shell script "/usr/bin/mktemp -d /private/tmp/homya-autostart-admin.XXXXXX"
set installerCopy to taskDirectory & "/install_homya_autostart.py"
set installerSource to "/Users/akm/Documents/ChatGPT/HOMYA/scripts/install_homya_autostart.py"
do shell script "/bin/cp " & quoted form of installerSource & " " & quoted form of installerCopy & " && /bin/chmod 600 " & quoted form of installerCopy & " && /usr/bin/cmp " & quoted form of installerSource & " " & quoted form of installerCopy
do shell script "/usr/bin/env PYTHONDONTWRITEBYTECODE=1 /usr/bin/python3 " & quoted form of installerCopy & " --install-system" with administrator privileges
