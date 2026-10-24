"""Original modeled armor and held gear with local animation/controller references."""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
import json,math
BASE=Path(__file__).resolve().parents[1];PACK=BASE/'resource_pack'

def write(path,obj):
    target=PACK/path;target.parent.mkdir(parents=True,exist_ok=True)
    target.write_text(json.dumps(obj,indent=2)+'\n',encoding='utf-8')

def cube(origin,size):
    return {'origin':origin,'size':size,'uv':{f:{'uv':[0,0],'uv_size':[16,16]} for f in ['north','south','east','west','up','down']}}

def bone(name,pivot=(0,0,0),cubes=(),parent=None,**extra):
    result={'name':name,'pivot':list(pivot),'cubes':[cube(o,s) for o,s in cubes],**extra}
    if parent:result['parent']=parent
    return result

def model(name,bones):
    write(Path('models/entity/'+name+'.geo.json'),{'format_version':'1.16.0','minecraft:geometry':[{'description':{'identifier':'geometry.showcase.'+name,'texture_width':64,'texture_height':64,'visible_bounds_width':4,'visible_bounds_height':4,'visible_bounds_offset':[0,1,0]},'bones':bones}]})

def skeleton():
    return [bone('waist',(0,12,0)),bone('body',(0,24,0),parent='waist'),bone('head',(0,24,0),parent='body'),bone('rightArm',(-5,22,0),parent='body'),bone('leftArm',(5,22,0),parent='body'),bone('rightLeg',(-1.9,12,0),parent='body'),bone('leftLeg',(1.9,12,0),parent='body')]

def add_cubes(bones,name,cubes):
    next(b for b in bones if b['name']==name)['cubes']=[cube(o,s) for o,s in cubes]

def idle(bones,length=2):return {'loop':True,'animation_length':length,'bones':bones}
animations={}
controllers={
 'controller.animation.showcase.armor':{'initial_state':'default','states':{'default':{'animations':['offset','idle']}}},
 'controller.animation.showcase.held':{'initial_state':'default','states':{'default':{'animations':['idle',{'first_person':'context.is_first_person'},{'third_person':'!context.is_first_person'}]}}}
}
render={'geometry':'Geometry.default','materials':[{'*':'variable.is_enchanted ? Material.enchanted : Material.default'}],'textures':['Texture.default','Texture.enchanted']}
write(Path('render_controllers/showcase_gear.json'),{'format_version':'1.8.0','render_controllers':{'controller.render.showcase.gear':render}})
write(Path('animation_controllers/showcase_gear.json'),{'format_version':'1.10.0','animation_controllers':controllers})

# A dedicated opaque 64x64 crystal-metal sheet, independent of inventory icons.
for name,base in [('prism_armor',(46,120,142)),('clockwork_staff',(115,81,43)),('wind_fan',(57,147,128)),('frost_blade',(87,144,194))]:
    image=Image.new('RGBA',(64,64),base+(255,));d=ImageDraw.Draw(image)
    for y in range(0,64,16):
        for x in range(0,64,16):
            d.rectangle((x,y,x+15,y+15),outline=(15,34,49))
            d.rectangle((x+2,y+2,x+13,y+13),outline=(117,235,221))
            d.polygon([(x+8,y+4),(x+11,y+8),(x+8,y+12),(x+5,y+8)],fill=(211,255,248))
    target=PACK/'textures/models'/f'{name}.png';target.parent.mkdir(parents=True,exist_ok=True);image.save(target)

