"""Separate add-on migration lab, with a local worker and explicit coverage."""

TOOL='''<div class="migration-intro"><span class="pill">LOCAL CONVERSION</span><h2 id="bring-your-addon">Bring your add-on. Keep control of the code.</h2><p>Upload a .mcaddon, .mcpack, .zip, .js or .json file, or paste a script. Get Customies definitions, supported PocketMine event hooks, preserved assets and a line-by-line migration report.</p></div>
<p><a class="button secondary" href="assets/examples/migration-demo.mcaddon" download>Download a sample .mcaddon</a></p>
<form class="authoring-form migration-form" data-migration>
<div class="authoring-grid"><label>Plugin name<input name="pluginName" value="ConvertedAddon" maxlength="48" required></label><label>Editor language<select name="sourceKind"><option value="js">JavaScript / Script API</option><option value="json">Block or item JSON / JSONC</option></select></label></div>
<label class="upload-zone">Choose a project file<input type="file" name="upload" accept=".mcaddon,.mcpack,.zip,.js,.json"><span>32 MiB compressed · 8 MiB per file · 64 MiB expanded · 1,500 entries · two nested pack levels</span></label>
<p class="shape-hint">An uploaded file takes precedence over the editor. Encrypted, split and ZIP64 archives require extraction with your own tools first.</p>
<div class="authoring-actions"><button type="button" data-migration-example>Load wand example</button><button type="button" data-migration-clear>Clear upload</button></div>
<label>Source editor<textarea name="source" rows="16" spellcheck="false" placeholder="Paste JavaScript or a complete block/item JSON definition"></textarea></label>
<div class="authoring-actions"><button class="button primary" type="submit" disabled>Translate project →</button><button type="button" data-migration-cancel hidden>Cancel conversion</button></div>
<p class="authoring-status" role="status" aria-live="polite">Ready. Processing stays in your browser; uploaded scripts are parsed, never executed.</p>
<section data-migration-result hidden><div class="migration-summary" data-migration-summary></div><div class="authoring-actions"><label>Project files<select aria-label="Migration project file" data-migration-files></select></label><button type="button" data-migration-copy>Copy source</button><button type="button" class="button primary" data-migration-download>Download project ZIP</button></div><img data-migration-image hidden alt="Preserved PNG resource preview" class="migration-image"><pre tabindex="0" data-migration-code></pre><h3>Migration report</h3><ul class="migration-review" data-migration-review></ul></section>
</form><noscript><p>JavaScript and web workers are required for local conversion. The migration guide remains readable without them.</p></noscript>'''

