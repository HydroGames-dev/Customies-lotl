"""Customies authoring tools; all inputs stay in the browser."""
import html, json

INTRO='''<div class="wiki-callout"><strong>Make something with Customies</strong><p>These tools run locally. Create PHP, inspect shapes and bring existing JSON into your own plugin. Target: Customies 1.8.0, Axolotl API 5.51.0 and Minecraft 26.60.</p></div>'''

def select(name,label,values):
    return f'<label>{label}<select name="{name}">'+''.join(f'<option value="{key}">{label}</option>' for key,label in values)+'</select></label>'

def form(kind,fields,canvas=''):
    return f'''<form class="authoring-form" data-authoring="{kind}">{fields}<div class="authoring-actions"><button class="button primary" type="submit" disabled>Build preview</button><button type="button" class="button secondary" data-reset>Reset example</button></div><p class="authoring-status" role="status" aria-live="polite">Loading local tool…</p>{canvas}<div class="authoring-result" hidden><div class="authoring-actions"><label>Generated file<select data-files aria-label="Generated file"></select></label><button type="button" class="button secondary" data-authoring-copy>Copy file</button><button type="button" class="button secondary" data-download>Download ZIP</button></div><pre tabindex="0" data-code></pre><div data-report></div></div></form><noscript><p>Enable JavaScript to use this tool. Your input stays on your device.</p></noscript>'''

def scene():
    return '''<div class="shape-stage"><canvas width="800" height="480" tabindex="0" aria-label="Interactive block shape preview. Drag or use arrow keys to orbit; plus and minus zoom."></canvas><div class="shape-legend"><span class="collision-key">Collision / client mesh</span><span class="selection-key">Selection / server</span><span class="vanilla-key">Vanilla reference</span></div></div><p class="shape-hint">Drag to orbit · Arrow keys to turn · + / − to zoom · Double-click to reset. X = east, Z = south; N marks north.</p>'''

SAMPLE={'format_version':'1.26.60','minecraft:block':{'description':{'identifier':'mycontent:signal_lamp','states':{'mycontent:lit':[False,True]},'traits':{'minecraft:placement_direction':{'enabled_states':['minecraft:cardinal_direction']}}},'components':{'minecraft:geometry':'minecraft:geometry.full_block','minecraft:material_instances':{'*':{'texture':'signal_lamp','render_method':'opaque'}},'minecraft:light_emission':0,'minecraft:collision_box':True,'minecraft:selection_box':True},'permutations':[{'condition':"q.block_state('mycontent:lit') == true",'components':{'minecraft:light_emission':15}}]}}

