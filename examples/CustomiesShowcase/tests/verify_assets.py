from pathlib import Path
import json, struct, uuid
BASE=Path(__file__).resolve().parents[1];PACK=BASE/'resource_pack'
def check(ok,message):
    if not ok:raise AssertionError(message)
for path in PACK.rglob('*.json'):
    json.loads(path.read_text(encoding='utf-8'))
manifest=json.loads((PACK/'manifest.json').read_text())
check(manifest['format_version']==2,'Manifest format')
check(uuid.UUID(manifest['header']['uuid'])!=uuid.UUID(manifest['modules'][0]['uuid']),'Pack and module UUID must differ')
for atlas in ['terrain_texture','item_texture']:
    data=json.loads((PACK/'textures'/f'{atlas}.json').read_text())
    for key,value in data['texture_data'].items():
        path=PACK/(value['textures']+'.png')
        check(path.is_file(),f'Missing texture {key}')
        raw=path.read_bytes()
        check(raw[:8]==b'\x89PNG\r\n\x1a\n',f'Invalid PNG {path}')
        check(struct.unpack('>II',raw[16:24])==(16,16),f'Unexpected dimensions {path}')
models={}
for path in (PACK/'models').rglob('*.json'):
    for geometry in json.loads(path.read_text())['minecraft:geometry']:
        key=geometry['description']['identifier']
        check(key not in models,f'Duplicate geometry: {key}')
        models[key]=geometry
for n in range(16):
    key=f'geometry.showcase.rotor_{n}'
    check(key in models,f'Missing rotation {n}')
    check(models[key]['bones'][1]['rotation'][1]==-22.5*n,f'Wrong rotation {n}')
cable={b['name'] for b in models['geometry.showcase.cable']['bones']}
check({'north','south','west','east'}<=cable,'Missing connection bones')
check((PACK/'sounds/showcase/chime.ogg').read_bytes()[:4]==b'OggS','Missing Ogg audio')
print(f'PASS: resource pack JSON, atlases, PNGs, {len(models)} geometries, 16 rotations, connection bones, Ogg and UUIDs.')
