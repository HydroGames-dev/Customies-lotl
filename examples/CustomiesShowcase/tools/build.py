"""Package committed assets; PHP with Phar is needed, no Python packages required."""
from pathlib import Path
import argparse, hashlib, json, subprocess, zipfile
BASE=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser()
parser.add_argument('--php',default='php')
parser.add_argument('--output',type=Path,default=BASE/'dist')
args=parser.parse_args()
out=args.output.resolve();out.mkdir(parents=True,exist_ok=True)
subprocess.run([args.php,'-dphar.readonly=0',str(BASE/'tools/build.php'),str(out)],check=True)
pack=out/'CustomiesShowcase.mcpack'
with zipfile.ZipFile(pack,'w',zipfile.ZIP_DEFLATED) as archive:
    for file in sorted((BASE/'resource_pack').rglob('*')):
        if file.is_file():archive.write(file,file.relative_to(BASE/'resource_pack').as_posix())
with zipfile.ZipFile(out/'CustomiesShowcase-install.zip','w',zipfile.ZIP_DEFLATED) as archive:
    for name in ['CustomiesShowcase.phar','Customies-Axolotl.phar']:archive.write(out/name,'plugins/'+name)
    archive.write(pack,'resource_packs/'+pack.name)
    for name in ['README.md','component-coverage.json','asset-preview.png','model-preview.png','LICENSE','VALIDATION.md']:
        archive.write(BASE/name,name)
    archive.writestr('resource_packs/resource_packs.yml.example','force_resources: true\nresource_stack:\n  - CustomiesShowcase.mcpack\n')
files=[out/name for name in ['CustomiesShowcase.phar','Customies-Axolotl.phar','CustomiesShowcase.mcpack','CustomiesShowcase-install.zip']]
(out/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(f.read_bytes()).hexdigest()+'  '+f.name+'\n' for f in files),encoding='utf-8')
for f in files:print(f'{f.name}: {f.stat().st_size:,} bytes')