PAGES={
'tool-generator':('Item & block generator','Build a content class, registration plugin and resource-pack scaffolding.',INTRO+'''
## Start with a working pattern

Choose a real PocketMine base: food restores hunger, durable items expose durability,
armor uses an armor slot, and slabs merge into a double block. The output includes
the registration entry point, plugin manifest, resource-pack manifest and atlas.
Supply the PNG named in the report. Armor also needs your attachables and textures.

'''+form('generator','''<div class="authoring-grid">'''+select('kind','Content type',[('block','Block'),('item','Item')])+select('preset','Pattern',[('cube','Solid cube'),('cardinal','Four-direction block'),('slab','Merging slab')])+'''<label>Identifier<input name="identifier" value="mycontent:prism" required maxlength="100"></label><label>PHP class<input name="className" value="PrismContent" required maxlength="48"></label><label>Display name<input name="name" value="Prism" required maxlength="100"></label><label>Texture key<input name="texture" value="mycontent_prism" required maxlength="100"></label><label data-stack hidden>Stack size<input name="stack" type="number" min="1" max="64" value="64"></label></div>''')+'''
## Install your output

1. Download and extract the ZIP. Review the class and `conversion-report.json`.
2. Add your PNG at the exact atlas path. For armor, author the matching attachable.
3. Package the PHP plugin using DevTools or your build pipeline. Install Customies
   first, then your plugin; place the completed pack in the server's resource packs.
4. Restart a test server, reconnect a Bedrock client and check placement, mining,
   save/reload, drops and appearance. See [installation](getting-started.html).

Generated patterns are starting points for your plugin. There are no generated
commands. Add your own abilities in [PocketMine hooks](coding.html).
'''),
'tool-shapes':('Collision & selection visualizer','Orbit physical and targeting boxes, rotate them and export PHP bounds.',INTRO+'''
## See where the block exists

Collision controls movement; selection controls targeting. Teal is collision and
amber is selection. Use native `origin`/`size` model pixels or `min`/`max` voxel
coordinates. Geometry X is mirrored when converting to server bounds. A collision
array may contain 16 boxes; selection is one box or a boolean.

'''+form('shapes','''<div class="authoring-grid">'''+select('example','Shape example',[('slab','Bottom slab'),('stair','Corner stair'),('pillar','Tall pillar'),('ghost','No collision')])+select('direction','Apply cardinal rotation',[('north','North · 0°'),('west','West · 90°'),('south','South · 180°'),('east','East · 270°')])+'''</div><label>Box JSON<textarea name="source" rows="10" spellcheck="false">{
  "collision": {"origin": [-8, 0, -8], "size": [16, 8, 16]},
  "selection": {"origin": [-8, 0, -8], "size": [16, 8, 16]}
}</textarea></label>''',scene())+'''
## Apply the result

The PHP export uses local block units, before the world position is added. Put
collision bounds in `recalculateCollisionBoxes()`. Targeting can use separate
`calculateIntercept()` logic, as the converter demonstrates. Client transformation
alone does not rotate server collision. See [protocol coordinates](protocol-updates.html).

The authoring bounds are X/Z 0–16 and Y 0–24. An oversized drawing does not reserve
extra world cells: use real multi-block placement for a structure. See
[Microsoft collision box reference](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/minecraftblock_collision_box?view=minecraft-bedrock-stable).
'''),
'tool-converter':('Mojang JSON → Customies PHP','Translate native item and block definitions into reviewable PHP.',INTRO+'''
## Bring an existing definition

Paste a complete `minecraft:block` or `minecraft:item` document. The converter
adapts supported component fields to their PHP constructors, creates explicit states,
placement traits and client permutations, and writes registration code.

Block-state comparisons, booleans and parentheses also become PHP conditions for
collision, selection, light and friction overrides. Food, durability, armor, stack
limits, attack damage and fuel receive server methods. Every unsupported path is
listed for review; original JSON is included in the ZIP. Script API, entity behavior,
loot tables, recipes and arbitrary Molang need your own implementation.

'''+form('converter','''<div class="authoring-grid"><label>PHP class<input name="className" value="SignalLamp" maxlength="48" required></label>'''+select('example','Load example',[('block','Lamp with states and trait'),('food','Food with hunger'),('armor','Armor with durability')])+'''</div><label>Mojang definition JSON<textarea name="source" rows="22" spellcheck="false">'''+html.escape(json.dumps(SAMPLE,indent=2))+'''</textarea></label>''')+'''
## Read the conversion report

“Converted” means a known field was adapted to a library constructor, not that all
gameplay is complete. `BlockBreakInfo` starts at hardness 1.0; set your tool and drop
policy. Transformations are client metadata: rotate PHP boxes explicitly for
non-cubic models. Resource-pack geometry, textures, attachables and animations
must be carried over separately.

Use [component references](block-components.html), [item behavior](items.html)
and [states and persistence](block-states.html) to finish the integration. Inputs
over 128 KiB, deeply nested documents and unsafe PHP class names are rejected.
'''),
'tool-rotation':('State & rotation workbench','Compare actual stair assets, server collision and vanilla shapes.',INTRO+'''
## Compare all 40 stair variants

These previews use fixtures exported from the actual Showcase meshes, its PHP
collision code and Axolotl's vanilla stairs. Select a corner, half and direction;
the layers should overlap. The occupancy check samples the eight half-cube cells,
which completely describe these stair models. It is not a general mesh validator.

'''+form('rotation','''<div class="authoring-grid">'''+select('shape','Corner',[('none','Straight'),('outer_left','Outer left'),('outer_right','Outer right'),('inner_left','Inner left'),('inner_right','Inner right')])+select('half','Half',[('bottom','Bottom'),('top','Top')])+select('direction','Direction',[('north','North · 0°'),('west','West · 90°'),('south','South · 180°'),('east','East · 270°')])+select('layer','Visible layers',[('all','Overlay all three'),('client','Actual client mesh'),('server','PHP collision'),('vanilla','Vanilla collision')])+'''</div>''',scene())+'''
## Why corner meshes needed a fix

The official four-direction mapping is north 0°, west 90°, south 180°, east 270°.
The old corner meshes used unmirrored X origins while server voxel bounds used
the mirrored convention. Correcting model origins aligns left/right corners
without reversing the direction mapping. The fixture test checks all meshes
against PHP and vanilla on every host compatibility run.

This workbench is a developer reference using the optional Showcase example.
The [Customies guide](states.html) explains how to implement your own content.
''')
}

LANDING='''# Customies authoring tools

Build your own content, inspect physical shapes and adapt existing Bedrock JSON.
Everything runs in your browser; your inputs are not uploaded.

| Tool | Create or inspect |
| --- | --- |
| [Item & block generator](tool-generator.html) | PHP classes, real base behavior, registration, plugin and pack manifests, atlases and slab geometry |
| [Collision & selection visualizer](tool-shapes.html) | Interactive 3D boxes, cardinal rotation and local PHP bounds |
| [Mojang JSON → Customies PHP](tool-converter.html) | Native definitions, known components, states, placement traits, permutations and a manual-review report |
| [State & rotation workbench](tool-rotation.html) | All 40 real stair mesh/collision/vanilla combinations |

## A useful development loop

Generate or convert a definition, inspect its boxes, add your artwork and gameplay,
then run host tests and check a Bedrock client. Downloads contain source files,
not prebuilt plugin binaries. The tools target this branch's API; inspect their
reports before using them with another version. See the [toolchain](toolchain.html)
and [Customies internals](how-it-works.html).
'''
