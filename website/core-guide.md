## Install Customies

Customies is a library for registering custom blocks and items on Axolotl-PM.
You write your own content plugin and resource pack. The optional Showcase plugin
is an example; it is not a dependency of your project.

### Check the target first

This branch targets **Axolotl API 5.51.0**, **Minecraft Bedrock 26.60** and **protocol
2225**. Use the host's matching PHP distribution. Automated checks currently use
PHP 8.4; the [compatibility matrix](protocol-updates.html) lists exact commits.
Customies uses the host protocol and does not translate older clients.

### Install the library and your plugin

1. Create a test server and start it once to verify the host/PHP installation.
2. Put one `Customies-Axolotl.phar` from [downloads](downloads.html) in `plugins/`.
3. Create a separate content plugin with the manifest below. The
   [generator](tool-generator.html) can create its source files, registration and
   pack scaffolding. Existing add-ons can start in the [Migration Lab](migration.html).
4. Register content in your plugin's `onEnable()` before Customies takes the
   deferred worker snapshot. Allocate each type ID once, outside its factory closure.
5. Build/install your plugin, complete its resource pack, and add the pack to the
   server's resource stack. Restart and reconnect clients after definition changes.

```yaml
name: MyContent
main: tutorial\Main
src-namespace-prefix: tutorial
version: 1.0.0
api: 5.51.0
load: POSTWORLD
depend: [Customies-Axolotl]
```

Put `Main extends PluginBase` in `src/Main.php` under the `tutorial` namespace.
The [coding guide](coding.html) contains complete registration and class examples.
Generators download source folders, not precompiled plugin binaries; package them
with DevTools or your normal PHP build pipeline.

### Verify the first definition

Confirm the identifier registers, then retrieve it with
`CustomiesBlockFactory::get()` or `CustomiesItemFactory::get()`. Give it through
your own inventory or command logic. Customies itself does not register in-game
commands. Check the [diagnostics API](diagnostics.html) for registry information.

Join with a matching Bedrock client. Verify the texture/model, targeting, placement,
physical collision, use action and mining/drop behavior. Save, restart and reload
the world or inventory. A working texture does not establish correct server physics,
and passing a host test does not establish client prediction.

### Choose the next guide

- [Blocks](blocks.html): components, states, placement traits and permutations.
- [Items](items.html): base classes, abilities, food, durability and armor.
- [Resource packs](assets.html): atlas paths, geometry, attachables and animation.
- [How Customies works](how-it-works.html): startup, worker snapshots and saved data.
- [Changelog](changelog.html): behavior changes, migration notes and verification.


## Library architecture

| Layer | What you implement |
| --- | --- |
| Definition | Register identifiers, components, state values and permutations through Customies |
| Resource pack | Supply the textures, geometry, attachables, sounds and animations you reference |
| Server behavior | Use PocketMine PHP for collision, placement, effects, inventory, drops and permissions |

Components advertise client metadata. They do not execute arbitrary PHP or create
server gameplay automatically. A storage component needs a real server inventory;
a food component needs a suitable Food subclass; collision and lighting components
need matching PHP values. Keep all three layers consistent.

Use stable identifiers such as `mycontent:lamp`. Allocate one type ID outside the
factory closure and reuse it inside that closure, including worker reconstruction.
Define traits and states before `TraitBlock`'s parent constructor calculates the
runtime state layout. Back up worlds before removing registrations or changing
state schemas used by saved chunks.

## Component combinations

| Feature | Components and states | PocketMine implementation |
| --- | --- | --- |
| Powered lamp | Geometry, materials, light emission, Boolean state, permutation | Toggle state in `onInteract()`, match `getLightLevel()`, write updated block |
| Small decoration | Small geometry, collision and selection boxes | Match PHP collision and targeting; visual scale does not reserve additional cells |
| Directional machine | Cardinal/facing preset, marked material faces | Rotate asymmetric PHP collision and interaction direction |
| Slab | `BlockPresets::slab()`, two half meshes and a full geometry | Extend `SlabBlock` for merging, boxes, support faces and two-slab drops |
| Connected cable | Connection trait, bone visibility, per-connection boxes | Provide the resolver and matching server collision arms |
| Crafting station | Crafting-table component, geometry, materials | Open a real crafting inventory and register any custom recipes |
| Ability item | Icon, stack limit, cooldown, interact label | Implement `onClickAir()` / `onInteractBlock()` and server cooldown values |
| Food | Food, use animation, use modifiers, stack limit | Extend `Food`; implement restoration, effects and residue |
| Weapon | Damage, durability, enchantability, held appearance | Extend the appropriate Tool/Sword class and apply authoritative damage/effects |
| Armor | Wearable, durability, icon, attachable | Extend Armor; match slot/defense/wear and implement worn effects |
| Linear multi-block | Native multi-block trait, cardinal if horizontal, per-part meshes | Use atomic placement, matching affected blocks, root-only drops |

Add components to express one coherent feature. Components with duplicate names
replace one another; material maps do not merge automatically. Client visual
conditions are not evaluated as server gameplay scripts. Test each layer before
adding more states or behaviors.

## Your resource pack

