"""Author the banked prefab layouts; meshes stay inside their occupied server cells."""
from pathlib import Path
import json

BASE=Path(__file__).resolve().parents[1]
path=BASE/'resources/expansion_blocks.json'
definitions=json.loads(path.read_text(encoding='utf-8'))

def cube(origin,size):return {'origin':origin,'size':size}
def segment(role,cubes,texture,animated=False):
    lo=[min(c['origin'][i] for c in cubes) for i in range(3)]
    hi=[max(c['origin'][i]+c['size'][i] for c in cubes) for i in range(3)]
    def bounds(a,b):return [(a[0]+8)/16,a[1]/16,(a[2]+8)/16,(b[0]+8)/16,b[1]/16,(b[2]+8)/16]
    result={'model':role,'cubes':cubes,'bounds':bounds(lo,hi),'collision_boxes':[bounds(c['origin'],[c['origin'][i]+c['size'][i] for i in range(3)]) for c in cubes],'light':0,'texture':'showcase_'+texture}
    if animated:result['active_texture']='showcase_'+texture+'_flow'
    return result

def building(name,cells,segments,ability,duration):
    assert len(cells)%16==0 and len(cells)<=128 and cells[0]==[0,0,0]
    definitions[name]={'banked':True,'part_materials':True,'layout':cells,'parts':len(cells),'mode':'powered','active_light':8,'ability':ability,'duration':duration,'amplifier':0,'segments':segments}

cells=[];segments=[]
for y in range(5):
    for z in range(5):
        for x in range(5):
            if y in (1,2,3) and x not in (0,4) and z not in (0,4):continue
            if z==0 and x==2 and y in (1,2):continue # two-block-high entry
            cells.append([x,y,-z])
            if y==0:part=segment('floor',[cube([-8,0,-8],[16,16,16])],'cottage_floor')
            elif y==4:
                h=[4,10,16,10,4][x]
                part=segment('roof_'+str(x),[cube([-8,0,-8],[16,h,16])],'cottage_roof')
                if x==4 and z>=3:part=segment('chimney',[cube([-6,0,-6],[12,16,12])],'cottage_stone')
            elif x in (0,4) and z in (0,4):part=segment('post',[cube([-8,0,-8],[16,16,16])],'cottage_timber')
            else:
                axis='z' if z in (0,4) else 'x'
                origin=[-8,0,-8] if axis=='z' else [-8,0,-8]
                if axis=='z':boxes=[cube([-8,0,-8],[16,3,4]),cube([-8,13,-8],[16,3,4]),cube([-8,3,-8],[3,10,4]),cube([5,3,-8],[3,10,4])]
                else:boxes=[cube([-8,0,-8],[4,3,16]),cube([-8,13,-8],[4,3,16]),cube([-8,3,-8],[4,10,3]),cube([-8,3,5],[4,10,3])]
                # Translate the far wall to its outer edge within the same occupied cell.
                if z==4 and axis=='z':
                    for c in boxes:c['origin'][2]+=12
                if x==4 and axis=='x':
                    for c in boxes:c['origin'][0]+=12
                role=('window_' if y==2 else 'wall_')+('front' if z==0 else 'back' if z==4 else 'left' if x==0 else 'right')
                if y!=2:boxes=[cube([-8,0,4 if z==4 else -8],[16,16,4])] if axis=='z' else [cube([4 if x==4 else -8,0,-8],[4,16,16])]
                part=segment(role,boxes,'cottage_window' if y==2 else 'cottage_timber',y==2)
            segments.append(part)
building('prism_cottage',cells,segments,'regeneration',200)

cells=[];segments=[]
for y in range(5):
    for z in range(5):
        for x in range(7):
            role=None
            if y==0:role='floor';boxes=[cube([-8,0,-8],[16,16,16])];texture='market_floor'
            elif y==4:role='canopy';boxes=[cube([-8,0,-8],[16,6,16])];texture='market_canopy'
            elif x in (0,6) and z in (0,4):role='post';boxes=[cube([-5,0,-5],[10,16,10])];texture='cottage_timber'
            elif y==1 and z in (1,3):role='counter';boxes=[cube([-8,0,-8],[16,10,16])];texture='market_counter'
            if role:
                cells.append([x,y,-z]);segments.append(segment(role,boxes,texture,role=='counter'))
building('market_hall',cells,segments,'haste',400)

cells=[];segments=[]
for y in range(6):
    for z in range(2):
        for x in range(7):
            if y<5 and x not in (0,1,5,6):continue
            cells.append([x,y,-z]);segments.append(segment('beam' if y==5 else 'pillar',[cube([-8,0,-8],[16,16,16])],'gateway_stone',True))
for y in range(5):
    for x in (-1,7):
        cells.append([x,y,0]);segments.append(segment('buttress',[cube([-4,0,-4],[8,16,8])],'gateway_stone',True))
building('grand_gateway',cells,segments,'speed',400)

path.write_text(json.dumps(definitions,indent=2)+'\n',encoding='utf-8')
print('Authored cottage (96 cells), market hall (96) and gateway (64); shared role models.')
