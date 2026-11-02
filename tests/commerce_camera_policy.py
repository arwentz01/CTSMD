"""Real Apache header regression on a temporary loopback-only static fixture."""
import grp
import os
from pathlib import Path
import pwd
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BINARY = os.environ.get('CTSMD_APACHE_BIN') or ('/Applications/MAMP/Library/bin/httpd' if Path('/Applications/MAMP/Library/bin/httpd').is_file() else shutil.which('apache2') or shutil.which('httpd') or '')
MODULES = Path(os.environ.get('CTSMD_APACHE_MODULES') or ('/usr/lib/apache2/modules' if Path('/usr/lib/apache2/modules').is_dir() else '/Applications/MAMP/Library/modules'))
if not Path(BINARY).is_file():
    raise SystemExit('Apache is required; set CTSMD_APACHE_BIN and CTSMD_APACHE_MODULES.')
compiled = subprocess.check_output([BINARY, '-l'], text=True)
with tempfile.TemporaryDirectory(prefix='ctsmd-camera-policy-') as tmp:
    base = Path(tmp)
    base.chmod(0o755)
    www = base / 'www'
    www.mkdir()
    (www / 'front.php').write_text('Synthetic Apache header fixture; no application or accounts.\n')
    shutil.copyfile(ROOT / '.htaccess', www / '.htaccess')
    with socket.socket() as probe:
        probe.bind(('127.0.0.1', 0))
        port = probe.getsockname()[1]
    modules = [('authz_core', 'mod_authz_core.c'), ('dir', 'mod_dir.c'), ('mime', 'mod_mime.c'), ('headers', 'mod_headers.c'), ('rewrite', 'mod_rewrite.c'), ('unixd', 'unixd.c')]
    lines = [f'ServerRoot "{base}"', f'PidFile "{base}/httpd.pid"', f'Listen 127.0.0.1:{port}', 'ServerName 127.0.0.1', f'ErrorLog "{base}/error.log"', 'LogLevel warn']
    if not any(name in compiled for name in ('event.c', 'prefork.c', 'worker.c')):
        lines.append(f'LoadModule mpm_event_module "{MODULES}/mod_mpm_event.so"')
    for name, static in modules:
        if static not in compiled:
            lines.append(f'LoadModule {name}_module "{MODULES}/mod_{name}.so"')
    username = pwd.getpwuid(os.getuid()).pw_name if os.getuid() else 'www-data'
    group = grp.getgrgid(os.getgid()).gr_name if os.getuid() else 'www-data'
    lines.extend([f'User {username}', f'Group {group}', 'TypesConfig /dev/null', f'DocumentRoot "{www}"', f'<Directory "{www}">', 'AllowOverride All', 'Require all granted', '</Directory>'])
    config = base / 'httpd.conf'
    config.write_text('\n'.join(lines) + '\n')
    syntax = subprocess.run([BINARY, '-f', str(config), '-t'], capture_output=True, text=True)
    if syntax.returncode:
        raise RuntimeError(syntax.stdout + syntax.stderr)
    process = subprocess.Popen([BINARY, '-f', str(config), '-X'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                if process.poll() is not None:
                    raise RuntimeError((base / 'error.log').read_text())
                time.sleep(.05)
        cases = [('/admin/commerce?tab=checkin', True), ('/admin/commerce?tab=checkin&id=2', True), ('/admin/commerce?id=2&tab=checkin', True), ('/nested/admin/commerce/?tab=checkin&id=2', True), ('/admin/commerce?tab=select', False), ('/admin/commerce?tab=configure', False), ('/admin/commerce?tab=checkin-other', False), ('/admin/commerce?other=tab=checkin', False), ('/fakeadmin/commerce?tab=checkin', False), ('/admin/commerce/other?tab=checkin', False), ('/login', False), ('/', False)]
        opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
        for path, allowed in cases:
            with opener.open(f'http://127.0.0.1:{port}{path}', timeout=5) as response:
                policies = response.headers.get_all('Permissions-Policy')
                expected = f'camera={"(self)" if allowed else "()"}, microphone=(), geolocation=()'
                if policies != [expected]:
                    raise AssertionError(f'{path}: {policies!r} != {[expected]!r}')
            print(f'PASS {path}: camera {"self" if allowed else "denied"}')
        print(f'PASS {len(cases)} real Apache policy cases, including front-controller rewrites; microphone/geolocation stay denied')
    finally:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
