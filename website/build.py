"""Build a portable, dependency-light GitHub Pages documentation site from repo sources."""
from pathlib import Path
import argparse, hashlib, html, json, re, shutil, subprocess
from urllib.parse import urlsplit, unquote
import markdown

BASE=Path(__file__).resolve().parent
ROOT=BASE.parent
SHOW=ROOT/'examples/CustomiesShowcase'
REPO='https://github.com/HydroGames-dev/Customies-lotl'
REF='feat/block-traits-presets'
parser=argparse.ArgumentParser()
parser.add_argument('--output',type=Path,default=BASE/'dist')
parser.add_argument('--downloads',type=Path)
args=parser.parse_args()
OUT=args.output.resolve();OUT.mkdir(parents=True,exist_ok=True)
(OUT/'assets').mkdir(exist_ok=True)
guide=(ROOT/'docs/customies-guide.md').read_text(encoding='utf-8')
sections={m[1]:m[0] for m in re.findall(r'(^## ([^\n]+)\n.*?)(?=^## |\Z)',guide,re.M|re.S)}
def section(*names):return '\n\n'.join(sections[n] for n in names)
def plain(value):return html.unescape(re.sub('<[^>]+>',' ',value))
def esc(value):return html.escape(str(value),quote=True)
def render(source):
    source=re.sub(r'^# ([^\n]+)$',r'## \1',source,flags=re.M)
    source=re.sub(r'\]\((\.\./[^)]+|traits-and-presets\.md)\)',lambda m:']('+REPO+'/blob/'+REF+'/'+str((ROOT/'docs'/m[1]).resolve().relative_to(ROOT)).replace('\\','/')+')',source)
    return markdown.markdown(source,extensions=['tables','fenced_code','codehilite','toc','sane_lists'],extension_configs={'codehilite':{'guess_lang':False}})

pages=[
 ('index','Overview','Start here','Build beyond the block.',''),
 ('getting-started','Getting started','Start here','From install to your first custom block.',section('Install and first tour','The three layers')),
 ('walkthroughs','Walkthroughs','Build','Eight projects. Real working code.',(BASE/'walkthroughs.md').read_text(encoding='utf-8')),
 ('coding','Write your first plugin','Build','Make the server do something.',section('Create a working block','Create a working item')),
 ('states','States, rotation & traits','Build','One block. Many possibilities.',section('States, rotation and permutations')+'\n\n'+(ROOT/'docs/traits-and-presets.md').read_text(encoding='utf-8')),
 ('structures','Houses & structures','Build','Think in footprints, not giant hitboxes.',section('Structures and the house')),
 ('block-components','Block components','Reference','33 building blocks for your blocks.',section('Every block component')),
 ('item-components','Item components','Reference','42 components. Know what each one does.',section('Every item component')),
 ('recipes','Combination recipes','Reference','Turn components into features.',section('Combine components into features')),
 ('assets','Resource packs & animation','Reference','Give your code a world to live in.',section('Assets and animation')),
 ('performance','Performance & server sizing','Operate','Measure the workload. Size with evidence.',section('Performance and measurement','Server sizing')),
 ('troubleshooting','Debugging & compatibility','Operate','Find the mismatch. Fix the right layer.',section('Debugging and maintenance','Further reference')),
 ('gallery','Asset gallery','Explore','Meet the Prism Laboratory.',''),
 ('downloads','Downloads & source','Explore','Build it. Try it. Make it yours.',''),
 ('guide','Complete guide','Explore','The entire guide, in one place.',guide),
]
PAGE_MAP={p[0]:p[1] for p in pages}
search=[]
def viewer(identifier,hero=False):
    return f'''<div class="model-viewer {'hero-viewer' if hero else ''}" data-model="{esc(identifier)}">
    <canvas width="760" height="560" aria-label="Interactive geometry preview of {esc(identifier)}" role="img"></canvas>
    <div class="viewer-toolbar"><button type="button" data-rotate="-1" aria-label="Rotate model left">↶</button><button type="button" data-rotate="1" aria-label="Rotate model right">↷</button><button type="button" data-reset>Reset</button><label><input type="checkbox" data-power> Powered</label></div>
    <p class="viewer-caption">Drag to rotate · Geometry preview, not the Minecraft renderer</p></div>'''

def nav(current):
    result='';group=None
    for key,label,category,*_ in pages:
        if category!=group:result+=f'<p class="nav-group">{category}</p>';group=category
        result+=f'<a href="{key}.html" {"aria-current=page" if key==current else ""}>{esc(label)}</a>'
    return result

