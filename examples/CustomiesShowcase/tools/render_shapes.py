"""Render the tested stair fixtures as a standalone proof sheet (requires Pillow)."""
from pathlib import Path
import argparse, json, math
from PIL import Image, ImageDraw, ImageFont

ROOT=Path(__file__).resolve().parents[3]
parser=argparse.ArgumentParser()
parser.add_argument('--fixtures',type=Path,default=ROOT/'website/shape-fixtures.json')
parser.add_argument('--output',type=Path,default=ROOT/'website/preview/shapes-1.8.0.png')
args=parser.parse_args()
fixtures=json.loads(args.fixtures.read_text(encoding='utf-8'))
image=Image.new('RGB',(1600,1110),'#122a29');draw=ImageDraw.Draw(image)
def font(size):
    for candidate in ['C:/Windows/Fonts/segoeui.ttf','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf']:
        if Path(candidate).exists():return ImageFont.truetype(candidate,size)
    return ImageFont.load_default(size=size)
draw.text((40,28),'Customies 1.8.0 / shape verification',font=font(32),fill='#e8f4e7')
draw.text((40,78),'Actual client meshes = PHP collision = vanilla occupancy in all 40 variants',font=font(20),fill='#b4d5c4')
draw.text((40,113),'North 0°  /  West 90°  /  South 180°  /  East 270°   ·   Mirrored geometry X',font=font(16),fill='#90b7aa')
for col,(half,direction) in enumerate((h,d) for h in ['bottom','top'] for d in ['north','west','south','east']):
    draw.text((177+col*175,169),half.title()+' / '+direction.title(),font=font(15),fill='#c9edc4')
shapes=['none','outer_left','outer_right','inner_left','inner_right']
for row,shape in enumerate(shapes):
    y=215+row*167
    draw.text((28,y+51),shape.replace('_','\n'),font=font(18),fill='#e8f4e7')
    for col,(half,direction) in enumerate((h,d) for h in ['bottom','top'] for d in ['north','west','south','east']):
        f=next(f for f in fixtures if (f['shape'],f['half'],f['direction'])==(shape,half,direction))
        def occupancy(boxes):return [any(a<x<d and b<yy<e and c<z<ff for a,b,c,d,e,ff in boxes) for x in [.25,.75] for yy in [.25,.75] for z in [.25,.75]]
        assert occupancy(f['client'])==occupancy(f['server'])==occupancy(f['vanilla']),f
        cx=253+col*175;cy=y+67
        draw.rounded_rectangle((cx-81,y-5,cx+81,y+146),radius=8,fill='#193b36')
        def project(p):
            x,yy,z=p;return (cx+(x-z)*54,cy+(x+z-1)*26-(yy-.5)*66)
        faces=[]
        for x,yy,z,X,Y,Z in f['client']:
            vertices=[(x,yy,z),(X,yy,z),(X,yy,Z),(x,yy,Z),(x,Y,z),(X,Y,z),(X,Y,Z),(x,Y,Z)]
            for indices,color in [([4,5,6,7],'#bce4cb'),([1,5,6,2],'#4c9b89'),([2,6,7,3],'#32776a')]:
                points=[vertices[i] for i in indices]
                faces.append((sum(p[0]+p[2]+p[1]*.01 for p in points)/4,points,color))
        for _,points,color in sorted(faces,key=lambda f:f[0]):
            draw.polygon([project(p) for p in points],fill=color,outline='#173f38',width=2)
        draw.text((cx-26,y+119),'PASS',font=font(13),fill='#c9edc4')
draw.text((40,1065),'Fixture: website/shape-fixtures.json · Generated from actual assets and host APIs · In-game appearance still needs a Bedrock client.',font=font(17),fill='#b4d5c4')
args.output.parent.mkdir(parents=True,exist_ok=True);image.save(args.output)
print(f'Rendered all 40 tested variants -> {args.output}')
