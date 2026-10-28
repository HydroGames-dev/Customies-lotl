"""Deterministic, original pixel textures and JSON models. Requires Pillow and ffmpeg."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import hashlib, json, math, random, re, struct, subprocess, tempfile, uuid, wave, sys

BASE = Path(__file__).resolve().parents[1]
PACK = BASE / 'resource_pack'
subprocess.run([sys.executable,str(BASE/'tools/generate_buildings.py')],check=True)
EXPANSION = json.loads((BASE/'resources/expansion_blocks.json').read_text())
COVERAGE = json.loads((BASE / 'component-coverage.json').read_text())
BLOCKS = re.findall(r"'([^']+)'", re.search(r'public const PLAYABLE = \[(.*?)\]', (BASE/'src/ShowcaseBlock.php').read_text()).group(1))
SPECIAL_ITEMS = re.findall(r"'([^']+)'\s*=>", re.search(r'public const SPECIAL_ITEMS = \[(.*?)\]', (BASE/'src/ShowcasePlugin.php').read_text()).group(1))
ITEMS = re.findall(r"'([^']+)'", re.search(r'public const PLAYABLE = \[(.*?)\]', (BASE/'src/ShowcaseItem.php').read_text()).group(1)) + SPECIAL_ITEMS

def write(path, value):
    target = PACK/path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, indent=2)+'\n', encoding='utf-8')

def color(name):
    digest = hashlib.sha256(name.encode()).digest()
    return tuple(65+v%155 for v in digest[:3])

def texture(name, item=False):
    seed = int.from_bytes(hashlib.sha256(name.encode()).digest()[:8], 'big')
    rng = random.Random(seed)
    base = color(name)
    if name in ['showcase_prism_helmet','showcase_prism_chestplate','showcase_prism_leggings','showcase_prism_boots']:base=(54,146,170)
    if name=='showcase_clockwork_staff':base=(115,81,43)
    if name=='showcase_wind_fan':base=(57,147,128)
    image = Image.new('RGBA',(16,16),(0,0,0,0) if item else base+(255,))
    draw = ImageDraw.Draw(image)
    light = tuple(min(255,v+55) for v in base)+(255,)
    dark = tuple(max(10,v-55) for v in base)+(255,)
    white = (218,255,249,255)
    if item:
        if any(key in name for key in ('pickaxe','wrench','inspector','wand')):
            draw.line((4,14,11,3),fill=dark,width=3)
            draw.line((4,13,10,4),fill=light,width=1)
            if 'pickaxe' in name:
                draw.line((5,2,13,4),fill=white,width=3)
                draw.line((12,4,14,7),fill=light,width=2)
            elif 'inspector' in name:
                draw.ellipse((7,0,15,8),outline=dark,width=2)
                draw.ellipse((8,1,14,7),outline=white,width=2)
            else:
                draw.polygon([(9,0),(14,3),(11,7),(7,4)],fill=base,outline=white)
        elif 'blade' in name:
            draw.line((3,14,12,2),fill=dark,width=4)
            draw.line((4,12,12,2),fill=(174,242,255),width=2)
            draw.line((3,9,8,13),fill=(115,156,189),width=2)
        elif 'repair_kit' in name:
            draw.rectangle((2,5,13,13),fill=dark,outline=light)
            draw.rectangle((5,2,10,5),outline=white)
            draw.rectangle((7,7,8,11),fill=white)
            draw.rectangle((5,9,10,9),fill=white)
        elif 'elixir' in name:
            draw.rectangle((6,1,9,4),fill=light)
            draw.rectangle((4,5,11,14),fill=(138,213,240),outline=white)
            draw.rectangle((5,9,10,13),fill=base)
        elif name.endswith('energized_ration'):
            draw.rounded_rectangle((2,4,13,13),radius=3,fill=(187,131,74),outline=(236,204,151))
            draw.line((5,5,5,8),fill=white)
            draw.line((9,5,9,8),fill=white)
        elif 'chestplate' in name:
            draw.polygon([(1,3),(5,2),(6,5),(9,5),(10,2),(14,3),(13,8),(11,8),(11,14),(4,14),(4,8),(2,8)],fill=light,outline=dark)
            draw.rectangle((7,7,8,10),fill=white)
        elif 'leggings' in name:
            draw.rectangle((3,2,12,7),fill=light,outline=dark)
            draw.rectangle((3,6,6,14),fill=base,outline=dark);draw.rectangle((9,6,12,14),fill=base,outline=dark)
        elif 'boots' in name:
            draw.rectangle((3,4,6,13),fill=light,outline=dark);draw.rectangle((9,4,12,13),fill=light,outline=dark)
            draw.rectangle((1,11,6,14),fill=base,outline=dark);draw.rectangle((9,11,14,14),fill=base,outline=dark)
        elif 'wind_fan' in name:
            draw.line((8,8,8,15),fill=dark,width=2)
            for points in [[(8,7),(4,1),(10,2)],[(8,7),(14,4),(13,10)],[(8,7),(8,13),(2,9)]]:draw.polygon(points,fill=light,outline=dark)
            draw.rectangle((7,6,9,8),fill=white)
        elif 'clockwork_staff' in name:
            draw.line((5,15,9,4),fill=dark,width=3);draw.line((5,14,9,5),fill=light)
            draw.ellipse((5,0,13,8),fill=dark,outline=white);draw.ellipse((7,2,11,6),fill=light)
        elif 'builder_compass' in name:
            draw.ellipse((1,1,14,14),fill=dark,outline=light);draw.polygon([(8,3),(11,10),(8,8),(5,10)],fill=white)
        elif 'moon_mochi' in name:
            draw.ellipse((2,4,13,13),fill=(235,215,247),outline=light);draw.ellipse((5,6,9,10),fill=(172,123,194))
        elif 'helmet' in name:
            draw.rectangle((2,3,13,11),fill=dark)
            draw.rectangle((3,2,12,8),fill=light)
            draw.rectangle((5,8,10,13),fill=(0,0,0,0))
            draw.rectangle((7,4,8,6),fill=white)
        elif 'berry' in name:
            draw.ellipse((3,5,12,14),fill=(157,61,184),outline=dark)
            draw.rectangle((7,2,8,6),fill=(71,173,97))
            draw.line((8,3,12,1),fill=(126,229,127),width=2)
            draw.rectangle((5,7,6,8),fill=white)
        elif 'fuel' in name:
            draw.polygon([(8,1),(13,7),(12,12),(8,15),(3,12),(3,7)],fill=(125,43,34),outline=dark)
            draw.polygon([(8,4),(11,9),(8,13),(5,10)],fill=(255,166,70))
        else:
            draw.polygon([(8,1),(14,6),(12,12),(7,15),(1,9),(3,4)],fill=base,outline=dark)
            draw.polygon([(8,3),(11,6),(7,10),(4,6)],fill=light)
            draw.line((3,5,7,11,11,7),fill=white,width=1)
        # Stable per-specimen pixels distinguish the laboratory icons.
        if 'lab_item_' in name:
            for x in range(4,12):
                if seed & (1 << x): draw.point((x,10),fill=white)
    else:
        for y in range(16):
            for x in range(16):
                n=rng.randrange(-12,13)
                image.putpixel((x,y),tuple(max(0,min(255,v+n)) for v in base)+(255,))
        draw.rectangle((0,0,15,15),outline=dark)
        draw.rectangle((2,2,13,13),outline=light)
        draw.line((2,8,13,8),fill=dark)
        draw.line((8,2,8,13),fill=dark)
        if 'rotor' in name or name.endswith('_front'):
            draw.rectangle((3,3,12,12),fill=dark)
            draw.polygon([(8,3),(12,8),(9,8),(9,12),(6,12),(6,8),(3,8)],fill=white)
        elif 'lamp' in name:
            draw.rectangle((4,4,11,11),fill=(244,235,143) if 'on' in name or name.endswith(('_1','_2','_3')) else (76,104,100))
            if 'on' in name or name.endswith(('_1','_2','_3')): draw.rectangle((6,6,9,9),fill=white)
        elif 'spring_pad' in name:
            draw.rectangle((3,3,12,12),fill=dark)
            for k in (4,7,10): draw.line((4,k,11,k),fill=(137,255,184) if '_on' in name else light,width=1)
        elif 'signal_lamp' in name or 'lumen_cable' in name:
            draw.rectangle((4,4,11,11),fill=(226,255,197) if name.endswith(('_1','_2','_3','_on')) else dark)
            draw.line((8,2,8,13),fill=light)
        elif 'totem' in name:
            draw.rectangle((4,5,6,7),fill=white); draw.rectangle((9,5,11,7),fill=white)
            draw.line((5,10,10,10),fill=dark,width=2)
        elif 'phase' in name:
            for n in range(-12,24,4): draw.line((n,0,n+16,16),fill=white,width=1)
        elif 'flower' in name:
            image=Image.new('RGBA',(16,16),(0,0,0,0));draw=ImageDraw.Draw(image)
            draw.line((8,15,8,6),fill=(57,164,91),width=2)
            draw.ellipse((3,0,12,9),fill=(218,89,186));draw.rectangle((6,3,9,6),fill=(255,237,148))
        elif 'lab_block' in name:
            for y in range(5,11):
                for x in range(5,11):
                    if seed & (1 << ((y-5)*6+x-5)): draw.point((x,y),fill=white)
    building_palette={'cottage_timber':((81,49,36),(162,108,64)), 'cottage_roof':((34,64,65),(77,133,124)), 'cottage_floor':((89,62,43),(184,139,86)), 'cottage_window':((54,89,94),(255,215,133)), 'cottage_stone':((64,67,80),(133,140,156)), 'market_floor':((94,72,52),(194,159,100)), 'market_canopy':((99,40,69),(235,139,176)), 'market_counter':((87,51,37),(237,187,105)), 'gateway_stone':((41,57,80),(107,196,225))}
    style=name.removeprefix('showcase_')
    if not item and style in building_palette:
        low,high=building_palette[style];image=Image.new('RGBA',(16,16),low+(255,));draw=ImageDraw.Draw(image)
        if 'timber' in style or 'floor' in style or 'counter' in style:
            for y in (0,5,10,15):draw.line((0,y,15,y),fill=high+(255,))
            for x,y in ((4,1),(11,6),(6,11)):draw.line((x,y,x,y+3),fill=high+(255,))
        elif 'window' in style:
            draw.rectangle((1,1,14,14),outline=high+(255,),width=2);draw.line((7,2,7,13),fill=high+(255,));draw.line((2,7,13,7),fill=high+(255,))
        elif 'canopy' in style:
            for x in range(0,16,8):draw.rectangle((x,0,x+3,15),fill=high+(255,))
        else:
            for y in range(0,16,4):
                draw.line((0,y,15,y),fill=high+(255,));draw.line(((y*2)%12,y,(y*2)%12,min(15,y+4)),fill=high+(255,))
    return image

write(Path('manifest.json'), {'format_version':2,'header':{'name':'Customies Showcase - Prism Laboratory','description':'Original textures, models and audio for 139 Customies specimens.','uuid':str(uuid.uuid5(uuid.NAMESPACE_URL,'https://github.com/HydroGames-dev/Customies-lotl/showcase/resource-pack')),'version':[1,4,2],'min_engine_version':[1,26,60]},'modules':[{'type':'resources','uuid':str(uuid.uuid5(uuid.NAMESPACE_URL,'https://github.com/HydroGames-dev/Customies-lotl/showcase/resources-module')),'version':[1,4,2]}],'metadata':{'authors':['HydroGames-dev'],'license':'MIT'}})
animated = {'showcase_reactor_flow':'reactor', **{d['active_texture']:key for key,d in EXPANSION.items() if 'active_texture' in d}}
segment_textures={seg[k] for d in EXPANSION.values() for seg in d['segments'] for k in ('texture','active_texture') if k in seg}
for d in EXPANSION.values():
    for seg in d['segments']:
        if 'active_texture' in seg:animated[seg['active_texture']]=seg['texture'].removeprefix('showcase_')
block_names = ['showcase_'+name for name in BLOCKS] + [*( 'showcase_'+key+'_on' for key,d in EXPANSION.items() if d['mode']=='powered' and key != 'crystal_reactor' and 'active_texture' not in d and not d.get('part_materials')), *animated, *sorted(segment_textures-set(animated)), 'showcase_mini_chess_1','showcase_mini_chess_2','showcase_signal_lamp_1','showcase_signal_lamp_2','showcase_signal_lamp_3','showcase_lumen_cable_on','showcase_spring_pad_on','showcase_machine_front','showcase_orienter_front','showcase_face_marker_front','showcase_lamp_on','showcase_totem_mid','showcase_totem_top','showcase_phase_blue','showcase_phase_ghost','showcase_lab'] + ['showcase_lab_block_'+key for key in COVERAGE['block']]
item_names = ['showcase_'+name for name in ITEMS] + ['showcase_lab_item_'+key for key in COVERAGE['item']]
for kind,names in [('blocks',block_names),('items',item_names)]:
    for name in names:
        path=PACK/'textures'/kind/(name+'.png');path.parent.mkdir(parents=True,exist_ok=True)
        texture(name,kind=='items').save(path)
flipbooks=[]
for name,style in animated.items():
    flow=Image.new('RGBA',(16,128))
    palettes={
        'thermal_forge':((68,24,20),(255,161,45)), 'molten_cell':((87,22,31),(255,211,70)),
        'tide_cell':((18,54,107),(72,224,249)), 'aurora_tile':((37,25,86),(162,255,199)),
        'runic_arch':((47,24,88),(217,148,255)), 'crystal_conservatory':((19,76,43),(153,255,109)),
        'sky_dock':((24,52,84),(162,233,255)), 'storm_engine':((36,24,77),(216,226,255)),
        'prism_cottage':((41,63,53),(255,219,146)), 'market_hall':((61,35,67),(255,166,218)),
        'grand_gateway':((21,46,91),(129,223,255)),
        'astral_beacon':((29,23,81),(243,224,154)),
        'cottage_window':((54,89,94),(255,215,133)), 'market_counter':((87,51,37),(237,187,105)),
        'gateway_stone':((41,57,80),(107,196,225)),
        'reactor':((18,72,75),(118,250,232))}
    dark,bright=palettes[style]
    for frame in range(8):
        tile=Image.new('RGBA',(16,16),dark+(255,)); pixels=tile.load()
        for y in range(16):
            for x in range(16):
                if style in ('molten_cell','thermal_forge'):
                    glow=(x*2+y+frame*2+(y//4)*3)%13<4
                elif style in ('aurora_tile','tide_cell'):
                    glow=(y+round(2*math.sin((x+frame*2)*math.pi/8))+frame)%8<3
                elif style=='storm_engine':
                    glow=(x+y*3+frame*2)%11<2 or (x-y-frame)%16==0
                elif style=='crystal_conservatory':
                    glow=(x-frame)%8==0 or (y+x//3+frame)%9<2
                else:glow=(x+y+frame*2)%8<3
                if glow:pixels[x,y]=bright+(255,)
                elif x in (0,15) or y in (0,15):pixels[x,y]=tuple(max(0,c-12) for c in dark)+(255,)
        flow.paste(tile,(0,frame*16))
    flow.save(PACK/'textures/blocks'/(name+'.png'))
    flipbooks.append({'flipbook_texture':'textures/blocks/'+name,'atlas_tile':name,'ticks_per_frame':4 if style=='tide_cell' else 3})
write(Path('textures/flipbook_textures.json'),flipbooks)
write(Path('textures/terrain_texture.json'), {'resource_pack_name':'CustomiesShowcase','texture_name':'atlas.terrain','padding':8,'num_mip_levels':4,'texture_data':{n:{'textures':'textures/blocks/'+n} for n in block_names}})
write(Path('textures/item_texture.json'), {'resource_pack_name':'CustomiesShowcase','texture_name':'atlas.items','texture_data':{n:{'textures':'textures/items/'+n} for n in item_names}})
write(Path('blocks.json'), {'format_version':[1,1,0], **{'showcase:'+name:{'sound':'stone','textures':'showcase_'+name} for name in BLOCKS}, **{'showcase:lab_block_'+key:{'sound':'stone','textures':'showcase_lab_block_'+key} for key in COVERAGE['block']}})

FACES = ['north','south','east','west','up','down']
def cube(origin,size):
    return {'origin':origin,'size':size,'uv':{f:{'uv':[0,0],'uv_size':[16,16],'material_instance':f} for f in FACES}}
def bone(name,origin,size,**extra):
    return {'name':name,'pivot':[0,0,0],'cubes':[cube(origin,size)],**extra}
def model(name,bones):
    write(Path('models/blocks/'+name+'.geo.json'),{'format_version':'1.12.0','minecraft:geometry':[{'description':{'identifier':'geometry.showcase.'+name,'texture_width':16,'texture_height':16,'visible_bounds_width':2,'visible_bounds_height':2,'visible_bounds_offset':[0,0.5,0]},'bones':bones}]})
for key,definition in EXPANSION.items():
    seen_models={}
    for i,segment in enumerate(definition['segments']):
        model_id=segment.get('model',i)
        if model_id in seen_models:
            assert seen_models[model_id]==segment['cubes'],f'Conflicting mesh role {key}:{model_id}'
            continue
        seen_models[model_id]=segment['cubes']
        model(f'{key}_{model_id}',[bone(f'piece_{n}',part['origin'],part['size']) for n,part in enumerate(segment['cubes'])])
model('cube',[bone('root',[-8,0,-8],[16,16,16])])
model('slab',[bone('root',[-8,0,-8],[16,8,16])])
model('slab_top',[bone('root',[-8,8,-8],[16,8,16])])
model('cable',[bone('post',[-2,0,-2],[4,16,4]),bone('north',[-2,6,-8],[4,4,8]),bone('south',[-2,6,0],[4,4,8]),bone('west',[-8,6,-2],[8,4,4]),bone('east',[0,6,-2],[8,4,4])])
model('stairs',[bone('base',[-8,0,-8],[16,8,16]),bone('nw',[-8,8,-8],[8,8,8]),bone('ne',[0,8,-8],[8,8,8]),bone('sw',[-8,8,0],[8,8,8]),bone('se',[0,8,0],[8,8,8])])
for half in ['bottom','top']:
    for shape,quadrants in {'none':[(0,0),(8,0)],'outer_left':[(0,0)],'outer_right':[(8,0)],'inner_left':[(0,0),(8,0),(0,8)],'inner_right':[(0,0),(8,0),(8,8)]}.items():
        bones=[bone('base',[-8,8 if half=='top' else 0,-8],[16,8,16])]
        # Geometry X is mirrored relative to server/voxel X, including corner handedness.
        bones += [bone(f'quarter_{i}',[-x,0 if half=='top' else 8,z-8],[8,8,8]) for i,(x,z) in enumerate(quadrants)]
        model('stairs_'+shape+'_'+half,bones)
model('gate_closed',[bone('panel',[-8,0,-2],[16,16,4])])
model('gate_open',[bone('left',[-8,0,-2],[2,16,4]),bone('right',[6,0,-2],[2,16,4]),bone('top',[-6,14,-2],[12,2,4])])
model('spring_pad',[bone('base',[-8,0,-8],[16,4,16])])
model('workbench',[bone('root',[-8,0,-8],[16,16,16])])
model('totem',[bone('root',[-4,0,-4],[8,16,8])])
model('bench',[bone('root',[-8,0,-8],[16,8,16])])
for i in range(16):
    # Arbitrary angles live in the geometry bone, avoiding quarter-turn transformation encoding.
    model('rotor_'+str(i),[bone('post',[-3,0,-3],[6,10,6]),bone('arrow',[-2,10,-6],[4,3,10],rotation=[0,-i*22.5,0]),bone('tip',[-4,10,2],[8,3,4],rotation=[0,-i*22.5,0])])
model('flower',[bone('x',[-5,0,-0.1],[10,12,0.2],rotation=[0,45,0]),bone('z',[-5,0,-0.1],[10,12,0.2],rotation=[0,-45,0])])
# Custom worn helmet; item icon remains useful even when a client ignores attachables.
write(Path('models/entity/helmet.geo.json'),{'format_version':'1.12.0','minecraft:geometry':[{'description':{'identifier':'geometry.showcase.helmet','texture_width':16,'texture_height':16},'bones':[{'name':'head','binding':'q.item_slot_to_bone_name(context.item_slot)','pivot':[0,24,0],'cubes':[{**cube([-4.5,24,-4.5],[9,8,9]),'inflate':0.2}]}]}]})
write(Path('attachables/prism_helmet.json'),{'format_version':'1.10.0','minecraft:attachable':{'description':{'identifier':'showcase:prism_helmet','materials':{'default':'armor','enchanted':'armor_enchanted'},'textures':{'default':'textures/items/showcase_prism_helmet','enchanted':'textures/misc/enchanted_item_glint'},'geometry':{'default':'geometry.showcase.helmet'},'render_controllers':['controller.render.showcase.helmet']}}})
write(Path('render_controllers/helmet.json'),{'format_version':'1.8.0','render_controllers':{'controller.render.showcase.helmet':{'geometry':'Geometry.default','materials':[{'*':'Material.default'}],'textures':['Texture.default']}}})

# Original two-tone chime, encoded as Ogg Vorbis for Bedrock.
sound_dir=PACK/'sounds/showcase';sound_dir.mkdir(parents=True,exist_ok=True)
with tempfile.TemporaryDirectory() as temp:
    wav=Path(temp)/'chime.wav'
    with wave.open(str(wav),'wb') as out:
        out.setnchannels(1);out.setsampwidth(2);out.setframerate(22050)
        frames=[]
        for i in range(22050):
            t=i/22050;envelope=math.sin(math.pi*min(1,t/0.015)/2)*math.exp(-4*t)
            value=int(11000*envelope*(math.sin(2*math.pi*523.25*t)+0.5*math.sin(2*math.pi*783.99*t))/1.5)
            frames.append(struct.pack('<h',value))
        out.writeframes(b''.join(frames))
    subprocess.run(['ffmpeg','-hide_banner','-loglevel','error','-y','-i',str(wav),'-c:a','libvorbis','-q:a','4',str(sound_dir/'chime.ogg')],check=True)
write(Path('sounds/sound_definitions.json'),{'format_version':'1.14.0','sound_definitions':{'showcase.chime':{'category':'record','sounds':[{'name':'sounds/showcase/chime','stream':False}]}}})

icon=Image.new('RGBA',(128,128),(14,25,37));d=ImageDraw.Draw(icon)
d.rounded_rectangle((8,8,119,119),radius=18,fill=(30,59,70),outline=(66,214,201),width=3)
d.polygon([(64,24),(96,42),(96,80),(64,100),(32,80),(32,42)],fill=(41,139,143),outline=(198,255,235))
d.polygon([(64,24),(96,42),(64,61),(32,42)],fill=(90,228,207))
d.line((64,61,64,100),fill=(198,255,235),width=2)
d.rectangle((43,60,51,68),fill=(220,255,241));d.rectangle((77,60,85,68),fill=(220,255,241))
icon.save(PACK/'pack_icon.png')
# A reviewable contact sheet with nearest-neighbor pixel scaling.
entries=[(n,False) for n in block_names]+[(n,True) for n in item_names]
columns=8;cell_w=155;cell_h=110
sheet=Image.new('RGB',(columns*cell_w,70+math.ceil(len(entries)/columns)*cell_h),(14,25,37));d=ImageDraw.Draw(sheet)
d.text((22,18),'CUSTOMIES / PRISM LABORATORY',fill=(110,245,224),font=ImageFont.load_default(size=22))
for index,(name,is_item) in enumerate(entries):
    x=(index%columns)*cell_w;y=70+(index//columns)*cell_h
    im=texture(name,is_item).resize((64,64),Image.Resampling.NEAREST)
    sheet.paste(im,(x+45,y),im)
    label=name.removeprefix('showcase_')
    for row in range(0,len(label),22):d.text((x+6,y+69+row//22*12),label[row:row+22],fill=(184,211,216),font=ImageFont.load_default(size=10))
sheet.save(BASE/'asset-preview.png')
print(f'Generated {len(block_names)} block textures, {len(item_names)} item icons, {len(list((PACK/'models/blocks').glob('*.json')))} block models, helmet and original audio.')

subprocess.run([sys.executable,str(BASE/'tools/generate_gear.py')],check=True)