Use your own namespace, pack UUIDs and texture keys. Keep the header/module UUIDs
stable across updates, and increase both versions when publishing changed assets.
Merge your pack into the server's resource stack without overwriting other packs.

| File | Purpose |
| --- | --- |
| `manifest.json` | Pack identity, module, versions and minimum engine version |
| `textures/terrain_texture.json` | Block material atlas keys and PNG paths |
| `textures/item_texture.json` | Item icon atlas keys and PNG paths |
| `models/blocks/*.geo.json` | Block geometry identifiers and bones |
| `attachables/*.json` | Worn/held geometry and player-bone bindings |
| `animations/` and `animation_controllers/` | Client animation clips and state controllers |
| `render_controllers/` | Geometry, texture and material selection |
| `textures/flipbook_textures.json` | Animated terrain texture strips |

For the tutorial lamp, merge these keys into your terrain atlas:

```json
{"resource_pack_name":"MyContent","texture_name":"atlas.terrain","texture_data":{"tutorial_lamp":{"textures":"textures/blocks/tutorial_lamp"},"tutorial_lamp_on":{"textures":"textures/blocks/tutorial_lamp_on"}}}
```

For its item tutorial:

```json
{"resource_pack_name":"MyContent","texture_name":"atlas.items","texture_data":{"tutorial_wand":{"textures":"textures/items/tutorial_wand"}}}
```

Create the three corresponding PNGs. Paths omit `.png`; identifiers must match
exactly, including case. Block geometry typically uses model pixels, with 16 pixels
per cell; PHP AABBs use block units. Scaling a model does not change server collision.

Animated materials use vertically stacked square frames and a flipbook definition.
Select the animated tile with a state permutation; let the client advance frames.
No repeating PHP task is needed for purely visual animation. Match attachable bone
names across geometry, clips and controllers, and test both camera perspectives in
Minecraft. A valid JSON file alone does not prove correct alignment or playback.

## Performance of the library

Registration cost depends on the number of definitions and the product of each
block's state values. Workers need compatible registrations too. This branch
batches palette insertion, but startup, worker memory, joining and client loading
still grow with the catalog. Four directions and two power values create eight
variants; adding sixteen charge values creates 128. Avoid unnecessary state products.

Simple registered blocks do not require a custom per-tick task. Your PHP behavior
determines runtime cost: world scans, repeated block writes, lighting, inventories,
events and entity effects can dominate. Trigger updates when gameplay changes,
bound scans and cache immutable definitions. Keep heavy work out of the main tick.

Client rendering has a separate budget. Complex meshes, transparency, large
textures and many animated attachments can lower FPS even with healthy server TPS.
Reuse meshes, keep textures modest and test the weakest supported mobile client.

Measure the same world and plugin set before and after adding your content.
Record startup/join time, main-process and worker memory, loaded chunks, concurrent
players and p95/p99 tick times. A 20 TPS target has 50 ms per tick; retain headroom.
Synthetic operation benchmarks are not player-capacity guarantees.

## Server sizing

Customies has no universal player-per-core or RAM requirement independent of the
host server, worlds and your content plugin. These are **starting test allocations**
for the whole server, not measured capacity promises:

| Workload | Initial allocation to evaluate |
| --- | --- |
| Development and a few testers | 2 fast modern CPU cores, 2–4 GiB RAM, SSD |
| Small public server with moderate plugins | 4 fast cores, 4–8 GiB RAM, SSD/NVMe |
| Heavy worlds, catalogs or gameplay | Profile from 6+ fast cores and 8–16+ GiB; derive final sizing from measurements |

Prioritize sustained single-core performance and low contention. More cores help
background jobs but do not parallelize slow synchronous gameplay. Leave memory
for workers, the OS and filesystem cache. Use the host's required 64-bit PHP build
and extensions. Size disk for worlds/backups/logs, and bandwidth for concurrent
pack downloads plus game traffic. A RAM upgrade will not fix CPU, disk or client FPS.

## Debugging your content

| Symptom | Check |
| --- | --- |
| Missing texture/model | Atlas key, path/case, geometry identifier, material slot and accepted pack version |
| Visual shape differs from collision | PHP AABBs, model pixel/block-unit conversion and every relevant state |
| Item advertises an action but does nothing | Appropriate base class and authoritative use/event hook |
| State changes disappear | Write the updated block to the world; validate persistence and cloning |
| Old worlds fail after an update | Identifier/state schema changes; restore or migrate saved data |
| Startup/join lag | Definition/state-product growth, worker copies and transfer size |
| Interaction lag | Repeated writes, scans, lighting, protection events and network volume |
| Healthy TPS but low FPS | Client mesh, alpha and animation cost |

Log identifiers, state values and positions from your own plugin. Bound verbose
NBT output and avoid logging every tick. Test placement, targeting, breaking,
inventory persistence and save/restart behavior. Do not infer client/protocol
compatibility solely from PHP tests.

## Obtain Customies

The library is the `Customies-Axolotl.phar` plugin, not the example content plugin.
Install it as a dependency of your own plugin and provide your own resource pack.
The download below contains only the library. The separate examples section has
its own plugin, pack and complete demo bundle.