for slot in ['helmet','chestplate','leggings','boots']:
    name='prism_'+slot;bones=skeleton()
    if slot=='helmet':
        add_cubes(bones,'head',[([-4.5,31,-4.5],[9,1.5,9]),([-4.5,24,-4.5],[1,7,9]),([3.5,24,-4.5],[1,7,9]),([-3.5,24,3.5],[7,7,1]),([-3.5,29,-4.5],[7,2,1])])
        bones.append(bone('crown',(0,33,0),[([-1.5,33,-1.5],[3,4,3])],parent='head'))
        action=idle({'crown':{'rotation':{'0.0':[0,0,0],'2.0':[0,360,0]},'position':{'0.0':[0,0,0],'1.0':[0,0.6,0],'2.0':[0,0,0]}}})
        offsets={'head':'armor_offset.helmet'};layer='helmet'
    elif slot=='chestplate':
        add_cubes(bones,'body',[([-4.5,12,-2.5],[9,12,5])])
        add_cubes(bones,'rightArm',[([-8.5,19,-2.5],[5,4,5])]);add_cubes(bones,'leftArm',[([3.5,19,-2.5],[5,4,5])])
        bones += [bone('satellite_l',(6,25,0),[([5,25,-1],[2,2,2])],parent='body'),bone('satellite_r',(-6,25,0),[([-7,25,-1],[2,2,2])],parent='body')]
        action=idle({'satellite_l':{'rotation':{'0.0':[0,0,0],'2.0':[0,360,0]},'position':{'0.0':[0,0,0],'1.0':[0,1,0],'2.0':[0,0,0]}},'satellite_r':{'rotation':{'0.0':[0,0,0],'2.0':[0,-360,0]},'position':{'0.0':[0,1,0],'1.0':[0,0,0],'2.0':[0,1,0]}}})
        offsets={'body':'armor_offset.chestplate'};layer='chest'
    elif slot=='leggings':
        add_cubes(bones,'body',[([-4.5,11,-2.5],[9,3,5])])
        add_cubes(bones,'rightLeg',[([-4.2,4,-2.4],[4.6,8,4.8])]);add_cubes(bones,'leftLeg',[([-.4,4,-2.4],[4.6,8,4.8])])
        bones += [bone('knee_l',(2,6,-3),[([1,5,-3.5],[2,2,1])],parent='leftLeg'),bone('knee_r',(-2,6,-3),[([-3,5,-3.5],[2,2,1])],parent='rightLeg')]
        action=idle({'knee_l':{'scale':{'0.0':1,'1.0':1.15,'2.0':1}},'knee_r':{'scale':{'0.0':1.15,'1.0':1,'2.0':1.15}}})
        offsets={'body':'armor_offset.leggings','leftLeg':'armor_offset.left_leg','rightLeg':'armor_offset.right_leg'};layer='leg'
    else:
        add_cubes(bones,'rightLeg',[([-4.3,0,-3],[4.8,4,6])]);add_cubes(bones,'leftLeg',[([-.5,0,-3],[4.8,4,6])])
        bones += [bone('wing_l',(4,4,0),[([4,3,-1],[4,1,3])],parent='leftLeg'),bone('wing_r',(-4,4,0),[([-8,3,-1],[4,1,3])],parent='rightLeg')]
        action=idle({'wing_l':{'rotation':{'0.0':[0,0,-12],'0.5':[0,0,12],'1.0':[0,0,-12]}},'wing_r':{'rotation':{'0.0':[0,0,12],'0.5':[0,0,-12],'1.0':[0,0,12]}}},1)
        offsets={'leftLeg':'armor_offset.left_boot','rightLeg':'armor_offset.right_boot'};layer='boot'
    model(name,bones)
    animations['animation.showcase.'+name+'.idle']=action
    animations['animation.showcase.'+name+'.offset']=idle({b:{'position':[f"query.get_root_locator_offset('{locator}', {i})" for i in range(3)]} for b,locator in offsets.items()})
    write(Path('attachables/'+name+'.json'),{'format_version':'1.20.30','minecraft:attachable':{'description':{'identifier':'showcase:'+name,'item':{'showcase:'+name:"query.is_owner_identifier_any('minecraft:player')"},'materials':{'default':'armor','enchanted':'armor_enchanted'},'textures':{'default':'textures/models/prism_armor','enchanted':'textures/misc/enchanted_actor_glint'},'geometry':{'default':'geometry.showcase.'+name},'animations':{'controller':'controller.animation.showcase.armor','idle':'animation.showcase.'+name+'.idle','offset':'animation.showcase.'+name+'.offset'},'scripts':{'parent_setup':f'variable.{layer}_layer_visible = 0.0;','animate':['controller']},'render_controllers':['controller.render.showcase.gear']}}})

for name in ['clockwork_staff','wind_fan','frost_blade']:
    root=bone('handle',(0,0,0),[([-1,-6,-1],[2,14,2])],binding='q.item_slot_to_bone_name(context.item_slot)')
    if name=='clockwork_staff':
        bones=[root,bone('orb',(0,12,0),[([-3,9,-3],[6,6,6])],parent='handle'),bone('gear',(0,12,0),[([-5,11,-1],[10,2,2]),([-1,7,-1],[2,10,2])],parent='handle')]
        action=idle({'orb':{'rotation':{'0.0':[0,0,0],'2.0':[0,360,0]},'position':{'0.0':[0,0,0],'1.0':[0,1,0],'2.0':[0,0,0]}},'gear':{'rotation':{'0.0':[0,0,0],'2.0':[0,0,360]}}})
    elif name=='wind_fan':
        blades=[]
        for angle in [0,120,240]:
            c=cube([-.8,9,-.7],[1.6,7,1.4]);c['pivot']=[0,9,0];c['rotation']=[0,0,angle];blades.append(c)
        bones=[root,{'name':'blades','parent':'handle','pivot':[0,9,0],'cubes':blades}]
        action=idle({'blades':{'rotation':{'0.0':[0,0,0],'1.0':[0,0,720]}}},1)
    else:
        root['cubes'] += [cube([-4,3,-1],[8,2,2]),cube([-1.5,5,-.6],[3,15,1.2])]
        bones=[root,bone('frost',(0,11,0),[([-2,9,-1],[4,3,2])],parent='handle')]
        action=idle({'frost':{'scale':{'0.0':1,'1.0':1.15,'2.0':1}}})
    model(name,bones)
    animations['animation.showcase.'+name+'.idle']=action
    for view,position,rotation,scale in [('first_person',[3,-3,-4],[-15,0,-10],.65),('third_person',[0,-2,0],[-10,0,0],.8)]:
        animations['animation.showcase.'+name+'.'+view]=idle({'handle':{'position':position,'rotation':rotation,'scale':scale}})
    write(Path('attachables/'+name+'.json'),{'format_version':'1.20.30','minecraft:attachable':{'description':{'identifier':'showcase:'+name,'item':{'showcase:'+name:"query.is_owner_identifier_any('minecraft:player')"},'materials':{'default':'entity_alphatest','enchanted':'entity_alphatest_glint'},'textures':{'default':'textures/models/'+name,'enchanted':'textures/misc/enchanted_item_glint'},'geometry':{'default':'geometry.showcase.'+name},'animations':{'controller':'controller.animation.showcase.held','idle':'animation.showcase.'+name+'.idle','first_person':'animation.showcase.'+name+'.first_person','third_person':'animation.showcase.'+name+'.third_person'},'scripts':{'animate':['controller']},'render_controllers':['controller.render.showcase.gear']}}})