def layout(key,title,description,body):
    meta=esc(description)
    sidebar=nav(key)
    toc=''.join(f'<a href="#{esc(identifier)}">{esc(plain(text))}</a>' for identifier,text in re.findall(r'<h2 id="([^"]+)">(.*?)</h2>',body))
    pos=list(PAGE_MAP).index(key)
    prev=list(PAGE_MAP)[pos-1] if pos else None
    nxt=list(PAGE_MAP)[pos+1] if pos+1<len(pages) else None
    pager=(f'<a href="{prev}.html"><small>Previous</small>{esc(PAGE_MAP[prev])}</a>' if prev else '<span></span>')+(f'<a href="{nxt}.html"><small>Next</small>{esc(PAGE_MAP[nxt])} →</a>' if nxt else '')
    dochead='' if key=='index' else f'<header class="article-head"><p class="eyebrow">CUSTOMIES / {esc(title.upper())}</p><h1>{esc(title)}</h1><p class="lead">{meta}</p></header>'
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{esc(title)} · Customies</title><meta name="description" content="{meta}"><meta name="theme-color" content="#122a29"><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head>
    <body data-page="{key}"><a class="skip-link" href="#content">Skip to content</a>
    <header class="topbar"><a class="brand" href="index.html"><span class="brand-mark">C<span>◆</span></span><span>Customies<small>THE DEVELOPER FIELD GUIDE</small></span></a><div class="top-actions"><span class="version">SHOWCASE 1.4.0</span><button class="search-trigger" type="button">Search docs <kbd>Ctrl K</kbd></button><a class="source-link" href="{REPO}/tree/{REF}">GitHub ↗</a><button class="menu-toggle" aria-expanded="false" aria-controls="navigation">Menu</button></div></header>
    <div class="site-shell"><aside class="sidebar" id="navigation"><nav aria-label="Documentation">{sidebar}</nav><div class="sidebar-note"><span class="status-dot"></span> Built for Axolotl-PM<br><small>PHP behavior + Bedrock assets</small></div></aside>
    <main id="content" tabindex="-1"><div class="reading-layout"><article>{dochead}{body}<nav class="page-pager" aria-label="Page navigation">{pager}</nav><footer class="article-footer">HydroGames-dev / Customies-lotl · MIT · <a href="{REPO}/blob/{REF}/docs/customies-guide.md">Guide source ↗</a><br>Server and asset checks pass. Client visuals and version-specific behavior still require in-game testing.</footer></article><aside class="on-this-page" aria-label="On this page"><p class="eyebrow">ON THIS PAGE</p>{toc}<a class="edit-link" href="{REPO}/tree/{REF}/website">Improve this site ↗</a></aside></div></main></div>
    <dialog id="search-dialog"><div class="dialog-head"><label for="site-search">Search the field guide</label><button data-close aria-label="Close search">✕</button></div><input id="site-search" type="search" placeholder="Try: armor, collision, house…" autocomplete="off"><p id="search-status" role="status">Type to find guides, components and examples.</p><div id="search-results"></div></dialog>
    <dialog id="model-dialog"><div class="dialog-head"><h2 id="model-title">Geometry preview</h2><button data-close aria-label="Close model preview">✕</button></div><div id="model-content"></div></dialog>
    </body></html>'''

home=f'''<section class="hero"><div class="hero-copy"><p class="eyebrow">A FIELD GUIDE TO CUSTOM MINECRAFT CONTENT</p><h1>Build beyond<br>the <em>block.</em></h1><p>Custom blocks. Functional items. Structures that feel like structures. Learn how to bring them to life with Customies and PocketMine PHP.</p><div class="hero-actions"><a class="button primary" href="getting-started.html">Start building <span>→</span></a><a class="button secondary" href="gallery.html">Explore the assets ↗</a></div><div class="hero-meta"><span>OPEN SOURCE</span><span>AXOLOTL-PM</span><span>BEDROCK ASSETS</span></div></div><div class="hero-stage"><span class="specimen-tag">SPECIMEN 01 / PRISM COTTAGE</span>{viewer('prism_cottage',True)}<div class="stage-label"><span>One item. A whole house.</span><span>96 cells / 5 × 5 × 5</span></div></div></section>
<div class="stat-strip"><div><strong>75</strong><span>components explained</span></div><div><strong>139</strong><span>showcase specimens</span></div><div><strong>13</strong><span>animated materials</span></div><div><strong>∞</strong><span>ideas to build</span></div></div>
<section class="home-section"><div class="section-heading"><p class="eyebrow">YOUR FIRST PRINCIPLE</p><h2 id="three-layers">Three layers.<br>One working feature.</h2><p>A texture is not gameplay. A component is not a PHP script. Build each layer with a clear responsibility.</p></div><div class="layer-grid"><a href="block-components.html"><span class="step-number">01</span><h3>Describe it.</h3><p>States, components and permutations tell the client what the content is.</p><span>Component reference →</span></a><a href="assets.html"><span class="step-number">02</span><h3>Make it visible.</h3><p>Textures, geometry, attachments and animations give it a recognizable form.</p><span>Resource pack guide →</span></a><a href="coding.html"><span class="step-number">03</span><h3>Make it work.</h3><p>PHP owns placement, collision, effects, inventories and the rules of play.</p><span>Code your first plugin →</span></a></div></section>
<section class="path-section"><p class="eyebrow">CHOOSE YOUR NEXT BUILD</p><h2 id="learning-paths">A clear path from idea to world.</h2><div class="path-grid"><a href="walkthroughs.html#01-your-first-powered-lamp"><span>START SMALL / 01</span><h3>A lamp that really lights up.</h3><p>Register a block, add a power state, switch the material and match server lighting.</p><b>Follow the walkthrough →</b></a><a href="coding.html#create-a-working-item"><span>ADD AN ABILITY / 02</span><h3>An item with a purpose.</h3><p>Build a working wand, then explore food, weapons, worn gear and cooldowns.</p><b>Write the PHP →</b></a><a href="structures.html"><span>THINK BIGGER / 03</span><h3>A house in your hand.</h3><p>Author hollow footprints, shared model roles and grouped multi-block behavior.</p><b>Build a structure →</b></a></div></section>
<section class="notice"><strong>Built to be honest about the boundaries.</strong><p>This guide separates working server behavior from client-only metadata. Hardware allocations are starting estimates; synthetic benchmarks are not player-capacity guarantees.</p><a href="performance.html">Understand performance →</a></section>'''

# Copy authored assets without modifying them.
pack=SHOW/'resource_pack'
for relative in ['textures/blocks','textures/items','textures/models','models','attachables','animations','animation_controllers','render_controllers']:
    target=OUT/'assets/pack'/relative;target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copytree(pack/relative,target,dirs_exist_ok=True)
for relative in ['manifest.json','textures/terrain_texture.json','textures/item_texture.json','textures/flipbook_textures.json']:
    target=OUT/'assets/pack'/relative;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(pack/relative,target)
for name in ['model-preview.png','asset-preview.png','benchmark-sample.json']:
    shutil.copy2(SHOW/name,OUT/'assets'/name)

definitions=json.loads((SHOW/'resources/expansion_blocks.json').read_text(encoding='utf-8'))
models={}
for name,d in definitions.items():
    cubes=[]
    for i,seg in enumerate(d['segments']):
        delta=d.get('layout',[[0,0,0]]*len(d['segments']))[i]
        if d.get('direction')=='up':delta=[0,i,0]
        elif d.get('direction')=='down':delta=[0,-i,0]
        elif d.get('direction')=='north':delta=[0,0,-i]
        for c in seg['cubes']:cubes.append({'origin':[c['origin'][n]+16*delta[n] for n in range(3)],'size':c['size'],'texture':seg.get('texture','showcase_'+name),'active_texture':seg.get('active_texture',d.get('active_texture',''))})
    models[name]={'parts':d['parts'],'cubes':cubes,'ability':d.get('ability',''),'mode':d['mode']}
(OUT/'assets/models.json').write_text(json.dumps(models,separators=(',',':')),encoding='utf-8')

blocksrc=(SHOW/'src/ShowcaseBlock.php').read_text(encoding='utf-8')
itemsrc=(SHOW/'src/ShowcaseItem.php').read_text(encoding='utf-8')
pluginsrc=(SHOW/'src/ShowcasePlugin.php').read_text(encoding='utf-8')
coverage=json.loads((SHOW/'component-coverage.json').read_text())
blocks=re.findall(r"'([^']+)'",re.search(r'public const PLAYABLE = \[(.*?)\]',blocksrc)[1])
items=re.findall(r"'([^']+)'",re.search(r'public const PLAYABLE = \[(.*?)\]',itemsrc)[1])+re.findall(r"'([^']+)'=>",re.search(r'public const SPECIAL_ITEMS = \[(.*?)\]',pluginsrc)[1])
catalog=[(name,'block',False) for name in blocks]+[(name,'item',False) for name in items]+[('lab_block_'+name,'block',True) for name in coverage['block']]+[('lab_item_'+name,'item',True) for name in coverage['item']]
cards=''
lore_source=(SHOW/'src/SpecimenDescriptions.php').read_text(encoding='utf-8')
for name,kind,lab in catalog:
    d=definitions.get(name,{})
    metadata=f"{d['parts']} cells · {d.get('mode','')}" if d else ('Component specimen' if lab else 'Playable specimen')
    match=re.search(r"'"+re.escape(name)+r"'\s*=>\s*\[(.*?)\]",lore_source)
    lore=re.findall(r"'((?:\\.|[^'])*)'",match[1]) if match else []
    description=' '.join(lore).replace("\\'","'")
    keywords=name+' '+description+(' house' if name=='prism_cottage' else '')+(' armor' if name in ('prism_helmet','prism_chestplate','prism_leggings','prism_boots') else '')
    modelbutton=f'<button data-view="{name}">Inspect geometry ↗</button>' if name in models else ''
    cards+=f'''<article class="asset-card" data-kind="{kind}" data-lab="{str(lab).lower()}" data-name="{name}" data-search="{esc(keywords.lower())}"><div class="asset-image"><img src="assets/pack/textures/{'blocks' if kind=='block' else 'items'}/showcase_{name}.png" alt="{esc(name.replace('_',' '))} texture" width="16" height="16" loading="lazy"></div><div class="asset-card-body"><small>{kind.upper()} / {'LAB' if lab else 'PLAYABLE'}</small><h3>{esc(name.replace('_',' ').title())}</h3><code>showcase:{name}</code><p>{esc(metadata)}</p><p class="asset-description">{esc(description)}</p><div>{modelbutton}<button data-copy="/cshow give {name}" aria-label="Copy give command for {name}">Copy /give</button></div></div></article>'''
flipbooks=json.loads((pack/'textures/flipbook_textures.json').read_text())
animationcards=''.join(f'<div class="flipbook-card"><div class="flipbook" style="background-image:url(assets/pack/{f["flipbook_texture"]}.png);animation-duration:{f["ticks_per_frame"]*8/20}s" role="img" aria-label="Animated {esc(f["atlas_tile"])} texture"></div><code>{esc(f["atlas_tile"].removeprefix("showcase_"))}</code><a href="assets/pack/{f["flipbook_texture"]}.png">View sprite strip ↗</a></div>' for f in flipbooks)
gallery=f'''<p>Browse every registered specimen. Copy a command, inspect authored geometry or open the original texture. These previews explain the assets; they do not simulate Minecraft gameplay.</p><div class="gallery-controls"><label>Find a specimen<input id="gallery-query" type="search" placeholder="House, armor, reactor…"></label><label>Category<select id="gallery-kind"><option value="all">All specimens</option><option value="block">Blocks</option><option value="item">Items</option><option value="playable">Playable only</option><option value="lab">Component laboratory</option></select></label><p id="gallery-status" role="status">{len(catalog)} specimens</p></div><div class="asset-grid">{cards}</div><h2 id="animated-materials">Animated materials</h2><p>Eight frames per strip. Animation can be paused; reduced-motion preferences show a static frame.</p><button class="button secondary" id="pause-animation" aria-pressed="false">Pause animations</button><div class="flipbook-grid">{animationcards}</div><h2 id="worn-and-held-assets">Worn and held assets</h2><p>The supplied pack includes a full armor set and three held rigs with 17 animations and two animation controllers. Explore their actual files below, then check alignment in a real client.</p><div class="link-grid"><a href="{REPO}/tree/{REF}/examples/CustomiesShowcase/resource_pack/attachables">Attachables ↗</a><a href="{REPO}/tree/{REF}/examples/CustomiesShowcase/resource_pack/models/entity">Gear meshes ↗</a><a href="{REPO}/tree/{REF}/examples/CustomiesShowcase/resource_pack/animations">Animations ↗</a><a href="assets/model-preview.png">Complete geometry sheet ↗</a><a href="assets/asset-preview.png">Texture contact sheet ↗</a></div>'''

downloadbody='<p>The source and all original assets are MIT licensed. Back up existing worlds before changing registrations or state schemas.</p>'
if args.downloads:
    dest=OUT/'downloads';dest.mkdir(exist_ok=True)
    names=['CustomiesShowcase-install.zip','CustomiesShowcase.phar','Customies-Axolotl.phar','CustomiesShowcase.mcpack','SHA256SUMS.txt']
    for name in names:shutil.copy2(args.downloads/name,dest/name)
    downloadbody+='<h2 id="ready-to-install">Ready-to-install bundle</h2><p>Showcase 1.4.0 / Customies 1.5.42. The ZIP contains both PHARs, the resource pack, guide and benchmark sample.</p><div class="download-list">'+''.join(f'<a href="downloads/{name}" download><span>{name}</span><small>{(dest/name).stat().st_size/1024:.0f} KiB ↓</small></a>' for name in names)+'</div>'
downloadbody+=render(f'''## Install safely

