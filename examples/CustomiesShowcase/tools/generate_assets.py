"""Deterministic, original pixel textures and JSON models. Requires Pillow and ffmpeg."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import hashlib, json, math, random, re, struct, subprocess, tempfile, uuid, wave

BASE = Path(__file__).resolve().parents[1]
PACK = BASE / 'resource_pack'
COVERAGE = json.loads((BASE / 'component-coverage.json').read_text())
BLOCKS = re.findall(r"'([^']+)'", re.search(r'public const PLAYABLE = \[(.*?)\]', (BASE/'src/ShowcaseBlock.php').read_text()).group(1))
ITEMS = re.findall(r"'([^']+)'", re.search(r'public const PLAYABLE = \[(.*?)\]', (BASE/'src/ShowcaseItem.php').read_text()).group(1)) + ['prism_berry','prism_pickaxe','prism_helmet','prism_pebble']

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
            draw.rectangle((4,4,11,11),fill=(244,235,143) if 'on' in name else (76,104,100))
            if 'on' in name: draw.rectangle((6,6,9,9),fill=white)
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
    return image

write(Path('manifest.json'), {'format_version':2,'header':{'name':'Customies Showcase - Prism Laboratory','description':'Original textures, models and audio for 98 Customies specimens.','uuid':str(uuid.uuid5(uuid.NAMESPACE_URL,'https://github.com/HydroGames-dev/Customies-lotl/showcase/resource-pack')),'version':[1,0,0],'min_engine_version':[1,26,50]},'modules':[{'type':'resources','uuid':str(uuid.uuid5(uuid.NAMESPACE_URL,'https://github.com/HydroGames-dev/Customies-lotl/showcase/resources-module')),'version':[1,0,0]}],'metadata':{'authors':['HydroGames-dev'],'license':'MIT'}})
block_names = ['showcase_'+name for name in BLOCKS] + ['showcase_machine_front','showcase_orienter_front','showcase_face_marker_front','showcase_lamp_on','showcase_totem_mid','showcase_totem_top','showcase_phase_blue','showcase_phase_ghost','showcase_lab'] + ['showcase_lab_block_'+key for key in COVERAGE['block']]
item_names = ['showcase_'+name for name in ITEMS] + ['showcase_lab_item_'+key for key in COVERAGE['item']]
for kind,names in [('blocks',block_names),('items',item_names)]:
    for name in names:
        path=PACK/'textures'/kind/(name+'.png');path.parent.mkdir(parents=True,exist_ok=True)
        texture(name,kind=='items').save(path)
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
model('cube',[bone('root',[-8,0,-8],[16,16,16])])
model('slab',[bone('root',[-8,0,-8],[16,8,16])])
model('slab_top',[bone('root',[-8,8,-8],[16,8,16])])
model('cable',[bone('post',[-2,0,-2],[4,16,4]),bone('north',[-2,6,-8],[4,4,8]),bone('south',[-2,6,0],[4,4,8]),bone('west',[-8,6,-2],[8,4,4]),bone('east',[0,6,-2],[8,4,4])])
model('stairs',[bone('base',[-8,0,-8],[16,8,16]),bone('nw',[-8,8,-8],[8,8,8]),bone('ne',[0,8,-8],[8,8,8]),bone('sw',[-8,8,0],[8,8,8]),bone('se',[0,8,0],[8,8,8])])
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
print(f'Generated {len(block_names)} block textures, {len(item_names)} item icons, 24 block models, helmet and original audio.')