write(Path('animations/showcase_gear.animation.json'),{'format_version':'1.8.0','animations':animations})

# A neutral, common-scale geometry preview drawn from the actual authored cubes.
entries=[]
for name,definition in json.loads((BASE/'resources/expansion_blocks.json').read_text()).items():
    cubes=[]
    for index,segment in enumerate(definition['segments']):
        dx,dy,dz=0,0,0
        if definition.get('direction')=='up':dy=index*16
        elif definition.get('direction')=='down':dy=-index*16
        elif definition.get('direction')=='north':dz=-index*16
        for c in segment['cubes']:cubes.append({'origin':[c['origin'][0]+dx,c['origin'][1]+dy,c['origin'][2]+dz],'size':c['size']})
    entries.append((name,cubes))
for name in ['prism_helmet','prism_chestplate','prism_leggings','prism_boots','clockwork_staff','wind_fan','frost_blade']:
    g=json.loads((PACK/'models/entity'/f'{name}.geo.json').read_text())['minecraft:geometry'][0]
    entries.append((name,[c for b in g['bones'] for c in b['cubes']]))
W,H=240,245;cols=5
sheet=Image.new('RGB',(W*cols,55+math.ceil(len(entries)/cols)*H),(14,25,37));draw=ImageDraw.Draw(sheet)
draw.text((18,14),'PRISM EXPANSION / GEOMETRY AT COMMON SCALE',font=ImageFont.load_default(size=19),fill=(118,240,220))
for n,(name,cubes) in enumerate(entries):
    x=(n%cols)*W;y=55+(n//cols)*H
    faces=[]
    project=lambda p:((p[0]-p[2])*.866,(p[0]+p[2])*.5-p[1])
    for data in cubes:
        o,s=data['origin'],data['size']
        def rotate(p):
            pivot=data.get('pivot',[0,0,0]);a,b,c=[p[i]-pivot[i] for i in range(3)]
            rx,ry,rz=[math.radians(v) for v in data.get('rotation',[0,0,0])]
            b,c=b*math.cos(rx)-c*math.sin(rx),b*math.sin(rx)+c*math.cos(rx)
            a,c=a*math.cos(ry)+c*math.sin(ry),-a*math.sin(ry)+c*math.cos(ry)
            a,b=a*math.cos(rz)-b*math.sin(rz),a*math.sin(rz)+b*math.cos(rz)
            return a+pivot[0],b+pivot[1],c+pivot[2]
        xx,yy,zz=o[0]+s[0],o[1]+s[1],o[2]+s[2]
        for points,color in [([(o[0],yy,o[2]),(xx,yy,o[2]),(xx,yy,zz),(o[0],yy,zz)],(135,231,212)), ([(xx,o[1],o[2]),(xx,o[1],zz),(xx,yy,zz),(xx,yy,o[2])],(47,130,144)), ([(o[0],o[1],zz),(xx,o[1],zz),(xx,yy,zz),(o[0],yy,zz)],(77,171,172))]:
            points=[rotate(p) for p in points]
            faces.append((sum(p[0]+p[2]+p[1]*.01 for p in points)/4,[project(p) for p in points],color))
    allpoints=[p for _,poly,_ in faces for p in poly]
    midx=(min(p[0] for p in allpoints)+max(p[0] for p in allpoints))/2
    midy=(min(p[1] for p in allpoints)+max(p[1] for p in allpoints))/2
    for _,poly,color in sorted(faces,key=lambda f:f[0]):draw.polygon([(x+W/2+(px-midx)*2.2,y+104+(py-midy)*2.2) for px,py in poly],fill=color,outline=(20,59,71))
    draw.text((x+10,y+211),name,font=ImageFont.load_default(size=12),fill=(215,235,237))
    draw.text((x+10,y+229),'Rest pose / 2.2 px per model pixel',font=ImageFont.load_default(size=10),fill=(125,163,176))
sheet.save(BASE/'model-preview.png')
print('Generated 4 armor rigs, 3 held rigs, 17 local animations, 2 animation controllers and model preview.')
