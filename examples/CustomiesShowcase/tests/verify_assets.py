from pathlib import Path
import json, struct, uuid
BASE=Path(__file__).resolve().parents[1];PACK=BASE/'resource_pack'
def check(ok,message):
    if not ok:raise AssertionError(message)
# Chat formatting and translated names must remain valid UTF-8 on the wire.
for path in (BASE/'src').glob('*.php'):path.read_bytes().decode('utf-8')
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
        dims=struct.unpack('>II',raw[16:24])
        check(dims==(16,16) or (key.endswith('_flow') and dims==(16,128)),f'Unexpected dimensions {path}')
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
            actual.append([(8-x-w)/16,y/16,(z+8)/16,(8-x)/16,(y+h)/16,(z+d+8)/16])
    check(sorted(actual)==sorted(expected),f'Stair collision/model mismatch: {shape}')
references=json.loads((BASE/'asset-references.json').read_text())
for key in references['geometry']:
    check(key.startswith('minecraft:') or key in models,f'Missing referenced geometry {key}')
for group,atlas in [('block_textures','terrain_texture'),('item_textures','item_texture')]:
    keys=json.loads((PACK/'textures'/f'{atlas}.json').read_text())['texture_data']
    for key in references[group]:check(key in keys,f'Missing referenced texture {key}')
print('PASS: all 10 stair models match server collision; every exported geometry/material/icon reference resolves.')

# Validate attachable -> controller/animation -> bone/texture/geometry relationships.
animations={};controllers={};renderers={}
for path in (PACK/'animations').glob('*.json'):animations.update(json.loads(path.read_text()).get('animations',{}))
for path in (PACK/'animation_controllers').glob('*.json'):controllers.update(json.loads(path.read_text()).get('animation_controllers',{}))
for path in (PACK/'render_controllers').glob('*.json'):renderers.update(json.loads(path.read_text()).get('render_controllers',{}))
known_items=set(json.loads((PACK/'textures/item_texture.json').read_text())['texture_data'])
for path in (PACK/'attachables').glob('*.json'):
    desc=json.loads(path.read_text())['minecraft:attachable']['description']
    item=desc['identifier'].split(':',1)[1]
    check('showcase_'+item in known_items,f'Unknown attachable item {item}')
    geometry=models[desc['geometry']['default']]
    bones={b['name'] for b in geometry['bones']}
    check(len(bones)==len(geometry['bones']),f'Duplicate bone in {item}')
    for bone in geometry['bones']:check('parent' not in bone or bone['parent'] in bones,f'Missing parent in {item}')
    for key,texture in desc['textures'].items():
        if not texture.startswith('textures/misc/'):check((PACK/(texture+'.png')).is_file(),f'Missing gear texture {texture}')
    for renderer in desc['render_controllers']:check(renderer in renderers,f'Missing renderer {renderer}')
    aliases=desc.get('animations',{})
    for alias,reference in aliases.items():
        if reference.startswith('controller.animation.'):
            check(reference in controllers,f'Missing controller {reference}')
            for state in controllers[reference]['states'].values():
                for entry in state.get('animations',[]):
                    for name in ([entry] if isinstance(entry,str) else entry):check(name in aliases,f'Unknown controller animation alias {name}')
        else:
            check(reference in animations,f'Missing animation {reference}')
            check(set(animations[reference].get('bones',{})) <= bones,f'Animation targets missing bones: {reference}')
    for entry in desc.get('scripts',{}).get('animate',[]):
        for alias in ([entry] if isinstance(entry,str) else entry):check(alias in aliases,f'Unknown script alias {alias}')
flipbooks=json.loads((PACK/'textures/flipbook_textures.json').read_text())
check(any(f['atlas_tile']=='showcase_reactor_flow' and f['ticks_per_frame']>0 for f in flipbooks),'Missing reactor flipbook')
print(f'PASS: {len(animations)} animations, {len(controllers)} animation controllers, all gear rigs/bones/poses and reactor flipbook.')

# Every custom animated material must have a matching, complete eight-frame strip.
expected={d['active_texture'] for d in json.loads((BASE/'resources/expansion_blocks.json').read_text()).values() if 'active_texture' in d}
expected.update(seg['active_texture'] for d in json.loads((BASE/'resources/expansion_blocks.json').read_text()).values() for seg in d['segments'] if 'active_texture' in seg)
check(expected <= {f['atlas_tile'] for f in flipbooks},'Missing state-driven flipbooks')
for f in flipbooks:
    raw=(PACK/(f['flipbook_texture']+'.png')).read_bytes()
    check(struct.unpack('>II',raw[16:24])==(16,128),'Flipbook must have eight square frames')
print(f'PASS: {len(flipbooks)} animated state-driven block textures.')
