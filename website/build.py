"""Build a portable, dependency-light GitHub Pages documentation site from repo sources."""
from pathlib import Path
import argparse, hashlib, html, json, re, shutil, subprocess
from urllib.parse import urlsplit, unquote
import markdown
from reference import detail,table_descriptions
from tool_pages import PAGES as TOOL_PAGES,LANDING as TOOLS_LANDING

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

core_text=(BASE/'core-guide.md').read_text(encoding='utf-8')
core_sections={m[1]:m[0] for m in re.findall(r'(^## ([^\n]+)\n.*?)(?=^## |\Z)',core_text,re.M|re.S)}
def core(*names):return '\n\n'.join(core_sections[n] for n in names)
def library_section(*names):
    text=section(*names)
    paragraphs=text.split('\n\n')
    result=[]
    for paragraph in paragraphs:
        if ('showcase' in paragraph.lower() or 'Prism' in paragraph or 'FrostBlade' in paragraph) and not paragraph.startswith('|'):continue
        if paragraph.startswith('Classes live in') or paragraph.startswith('Names below'):continue
        result.append(paragraph.replace('showcase repair kit is a separate real action','implement a matching repair action').replace('see ice tile','match client/server friction conventions'))
    return '\n\n'.join(result)
coding_source=library_section('Create a working block','Create a working item')
states_source=library_section('States, rotation and permutations')
states_source=re.sub(r'State combinations multiply:.*?(?=\n\n|$)',"State combinations multiply: four directions and two power values create eight runtime variants. Adding sixteen charge values increases that to 128. Keep continuous counters and inventory data in appropriate server storage instead of unnecessary block states.",states_source,flags=re.S)
states_source+='\n\n## Native multi-block boundaries\n\nThe native multi-block trait handles straight chains of 2-4 parts. Only cardinal placement direction may accompany it; movement must be immovable or popped. Larger or non-linear footprints require placement, grouping and persistence logic in your own content plugin. They are not automatically provided by the native trait.'

block_source=library_section('Every block component')
item_source=library_section('Every item component')
core_walkthrough=(BASE/'walkthroughs.md').read_text(encoding='utf-8')
complete_core='\n\n'.join([core('Install Customies','Library architecture'),coding_source,states_source,block_source,item_source,core('Component combinations','Your resource pack','Performance of the library','Server sizing','Debugging your content')])
SHOWCASE_KEYS={'showcase','showcase-install','showcase-walkthroughs','structures','gallery','showcase-downloads','showcase-validation'}
pages=[
 ('index','Customies overview','Start here','The library for your custom Minecraft content.',''),
 ('getting-started','Getting started','Start here','Install Customies and create your own content plugin.',core('Install Customies','Library architecture')),
 ('walkthroughs','Customies walkthroughs','Build','Build your own blocks, items and resource pack.',core_walkthrough),
 ('coding','Write your first plugin','Build','Make the server do something.',coding_source),
 ('states','States, rotation & traits','Build','One block. Many possibilities.',states_source),
 ('block-components','Block components','Reference','33 block components in the Customies API.',block_source),
 ('item-components','Item components','Reference','42 item components in the Customies API.',item_source),
 ('recipes','Combination recipes','Reference','Turn library components into your own features.',core('Component combinations')),
 ('assets','Resource packs & animation','Reference','Create the assets for your own plugin.',core('Your resource pack')),
 ('performance','Performance & server sizing','Operate','Measure your content and size your server.',core('Performance of the library','Server sizing')),
 ('troubleshooting','Debugging & compatibility','Operate','Diagnose your own Customies content.',core('Debugging your content')),
 ('downloads','Customies downloads','Library','Get the library for your own plugin.',core('Obtain Customies')),
 ('guide','Complete Customies guide','Library','The library guide in one place.',complete_core),
 ('showcase','CustomiesShowcase overview','Example plugin','An example of what you can build with Customies.',''),
 ('showcase-install','Install the example','Example plugin','Install and tour the optional demo plugin.',section('Install and first tour')),
 ('showcase-walkthroughs','Example walkthroughs','Example plugin','Explore the implementation of the sample content.',(BASE/'showcase-walkthroughs.md').read_text(encoding='utf-8')),
 ('structures','Demo houses & structures','Demo content','Explore the example prefab system.',section('Structures and the house')),
 ('gallery','Demo asset gallery','Demo content','The Prism Laboratory example catalog.',''),
 ('showcase-downloads','Example downloads','Example content','Download the demo plugin, pack and complete bundle.',''),
 ('showcase-validation','Demo validation & benchmark','Example content','Understand the sample plugin measurements.',section('Performance and measurement')),
]
# Wiki topics are intentionally separate from the Showcase section.
for i,page in enumerate(pages):
    key,label,category,description,source=page
    if key in ('coding','states','block-components'):category='Blocks'
    elif key=='item-components':category='Items'
    elif key in ('assets','recipes'):category='Resources'
    elif key in ('performance','troubleshooting'):category='Server & compatibility'
    pages[i]=(key,label,category,description,source)