Stop the server, replace both PHARs, update the resource pack in its existing stack,
restart and reconnect. Keep one Customies installation. See [getting started](getting-started.html).

## Source and reproducible builds

- [Full repository]({REPO}/tree/{REF})
- [Download source ZIP]({REPO}/archive/refs/heads/{REF}.zip)
- [Customies PHP]({REPO}/tree/{REF}/src)
- [Showcase plugin]({REPO}/tree/{REF}/examples/CustomiesShowcase/src)
- [Original resource pack]({REPO}/tree/{REF}/examples/CustomiesShowcase/resource_pack)
- [Guide Markdown]({REPO}/blob/{REF}/docs/customies-guide.md)
- [Benchmark sample](assets/benchmark-sample.json)

```text
python examples/CustomiesShowcase/tools/build.py --php /path/to/pmmp/php
```

Building committed assets needs Python and PHP with Phar. Regenerating textures
also needs Pillow and ffmpeg. The host's protocol dependencies determine accepted
Minecraft versions; 1.26.52/1.26.60 have not been client-tested here.
''')

for key,title,category,description,source in pages:
    body=home if key=='index' else gallery if key=='gallery' else downloadbody if key=='downloads' else render(source)
    if key=='structures':body=viewer('prism_cottage')+body+'<h2 id="more-structures">Explore the larger prefabs</h2>'+viewer('market_hall')+viewer('grand_gateway')
    if key in ('block-components','item-components'):
        kind='block' if key=='block-components' else 'item'
        body='<label class="reference-filter">Filter this reference<input type="search" id="component-filter" placeholder="Try: collision, food, durability…"></label>'+body
        body+='<h2 id="constructor-examples">Constructor examples</h2><p>These are the exact catalog expressions, not standalone plugins. Use the catalog imports and shared variables; the complete PHP file is linked with each example.</p>'
        catalogsource=(SHOW/'src/ComponentCatalog.php').read_text(encoding='utf-8').split('public static function '+('blocks' if kind=='block' else 'items')+'(): array {')[1].split('\n\t}')[0]
        for component in coverage[kind]:
            search.append({'title':component.replace('_',' ')+' / '+kind+' component','url':key+'.html#component-'+component,'text':component+' '+coverage[kind][component]})
            expr=re.search(r"'"+re.escape(component)+r"'\s*=>\s*(.*)",catalogsource)[1].rstrip(',')
            body+=f'<details id="component-{component}" class="component-example" data-component="{component}"><summary>{component}</summary>'+render('```php\n'+expr+';\n```')+f'<a href="{REPO}/blob/{REF}/examples/CustomiesShowcase/src/ComponentCatalog.php">Full catalog context ↗</a></details>'
    (OUT/(key+'.html')).write_text(layout(key,title,description,body),encoding='utf-8')
    # Page-level and heading-level records keep search results useful and small.
    search.append({'title':title,'url':key+'.html','text':re.sub(r'\s+',' ',plain(body))[:22000]})
    for anchor,label in re.findall(r'<h[23] id="([^"]+)">(.*?)</h[23]>',body):search.append({'title':title+' / '+plain(label),'url':key+'.html#'+anchor,'text':plain(label)})

(OUT/'assets/search.json').write_text(json.dumps(search,ensure_ascii=False),encoding='utf-8')
from pygments.formatters import HtmlFormatter
(OUT/'assets/syntax.css').write_text(HtmlFormatter(style='solarized-dark').get_style_defs('.codehilite'),encoding='utf-8')
for name in ['style.css','app.js','favicon.svg']:shutil.copy2(BASE/name,OUT/'assets'/name)
(OUT/'.nojekyll').write_text('')
(OUT/'404.html').write_text( '<!doctype html><html lang="en"><meta charset="utf-8"><title>Page not found · Customies</title><h1>Page not found</h1><p><a href="/Customies-lotl/">Return to the Customies field guide</a></p></html>',encoding='utf-8')
(OUT/'sitemap.xml').write_text('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join(f'<url><loc>https://hydrogames-dev.github.io/Customies-lotl/{key}.html</loc></url>' for key in PAGE_MAP)+'</urlset>')
print(f'Built {len(pages)} documentation pages, {len(catalog)} gallery specimens, {len(flipbooks)} animated materials and {len(models)} interactive models -> {OUT}')