PAGES={
'migration':('Migration Lab','Move Bedrock content into a PHP plugin, one verified layer at a time.','''
# Move your add-on to Customies

Bedrock add-ons and PocketMine plugins run in different server environments.
Use this lab to carry over supported definitions and assets, translate common
Script API callbacks, and identify the parts that need your own PHP implementation.

<div class="migration-path"><a href="migration-tool.html"><span>01 / IMPORT</span><h3>Translate a project</h3><p>Start with a script, JSON definition or pack archive. Inspect the PHP before downloading.</p><b>Open the converter →</b></a><a href="migration-guide.html"><span>02 / UNDERSTAND</span><h3>Follow the migration guide</h3><p>Learn where definitions, resources and server behavior belong.</p><b>Follow the walkthrough →</b></a><a href="migration-support.html"><span>03 / VERIFY</span><h3>Check supported features</h3><p>See exactly which callbacks and actions translate, and what remains manual.</p><b>Read the support matrix →</b></a></div>

## What the output contains

- `plugin/`: source classes, registration and translated event listeners.
- `resource_packs/`: copied resource-pack files, including textures, models,
  attachables, animation and sound data when a resource manifest identifies them.
- `originals/`: source material kept for comparison, including unsupported files.
- `migration-report.json`: translated callbacks, source locations, unsupported
  paths and integration tasks. A scaffold with no translated logic is labelled as such.

The download is source code, not a compiled PHAR. Build it with your normal
PocketMine workflow and test the matching resource pack. CustomiesShowcase remains
a separate [example plugin](showcase.html).
'''),
'migration-tool':('Add-on → PHP converter','Translate supported JSON and JavaScript from a script or pack archive.',TOOL+'''
## Before installing the result

Read every review entry. Unsupported callbacks are kept entirely for manual work;
the converter does not emit a few actions from a callback whose control flow it
cannot understand. A resource-only pack produces assets and an integration scaffold.
Referenced PHP types are resolved after content registration; missing types log a warning.

See the [support matrix](migration-support.html) and [installation walkthrough](migration-guide.html).
The parser is Acorn; ZIP decompression uses fflate. Both are bundled locally with
their license notices. No uploaded project is sent to a server or executed.
'''),
'migration-guide':('Migration walkthrough','Import, review, build and test a converted project.','''
# A project migration, step by step

## 1. Make a working copy

Keep the original add-on and its license. Separate the behavior pack from the
resource pack. Find `manifest.json`, the script module's `entry`, block/item JSON
and any custom component registrations. Only migrate content you are permitted to use.

## 2. Import the project

Open the [converter](migration-tool.html). A .mcaddon can contain nested .mcpack
files; the tool reads up to two nested archive levels. A behavior pack's manifest
entry and relative imports determine reachable scripts. Unreachable scripts are
preserved without being translated. JSON comments and trailing commas are accepted
for migration inputs; the ordinary definition converter expects strict JSON.

## 3. Review the generated layers

Definitions become `TraitBlock`, `Item`, `Food`, `Durable` or `Armor` subclasses.
Known component fields become constructor calls. Supported state comparisons
become server collision, selection, light and friction conditions. Y-axis quarter
turns rotate PHP boxes too; scale, translation, other axes and custom pivots need
your own physical implementation. Legacy collision objects are normalized to
arrays in the exported 1.26.60 JSON.

Resource-pack files are copied unchanged. Behavior JSON is not installed as a
resource pack. If the source has no resource manifest, add the required pack yourself.

Supported Script API subscriptions become separate listener methods, preserving
each callback's early return independently. A custom `onUse` or `onConsume`
handler is filtered to its imported item identifiers. Block interactions also
check the imported block identifier and right-click action.

## 4. Finish the manual work

PocketMine events and Script API before/after events have different timing and
cancellation rules. Review each mapped hook. Rebuild UI, entity AI, timers,
commands, dynamic properties, recipes, arbitrary Molang and unsupported calls
using the matching PocketMine APIs. Original JS is reference material; the plugin
does not load or evaluate it. Never assume a component constructor implements its
entire gameplay contract.

## 5. Build and install on a test server

Package the `plugin/` folder with DevTools or your normal build pipeline. Install
Customies first. Package each `resource_packs/pack_N/` folder as a .mcpack and
configure the host resource stack. Restart, inspect startup logs and reconnect a
Bedrock client to receive the pack. Follow [Customies installation](getting-started.html).

## 6. Use an acceptance checklist

Test item identification, ability duration, cancelled events, clicks on blocks
and in air, full/empty inventories, food and durability, placement obstruction,
collision/selection, mining/drops, saved data after restart and two-player behavior.
Check the Bedrock content log for broken assets. Keep a rollback copy of the world.
'''),
'migration-support':('Migration support matrix','Know which behavior translates and which APIs need a manual port.','''
# Supported migration patterns

The converter recognizes a bounded syntax tree. It does not run JavaScript,
infer arbitrary business logic or provide a Script API compatibility runtime.

## Inputs and pack data

| Input | Result |
| --- | --- |
| .js | Supported top-level Script API subscriptions and literal configuration |
| Block/item JSON or JSONC | Known constructor adapters, explicit states, placement traits and permutations |
| .mcpack / .mcaddon / .zip | Definitions, reachable scripts, resource-pack assets and original files |
| Resource-only pack | Preserved assets and a manual integration scaffold |
| Encrypted/split/ZIP64 archive | Rejected with an explanation; extract it with your own tools |
| Entity JSON, recipes, loot tables, mcfunction | Preserved as reference; gameplay needs manual PHP |

## Script hooks

| Source | Generated host hook | Review |
| --- | --- | --- |
| `world.afterEvents.itemUse.subscribe` | `PlayerItemUseEvent` listener | PM activation and cancellation differ from Script API after-events |
| `world.afterEvents.playerBreakBlock.subscribe` | `BlockBreakEvent` listener | PM event happens before the completed break |
| Item custom `onUse` | Filtered `PlayerItemUseEvent` | Binding must appear in an imported definition |
| Item custom `onConsume` | Filtered `PlayerItemConsumeEvent` | Verify consumption timing and cancellation |
| Block custom `onPlayerInteract` | Filtered right-click `PlayerInteractEvent` | Verify targeting and protection plugins |
| `onTick`, intervals, named ticks, async handlers | Manual | Implement bounded tasks and lifecycle cleanup |

Custom components may be listed in the old `minecraft:custom_components` array
or as namespaced entries directly in `components`. Startup registrations with
`itemComponentRegistry` / `blockComponentRegistry`, including destructured aliases,
are recognized. Unbound registrations remain manual.

## Actions and control flow

Literal constants, event/player/item/block aliases, destructuring, `if`/`else`,
boolean comparisons, basic numeric arithmetic and early `return` are supported.
Identifier comparisons use cached Bedrock identifiers from host serializers. This
keeps colored variants distinct while allowing potion data variants to share one
item identifier. PHP class names and broad internal type IDs are not sufficient.

Player actions include messages, action-bar text, supported potion effects with
literal duration/amplifier/particle settings, XP/level additions, extinguishing,
integer-duration fire and clearing motion. Unsupported expressions, helper calls,
mutable variables, loops and arbitrary imports leave the **whole callback** manual.
Generic loose equality, string/number coercion and operand-valued logical
expressions also remain manual when PHP would evaluate them differently.

## What a successful conversion proves

It proves the source matched a supported translation rule. It does not prove
client appearance, game balance, protection-plugin compatibility, data migration
or capacity. Run host tests and a real Bedrock client before deploying.

References: [Microsoft custom components](https://learn.microsoft.com/en-us/minecraft/creator/documents/scripting/custom-components?view=minecraft-bedrock-stable),
[WorldAfterEvents](https://learn.microsoft.com/en-us/minecraft/creator/scriptapi/minecraft/server/worldafterevents?view=minecraft-bedrock-stable),
[Acorn parser](https://github.com/acornjs/acorn), [fflate](https://github.com/101arrowz/fflate).
''')
}
