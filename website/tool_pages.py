"""Accessible browser-local tools; their pure logic lives in tools-lib.js."""

INTRO='''<div class="wiki-callout"><strong>Local calculations, explicit limits</strong><p>These tools run in your browser. Inputs are not uploaded. They help prepare and review content, but cannot replace host tests, real NBT validation or Minecraft client testing.</p></div>'''

def form(name,fields):
    return f'''<form class="tool-form" data-tool="{name}">{fields}<button class="button primary" type="submit" disabled>Run tool</button><p class="tool-status" role="status" aria-live="polite">Loading local tool…</p><div class="tool-result" hidden><h2>Result</h2><pre tabindex="0"></pre><div class="tool-files"></div></div></form><noscript><p>Enable JavaScript to run this local calculator. The documentation remains readable without it.</p></noscript>'''

PAGES={
'tool-states':('State calculator','Count the runtime combinations in a proposed state schema.',INTRO+'''
## State enum calculator

Enter a JSON object mapping namespaced state names to explicit arrays. The result
is the Cartesian-product count, with the first value shown as each default.
It does not create or register blocks and is not a client compatibility validator.

'''+form('states','''<label>State definitions<textarea name="states" rows="9" spellcheck="false">{
  "minecraft:cardinal_direction": ["north", "west", "south", "east"],
  "mycontent:powered": [false, true]
}</textarea></label>''')+'''
## Interpret the result

Four directions and two power values create eight combinations, even if there are
only two visual permutation entries. Extra independent state values multiply.
Large enums need target-client checks; the tool's input bounds are safety limits,
not documentation of engine limits. Read [block states](block-states.html).
'''),
'tool-boxes':('Box converter','Convert model-pixel boxes and local PHP collision boxes.',INTRO+'''
## Local coordinate converter

Supply an origin and size, each as a JSON array of three numbers. Model pixels use
X/Z centered around zero and 16 pixels per cell. Block-unit input uses a local
0-to-1 origin and size. Cardinal rotation follows this branch's north/west/south/east
quarter-turn convention.

'''+form('boxes','''<div class="tool-columns"><label>Input units<select name="units"><option value="pixels">Model pixels</option><option value="blocks">Block units (origin + size)</option></select></label><label>Server rotation<select name="direction"><option>north</option><option>west</option><option>south</option><option>east</option></select></label></div><label>Origin<textarea name="origin" rows="2" spellcheck="false">[-8, 0, -8]</textarea></label><label>Size<textarea name="size" rows="2" spellcheck="false">[16, 8, 16]</textarea></label>''')+'''
## What the output means

Client vectors remain unrotated so you can pair them with the corresponding visual
transformation. The PHP AABB is rotated in local block units. The tool does not
check selection behavior, all component coordinate limits, world positions or a
whole structure. Do not use negative/zero sizes to represent disabled collision.
Read [collision boxes](block-component-collision_box.html).
'''),
'tool-identifiers':('Identifier audit','Find duplicates and naming-convention problems.',INTRO+'''
## Review your identifiers

Enter one identifier per line. This tool checks a conservative naming convention:
lowercase letters, digits and underscores with one namespace separator. A warning
about that convention is not a complete statement of the engine's identifier grammar.

'''+form('identifiers','''<label>Identifiers<textarea name="identifiers" rows="8" spellcheck="false">mycontent:lamp
mycontent:wand
mycontent:lamp
minecraft:my_custom_block</textarea></label>''')+'''
## Before renaming

Choose your own namespace and avoid accidental duplicate registration. Identifiers
are saved data: do not rename a deployed block/item just to satisfy a cosmetic
convention without a migration. See [data and persistence](data-guide.html).
'''),
'tool-components':('Component inspector','Compare JSON values with exported Customies wrapper examples.',INTRO+'''
## Inspect a readable wrapper value

Choose a wrapper family and enter an object keyed by component identifier. The
tool knows the checked-in wrapper names and the **observed fixture shape**, not
every required or optional field in native Bedrock. Missing fixture fields are
advisories, not proof your input is invalid.

'''+form('components','''<label>Wrapper family<select name="kind"><option value="item">Item components</option><option value="block">Block components</option></select></label><label>Component values<textarea name="components" rows="9" spellcheck="false">{
  "minecraft:cooldown": {
    "category": "mycontent:wand",
    "duration": 2,
    "type": "use"
  }
}</textarea></label>''')+'''
## Important boundaries

JSON does not preserve NBT tag types, and wrapper payloads may differ from native
behavior-pack JSON. This is not a complete schema validator, exploit detector or
server compatibility test. Open the corresponding [item](item-components.html) or
[block](block-components.html) reference and inspect the implementation.
'''),
'tool-starter':('Plugin starter generator','Generate a small content-plugin outline with matching basic metadata.',INTRO+'''
## Start an item or block plugin

Generate a manifest, entry class, content class and atlas fragment. This outline
uses the API conventions tested in this repository. It does not generate PNGs,
resource-pack UUIDs, an installable pack or your final gameplay logic.

'''+form('starter','''<div class="tool-columns"><label>Content kind<select name="kind"><option value="item">Item</option><option value="block">Block</option></select></label><label>Plugin name<input name="plugin" value="MyContent" maxlength="48" autocomplete="off"></label></div><div class="tool-columns"><label>Namespace<input name="namespace" value="mycontent" autocomplete="off" spellcheck="false"></label><label>Content path<input name="path" value="starter" autocomplete="off" spellcheck="false"></label></div><label>Item stack size<input name="stack" type="number" min="1" max="64" step="1" value="1"></label>''')+'''
## Finish the project

Save each generated file at its shown project path. Create your own pack manifest
with stable UUIDs and add the PNG at the atlas path. Install Customies, package
your plugin or use a suitable development loader, then test with the real host.
The generic starter does not add commands or abilities. Continue with the
[coding guide](coding.html) and [maintainer workflow](updating-customies.html).
''')
}

LANDING='''# Customies toolbox

Use these tools for small, repeatable checks while developing your own content.
They work locally in the browser, need no account and do not upload your inputs.

| Tool | Helps you with | Does not prove |
| --- | --- | --- |
| [State calculator](tool-states.html) | State products, defaults, enum mistakes | Engine limits, performance or saved-world compatibility |
| [Box converter](tool-boxes.html) | Model pixels ↔ local block boxes and cardinal rotation | Complete collision/selection correctness |
| [Identifier audit](tool-identifiers.html) | Duplicate names, namespace and naming conventions | Full engine identifier grammar or migration safety |
| [Component inspector](tool-components.html) | Known wrapper names and example value shapes | Complete schema, NBT types or native feature support |
| [Plugin starter](tool-starter.html) | Basic manifest, classes and atlas fragments | A complete resource pack or your gameplay implementation |

## Use the result in a real workflow

Start with a calculation or outline, inspect the generated values, then run PHP
tests against your target host. Check the pack references and test a real client.
Use the [toolchain guide](toolchain.html) for tools outside the browser and the
[data guide](data-guide.html) before changing deployed identifiers or schemas.
'''