articles=[('blocks','Introduction to blocks','Blocks','Understand the complete block definition pipeline.'),('block-states','Block states','Blocks','Values, runtime combinations, cloning and persistence.'),('block-traits','Block traits','Blocks','Direction, position, connections and native multi-blocks.'),('block-permutations','Block permutations','Blocks','Compose client components with state conditions.'),('items','Introduction to items','Items','Base classes, hooks, inventory correctness and persistence.')]
for key,label,category,description in articles:
    position=next(i for i,p in enumerate(pages) if p[0]==('block-components' if category=='Blocks' else 'item-components'))
    pages.insert(position,(key,label,category,description,(BASE/'articles'/(key+'.md')).read_text(encoding='utf-8')))
extra_articles=[
 ('how-it-works','How Customies works','Understanding Customies','From registration through joining, gameplay and persistence.'),
 ('data-guide','Data & persistence','Understanding Customies','Choose storage, validate NBT and plan migrations.'),
 ('updating-customies','Update & maintain Customies','Maintenance','Port the library with the correct host, data and verification tools.'),
 ('accessibility','Reader controls & accessibility','Resources','Readable text, reduced motion, keyboard use and local preferences.'),
 ('toolchain','Development toolchain','Maintenance','Use the right tool for the layer you are changing.'),
 ('tutorial-data','Tutorial: saved charges','Tutorials','Build a charged item with validated, persisted data.'),
 ('tutorial-equipment','Tutorial: food & equipment','Tutorials','Connect proper host base classes with metadata and assets.'),
 ('tutorial-debugging','Tutorial: debug a block','Tutorials','Trace a failure through registration, data, visuals and PHP.'),
]
insert=next(i for i,p in enumerate(pages) if p[0]=='showcase')
for offset,(key,label,category,description) in enumerate(extra_articles):pages.insert(insert+offset,(key,label,category,description,(BASE/'articles'/(key+'.md')).read_text(encoding='utf-8')))
insert=next(i for i,p in enumerate(pages) if p[0]=='showcase')
pages.insert(insert,('tools','Browser toolbox','Tools','Small local tools for authoring and reviewing Customies content.',TOOLS_LANDING))
for offset,(key,(label,description,source)) in enumerate(TOOL_PAGES.items(),1):pages.insert(insert+offset,(key,label,'Tools',description,source))
api=json.loads((BASE/'api-reference.json').read_text(encoding='utf-8'))
descriptions={kind:table_descriptions(section('Every '+kind+' component')) for kind in ('block','item')}
api_fixture=(SHOW/'src/ComponentCatalog.php').read_text(encoding='utf-8')
for kind in ('block','item'):
    snippet=api_fixture.split('public static function '+('blocks' if kind=='block' else 'items')+'(): array {')[1].split('\n\t}')[0]
    for key,entry in api[kind].items():
        usage=re.search(r"'"+re.escape(key)+r"'\s*=>\s*(.*)",snippet)[1].rstrip(',')
        usage=usage.replace('$geometry',"new B\\GeometryComponent('minecraft:geometry.full_block')").replace('$materials',"[new Material('*', 'your_texture')]")
        usage=re.sub(r'showcase_[a-z_0-9]+','tutorial_texture',usage).replace('showcase:','mycontent:').replace('showcase.chime','mycontent.chime').replace('Showcase Workbench','My Workbench')
        summary,responsibility=descriptions[kind][key]
        responsibility=responsibility.replace('showcase repair kit is a separate real action','implement a server repair action').replace('see ice tile','match client/server conventions')
        pages.append((kind+'-component-'+key,key.replace('_',' ').title(), 'Blocks' if kind=='block' else 'Items',entry['name']+' / Customies PHP reference',detail(kind,key,entry,summary,responsibility,usage)))
