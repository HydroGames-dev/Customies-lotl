"""Start independent Axolotl processes against one isolated test world's saved data."""
from pathlib import Path
import argparse, json, subprocess, tempfile

parser = argparse.ArgumentParser()
parser.add_argument('host', type=Path)
parser.add_argument('--php', default='php')
parser.add_argument('--cycles', type=int, default=12)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
host = args.host.resolve()
php = str(Path(args.php).resolve()) if Path(args.php).exists() else args.php
if not (host/'vendor/autoload.php').is_file() or not 2 <= args.cycles <= 100:
    parser.error('Supply an installed host and 2-100 cycles')
data = Path(tempfile.mkdtemp(prefix='customies-server-restarts-')).resolve()
(data/'plugins').mkdir()
(data/'players').mkdir()
(data/'server.properties').write_text('server-ip=127.0.0.1\nserver-port=19265\nenable-ipv6=off\nenable-query=off\nlevel-name=restart-test\nlevel-type=FLAT\nview-distance=2\nmax-players=1\nauto-save=on\n', encoding='utf-8')
(data/'pocketmine.yml').write_text('settings:\n  language: eng\n  async-workers: 2\n  enable-dev-builds: true\ntransport:\n  name: raknet\nconsole:\n  enable-input: false\nanonymous-statistics:\n  enabled: false\nauto-updater:\n  enabled: false\n', encoding='utf-8')
subprocess.run([php, '-dphar.readonly=0', str(root/'examples/CustomiesShowcase/tools/build.php'), str(data/'plugins')], check=True, capture_output=True)
builder = data/'build-probe.php'
builder.write_text('<?php\n$p = new Phar($argv[1]); $p->setStub("<?php __HALT_COMPILER();"); $p->addFromString("plugin.yml", "name: RestartProbe\\nmain: customiesdevs\\\\restarttest\\\\RestartProbe\\nsrc-namespace-prefix: customiesdevs\\\\restarttest\\nversion: 1.0.0\\napi: 5.51.0\\ndepend: [Customies-Axolotl, CustomiesShowcase]\\n"); $p->addFile($argv[2], "src/RestartProbe.php");', encoding='utf-8')
subprocess.run([php, '-dphar.readonly=0', str(builder), str(data/'plugins/RestartProbe.phar'), str(root/'tests/restart-server-probe.php')], check=True, capture_output=True)
print('Isolated restart test data:', data, flush=True)
for cycle in range(args.cycles):
    # Explicit plugin path: Axolotl's default is relative to cwd, not --data.
    with (data/f'cycle-{cycle:02}.log').open('w', encoding='utf-8') as log:
        result = subprocess.run([php, str(host/'src/PocketMine.php'), '--no-wizard', '--disable-ansi', '--data='+str(data), '--plugins='+str(data/'plugins')], cwd=host, stdout=log, stderr=subprocess.STDOUT, timeout=90)
    output = (data/f'cycle-{cycle:02}.log').read_text(encoding='utf-8')
    marker = f'RESTART PASS {cycle}:'
    if result.returncode != 0 or marker not in output or 'CRITICAL' in output or 'item will be replaced by AIR' in output:
        raise RuntimeError(f'Restart {cycle} failed; inspect {data}/cycle-{cycle:02}.log\n'+output[-9000:])
    if json.loads((data/'restart-cycle.json').read_text()) != cycle+1:
        raise RuntimeError('Probe did not commit cycle result')
    print(f'PASS server process {cycle+1}/{args.cycles}', flush=True)
print('Native server restart regression passed; no Bedrock client was connected. Logs:', data)
