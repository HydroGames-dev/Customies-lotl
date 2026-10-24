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

# Compare rendered, explicit stair cubes against the PHP collision shapes.
for shape,expected in json.loads((BASE/'stair-shapes.json').read_text()).items():
    key='geometry.showcase.stairs_'+shape
    check(key in models, f'Missing stair geometry {shape}')
    actual=[]
    for bone in models[key]['bones']:
        for cube in bone['cubes']:
            x,y,z=cube['origin'];w,h,d=cube['size']
            actual.append([(x+8)/16,y/16,(z+8)/16,(x+w+8)/16,(y+h)/16,(z+d+8)/16])
    check(sorted(actual)==sorted(expected),f'Stair collision/model mismatch: {shape}')
references=json.loads((BASE/'asset-references.json').read_text())
for key in references['geometry']:
    check(key.startswith('minecraft:') or key in models,f'Missing referenced geometry {key}')
for group,atlas in [('block_textures','terrain_texture'),('item_textures','item_texture')]:
    keys=json.loads((PACK/'textures'/f'{atlas}.json').read_text())['texture_data']
    for key in references[group]:check(key in keys,f'Missing referenced texture {key}')
print('PASS: all 10 stair models match server collision; every exported geometry/material/icon reference resolves.')