PAGE_MAP={p[0]:p[1] for p in pages}
search=[]
def viewer(identifier,hero=False):
    return f'''<div class="model-viewer {'hero-viewer' if hero else ''}" data-model="{esc(identifier)}">
    <canvas width="760" height="560" aria-label="Interactive geometry preview of {esc(identifier)}" role="img"></canvas>
    <div class="viewer-toolbar"><button type="button" data-rotate="-1" aria-label="Rotate model left">↶</button><button type="button" data-rotate="1" aria-label="Rotate model right">↷</button><button type="button" data-reset>Reset</button><label><input type="checkbox" data-power> Powered</label></div>
    <p class="viewer-caption">Drag to rotate · Geometry preview, not the Minecraft renderer</p></div>'''

def nav(current):
    demo=current in SHOWCASE_KEYS
    buckets={}
    for key,label,category,*_ in pages:
        if key.startswith(('block-component-','item-component-')) or (key in SHOWCASE_KEYS)!=demo:continue
        parent_active=(key=='block-components' and current.startswith('block-component-')) or (key=='item-components' and current.startswith('item-component-'))
        buckets.setdefault(category,[]).append((key,label,key==current or parent_active))
    order=['Start here','Understanding Customies','Build','Tutorials','Blocks','Items','Resources','Tools','Maintenance','Server & compatibility','Library'] if not demo else list(buckets)
    result=''
    for category in order:
        entries=buckets.get(category,[])
        if not entries:continue
        opened='open' if any(active for _,_,active in entries) else ''
        result+=f'<details class="nav-folder" {opened}><summary>{esc(category)}</summary>'
        for key,label,active in entries:result+=f'<a href="{key}.html" {"aria-current=page" if active else ""}>{esc(label)}</a>'
        result+='</details>'
    other='index' if demo else 'showcase'
    label='Customies Guide / Wiki' if demo else 'CustomiesShowcase example'
    return result+f'<p class="nav-group">OTHER SECTION</p><a class="section-link" href="{other}.html">{label} ↗</a>'

def layout(key,title,description,body):
    meta=esc(description)
    sidebar=nav(key)
    toc=''.join(f'<a href="#{esc(identifier)}">{esc(plain(text))}</a>' for identifier,text in re.findall(r'<h2 id="([^"]+)">(.*?)</h2>',body))
    demo=key in SHOWCASE_KEYS
    scope='showcase' if demo else 'customies'
    brand='CustomiesShowcase' if demo else 'Customies'
    version='EXAMPLE PLUGIN 1.4.0' if demo else 'LIBRARY 1.5.42'
    scope_keys=([p[0] for p in pages if p[0].startswith(key.split('-component-')[0]+'-component-')] if '-component-' in key else [p[0] for p in pages if (p[0] in SHOWCASE_KEYS)==demo and '-component-' not in p[0]])
    pos=scope_keys.index(key)
    prev=scope_keys[pos-1] if pos else None
    nxt=scope_keys[pos+1] if pos+1<len(scope_keys) else None
    pager=(f'<a href="{prev}.html"><small>Previous</small>{esc(PAGE_MAP[prev])}</a>' if prev else '<span></span>')+(f'<a href="{nxt}.html"><small>Next</small>{esc(PAGE_MAP[nxt])} →</a>' if nxt else '')
    dochead='' if key in ('index','showcase') else f'<header class="article-head"><p class="eyebrow">{brand.upper()} / {esc(title.upper())}</p><h1>{esc(title)}</h1><p class="lead">{meta}</p></header>'
    return f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{esc(title)} · Customies</title><meta name="description" content="{meta}"><meta name="theme-color" content="#122a29"><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script><script src="assets/tools-lib.js" defer></script><script src="assets/tools.js" defer></script></head>
    <body data-page="{key}" data-scope="{scope}"><div id="reading-progress" aria-hidden="true"></div><a class="skip-link" href="#content">Skip to content</a>
    <header class="topbar"><a class="brand" href="{'showcase' if demo else 'index'}.html"><span class="brand-mark">C<span>◆</span></span><span>{brand}<small>{"EXAMPLE CONTENT PLUGIN" if demo else "GUIDE / WIKI / API"}</small></span></a><div class="top-actions"><span class="version">{version}</span><button id="reader-size" type="button" aria-label="Text size 100 percent; activate to change" title="Change reading text size">Aa</button><button id="theme-toggle" aria-label="Toggle dark theme" aria-pressed="false">◐</button><button class="search-trigger" type="button">Search docs <kbd>Ctrl K</kbd></button><a class="source-link" href="{REPO}/tree/{REF}">GitHub ↗</a><button class="menu-toggle" aria-expanded="false" aria-controls="navigation">Menu</button></div></header>
    <div class="site-shell"><aside class="sidebar" id="navigation"><nav class="section-switch" aria-label="Documentation section"><a href="index.html" {"aria-current=page" if not demo else ""}>Customies Guide</a><a href="showcase.html" {"aria-current=page" if demo else ""}>CustomiesShowcase</a></nav><nav aria-label="Documentation">{sidebar}</nav><div class="sidebar-note"><span class="status-dot"></span> Built for Axolotl-PM<br><small>PHP behavior + Bedrock assets</small></div></aside>
    <main id="content" tabindex="-1"><div class="reading-layout"><article>{dochead}{body}<nav class="page-pager" aria-label="Page navigation">{pager}</nav><footer class="article-footer">HydroGames-dev / Customies-lotl · MIT · <a href="{REPO}/blob/{REF}/{"examples/CustomiesShowcase/README.md" if demo else "website/core-guide.md"}">Section source ↗</a><br>Server and asset checks pass. Client visuals and version-specific behavior still require in-game testing.</footer></article><aside class="on-this-page" aria-label="On this page"><p class="eyebrow">ON THIS PAGE</p>{toc}<a class="edit-link" href="{REPO}/tree/{REF}/website">Improve this site ↗</a></aside></div></main></div>
    <dialog id="search-dialog"><div class="dialog-head"><label for="site-search">Search {brand}</label><button data-close aria-label="Close search">✕</button></div><input id="site-search" type="search" placeholder="Search this section…" autocomplete="off"><p id="search-status" role="status">Type to find guides, components and examples.</p><div id="search-results"></div></dialog>
    <dialog id="model-dialog"><div class="dialog-head"><h2 id="model-title">Geometry preview</h2><button data-close aria-label="Close model preview">✕</button></div><div id="model-content"></div></dialog>
    <button id="back-to-top" type="button" hidden aria-label="Back to top">↑</button></body></html>'''

demo_home=f'''<section class="hero"><div class="hero-copy"><p class="eyebrow">CUSTOMIESSHOWCASE / EXAMPLE CONTENT PLUGIN</p><h1>Build beyond<br>the <em>block.</em></h1><p>Custom blocks. Functional items. Structures that feel like structures. Explore one example plugin built using the Customies library.</p><div class="hero-actions"><a class="button primary" href="showcase-install.html">Install example <span>→</span></a><a class="button secondary" href="gallery.html">Explore the assets ↗</a></div><div class="hero-meta"><span>OPEN SOURCE</span><span>AXOLOTL-PM</span><span>BEDROCK ASSETS</span></div></div><div class="hero-stage"><span class="specimen-tag">SPECIMEN 01 / PRISM COTTAGE</span>{viewer('prism_cottage',True)}<div class="stage-label"><span>One item. A whole house.</span><span>96 cells / 5 × 5 × 5</span></div></div></section>
<div class="stat-strip"><div><strong>75</strong><span>components explained</span></div><div><strong>139</strong><span>showcase specimens</span></div><div><strong>13</strong><span>animated materials</span></div><div><strong>∞</strong><span>ideas to build</span></div></div>
<section class="home-section"><div class="section-heading"><p class="eyebrow">YOUR FIRST PRINCIPLE</p><h2 id="three-layers">Three layers.<br>One working feature.</h2><p>A texture is not gameplay. A component is not a PHP script. Build each layer with a clear responsibility.</p></div><div class="layer-grid"><a href="block-components.html"><span class="step-number">01</span><h3>Describe it.</h3><p>States, components and permutations tell the client what the content is.</p><span>Component reference →</span></a><a href="assets.html"><span class="step-number">02</span><h3>Make it visible.</h3><p>Textures, geometry, attachments and animations give it a recognizable form.</p><span>Resource pack guide →</span></a><a href="coding.html"><span class="step-number">03</span><h3>Make it work.</h3><p>PHP owns placement, collision, effects, inventories and the rules of play.</p><span>Code your first plugin →</span></a></div></section>
<section class="path-section"><p class="eyebrow">CHOOSE YOUR NEXT BUILD</p><h2 id="learning-paths">A clear path from idea to world.</h2><div class="path-grid"><a href="showcase-walkthroughs.html#01-your-first-powered-lamp"><span>START SMALL / 01</span><h3>A lamp that really lights up.</h3><p>Register a block, add a power state, switch the material and match server lighting.</p><b>Follow the walkthrough →</b></a><a href="coding.html#create-a-working-item"><span>ADD AN ABILITY / 02</span><h3>An item with a purpose.</h3><p>Build a working wand, then explore food, weapons, worn gear and cooldowns.</p><b>Write the PHP →</b></a><a href="structures.html"><span>THINK BIGGER / 03</span><h3>A house in your hand.</h3><p>Author hollow footprints, shared model roles and grouped multi-block behavior.</p><b>Build a structure →</b></a></div></section>
<section class="notice"><strong>Built to be honest about the boundaries.</strong><p>This guide separates working server behavior from client-only metadata. Hardware allocations are starting estimates; synthetic benchmarks are not player-capacity guarantees.</p><a href="showcase-validation.html">Example benchmark →</a></section>'''

home='''<header class="wiki-welcome"><p class="eyebrow">CUSTOMIES DOCUMENTATION</p><h1>Customies Wiki</h1><p>A practical reference for creating your own blocks, items and resource packs with the Customies library and PocketMine PHP.</p><a class="button primary" href="getting-started.html">Beginner’s guide →</a><a class="button secondary" href="coding.html">Create your first plugin →</a></header><div class="wiki-callout"><strong>Three layers, one working feature</strong><p>Customies defines client metadata. Your resource pack supplies visuals. PHP enforces gameplay. Each reference explains where the responsibility belongs.</p></div><h2 id="browse-topics">Browse the documentation</h2><div class="wiki-topics"><section><h3>Blocks</h3><p>Build a definition and connect its client and server behavior.</p><a href="blocks.html">Introduction to blocks</a><a href="block-components.html">33 block components</a><a href="block-states.html">Block states and persistence</a><a href="block-traits.html">Placement and connection traits</a><a href="block-permutations.html">Permutations and component overrides</a></section><section><h3>Items</h3><p>Choose the right base class and implement authoritative actions.</p><a href="items.html">Introduction to items</a><a href="item-components.html">42 item components</a><a href="coding.html#create-a-working-item">Create an ability item</a><a href="recipes.html">Food, tools, armor and other combinations</a></section><section><h3>Resources & operations</h3><p>Ship assets that match your PHP and measure the actual workload.</p><a href="assets.html">Resource packs and animation</a><a href="performance.html">Performance and server sizing</a><a href="troubleshooting.html">Troubleshooting and compatibility</a><a href="downloads.html">Get the Customies library</a></section></div><h2 id="guides-and-tools">Understand, build and maintain</h2><div class="wiki-topics"><section><h3>Understand the data flow</h3><a href="how-it-works.html">How Customies works internally</a><a href="data-guide.html">Data, NBT and persistence</a><a href="tutorial-data.html">Tutorial: a charged item</a></section><section><h3>Maintain your own fork</h3><a href="updating-customies.html">Update Customies yourself</a><a href="toolchain.html">Development toolchain</a><a href="tutorial-debugging.html">Diagnose a broken block</a></section><section><h3>Use the toolbox</h3><a href="tools.html">All browser tools</a><a href="tool-states.html">State calculator</a><a href="tool-boxes.html">Box converter</a><a href="tool-starter.html">Plugin starter generator</a></section></div><h2 id="how-to-use-this-wiki">How to use this wiki</h2><p>Start with installation and the complete PHP tutorial. Use the component indexes to find a feature, then open its dedicated reference for constructor parameters, defaults, serialized values, combinations and common mistakes.</p><p>Native Bedrock documentation and the PHP wrapper are different interfaces. Check the target host/client before adopting experimental metadata.</p><div class="wiki-callout"><strong>CustomiesShowcase is a separate example</strong><p>The demo plugin’s catalog, houses, assets, commands and bundle live in their own section.</p><a href="showcase.html">Explore the example plugin →</a></div><h2 id="external-references">External references</h2><p>Information architecture inspired by <a href="https://wiki.bedrock.dev/">Bedrock Wiki</a>. This is an independent Customies guide, not an official Bedrock Wiki or Microsoft site.</p><p><a href="https://learn.microsoft.com/en-us/minecraft/creator/documents/intro-block-traits?view=minecraft-bedrock-stable">Microsoft: block traits</a> · <a href="https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/blockcomponentslist?view=minecraft-bedrock-stable">Block component reference</a></p>'''
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
restart and reconnect. Keep one Customies installation. See [installing the example](showcase-install.html).

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
    body=home if key=='index' else demo_home if key=='showcase' else gallery if key=='gallery' else downloadbody if key=='showcase-downloads' else render(source)
    if key=='downloads' and args.downloads:body+='<p><a class="button primary" href="downloads/Customies-Axolotl.phar" download>Download Customies-Axolotl.phar ↓</a></p><p><a href="'+REPO+'/tree/'+REF+'/src">Library source ↗</a></p>'
    if key=='structures':body=viewer('prism_cottage')+body+'<h2 id="more-structures">Explore the larger prefabs</h2>'+viewer('market_hall')+viewer('grand_gateway')
    if key in ('block-components','item-components'):
        kind='block' if key=='block-components' else 'item'
        body='<p class="wiki-callout">This reference documents the wrappers shipped by this Customies branch. Native Bedrock has additional components; a Microsoft entry is not a promise that the host or this library implements its entire feature. Open a component for its constructor, helper methods and server responsibilities.</p>'+body
        body='<label class="reference-filter">Filter this reference<input type="search" id="component-filter" placeholder="Try: collision, food, durability…"></label>'+body
        body+='<h2 id="constructor-examples">Constructor examples</h2><p>Use B as the block-component namespace alias and I as the item-component alias. These are constructor expressions to add in your own content class; the library implementation is linked below.</p>'
        catalogsource=(SHOW/'src/ComponentCatalog.php').read_text(encoding='utf-8').split('public static function '+('blocks' if kind=='block' else 'items')+'(): array {')[1].split('\n\t}')[0]
        for component in coverage[kind]:
            search.append({'title':component.replace('_',' ')+' / '+kind+' component','url':key+'.html#component-'+component,'text':component+' '+coverage[kind][component]})
            expr=re.search(r"'"+re.escape(component)+r"'\s*=>\s*(.*)",catalogsource)[1].rstrip(',')
            expr=expr.replace('$geometry',"new B\\GeometryComponent('minecraft:geometry.full_block')").replace('$materials',"[new Material('*', 'your_texture')]")
            expr=re.sub(r"showcase_[a-z_0-9]+",'tutorial_texture',expr).replace('showcase:','mycontent:').replace('showcase.chime','mycontent.chime').replace('Showcase Workbench','My Workbench')
            implementation=re.search(r'new [BI]\\(\w+)',expr)[1]
            body+=f'<details id="component-{component}" class="component-example" data-component="{component}"><summary>{component}</summary>'+render('```php\n'+expr+';\n```')+f'<a href="{REPO}/blob/{REF}/src/{kind}/component/{implementation}.php">Library implementation ↗</a></details>'
    if key in ('block-components','item-components'):
        kind='block' if key=='block-components' else 'item'
        for component in api[kind]:
            body=body.replace('<td><code>'+component+'</code></td>','<td><a href="'+kind+'-component-'+component+'.html"><code>'+component+'</code></a></td>')
            body=body.replace('<summary>'+component+'</summary>','<summary>'+component+'</summary><p><a href="'+kind+'-component-'+component+'.html">Full parameter, behavior and serialization reference →</a></p>')
    (OUT/(key+'.html')).write_text(layout(key,title,description,body),encoding='utf-8')
    # Page-level and heading-level records keep search results useful and small.
    search.append({'title':title,'url':key+'.html','text':re.sub(r'\s+',' ',plain(body))[:22000]})
    for anchor,label in re.findall(r'<h[23] id="([^"]+)">(.*?)</h[23]>',body):search.append({'title':title+' / '+plain(label),'url':key+'.html#'+anchor,'text':plain(label)})

for record in search:record['scope']='showcase' if record['url'].split('.html')[0] in SHOWCASE_KEYS else 'customies'
for scope in ('customies','showcase'):
    (OUT/f'assets/search-{scope}.json').write_text(json.dumps([r for r in search if r['scope']==scope],ensure_ascii=False),encoding='utf-8')
(OUT/'assets/search.json').write_text(json.dumps(search,ensure_ascii=False),encoding='utf-8')
from pygments.formatters import HtmlFormatter
(OUT/'assets/syntax.css').write_text(HtmlFormatter(style='solarized-dark').get_style_defs('.codehilite'),encoding='utf-8')
shutil.copy2(BASE/'api-reference.json',OUT/'assets/wrapper-api.json')
for name in ['style.css','app.js','favicon.svg','tools-lib.js','tools.js']:shutil.copy2(BASE/name,OUT/'assets'/name)
(OUT/'.nojekyll').write_text('')
(OUT/'404.html').write_text( '<!doctype html><html lang="en"><meta charset="utf-8"><title>Page not found · Customies</title><h1>Page not found</h1><p><a href="/Customies-lotl/">Return to the Customies field guide</a></p></html>',encoding='utf-8')
(OUT/'sitemap.xml').write_text('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join(f'<url><loc>https://hydrogames-dev.github.io/Customies-lotl/{key}.html</loc></url>' for key in PAGE_MAP)+'</urlset>')
# Keep the static snapshot byte-consistent across Windows and Linux checkouts.
for path in OUT.rglob('*'):
    if path.is_file() and path.suffix in ('.html','.json','.css','.js','.svg','.xml','.txt'):
        data=path.read_bytes();path.write_bytes(data.replace(b'\r\n',b'\n'))
print(f'Built {len(pages)} documentation pages, {len(catalog)} gallery specimens, {len(flipbooks)} animated materials and {len(models)} interactive models -> {OUT}')
