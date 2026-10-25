# Customies Showcase: Prism Laboratory

A companion plugin and original Bedrock resource pack for this Customies branch.
Showcase 1.4.0 adds a hollow cottage, market hall, grand gateway, shared model roles and banked part indices. Bundled Customies 1.5.42 includes the corrected corner-trait selector and slab/bench selection boxes.
When updating, replace both plugin PHARs, restart the server, and reconnect clients.
**73 blocks + 66 items**, including 64 playable examples and 75 component specimens.
Every shipped block component (33) and item component (42) has an explicit constructor
example in `src/ComponentCatalog.php` and a separate laboratory specimen.

## Install

1. Use the linked Axolotl-PM server with API 5.49.0 and its matching protocol/data dependencies.
2. Stop the server. Copy `Customies-Axolotl.phar` and `CustomiesShowcase.phar` from the install ZIP into `plugins/`. Keep exactly one Customies installation.
3. Copy `CustomiesShowcase.mcpack` into the server's `resource_packs/` directory.
4. Add `CustomiesShowcase.mcpack` to the existing `resource_stack` in `resource_packs/resource_packs.yml`. Set `force_resources: true` for a consistent showcase. Merge the supplied example; preserve other packs and settings.
5. Start the server and reconnect with the pack accepted. Operators can run `/cshow kit`.

The plugin depends on `Customies-Axolotl` by its plugin.yml name. Source folder loading
requires DevTools; the provided PHAR works without DevTools. A separate behavior pack
is not required: Customies registers block and item definitions with the server.
The pack's minimum engine version is 1.26.50. 1.26.52/1.26.60 depend on host protocol
support; those clients have not been tested here.

## Descriptions and lore

Every one of the 139 specimens has purpose-specific lore. Playable examples explain
how to use them and give key values; laboratory examples explain their metadata
and identify behavior that needs additional server support. The same descriptions
appear in creative inventory, command kits, block drops, and inspector output.
Prism batch/quality details append to its description. All descriptions are kept
in `src/SpecimenDescriptions.php` for editing.

Lore persists with saved item stacks. Existing inventory stacks keep their saved
lore; obtain fresh specimens with `/cshow kit` or `/cshow give` after updating.
Placed blocks gain the new description when inspected or broken into an item.

## Commands

- `/cshow list [filter] [page]` lists 12 IDs per page; filters include `lab_block`, `lab_item`, `rotor`.
- `/cshow give <id> [amount]` gives a specimen; `showcase:` is optional.
- `/cshow kit [page]` gives nine specimens per page without replacing inventory contents.
- `/cshow inspect` shows held-item component names, server ID, stack limit and lore.
- `/cshow inspect full` additionally shows NBT, capped at 1024 bytes.
- `/cshow inspect block` shows readable states, position, light and structure completeness.
- `/cshow inspect block full` includes components, server IDs and lore.
- `/cshow debug on|off` toggles detailed inspection for your session. Inspector, compass and wrench are limited to one action per second to avoid message floods.

All commands and inspector/wrench actions require `customiesshowcase.command`
(default: operator). Inventory overflow is skipped, not dropped. The first kit pages contain the playable set; later pages contain the laboratory.
`plugins/CustomiesShowcase/config.yml` can disable `component-laboratory`; restart
before testing changes. Keep the same registration configuration for saved test worlds.
Removing a registered specimen can make saved blocks/items unavailable.

## House and large prefabs (1.4.0)

Read the [complete Customies guide](../../docs/customies-guide.md) for registration,
all 75 components, combined features, assets, performance and server sizing. The
install ZIP includes the guide as `GUIDE.md` and a scoped benchmark sample.

| Prefab | Size / occupied cells | Interaction |
| --- | --- | --- |
| `prism_cottage` | 5 x 5 x 5 / 96 | Hollow room, two-high entrance, open window frames, timber, stepped roof and chimney details. Power lights the windows; Resonance grants Regeneration I 10s. |
| `market_hall` | 7 x 5 x 5 / 96 | Covered stalls, counters and side entrances. Power animates counters; Resonance grants Haste I 20s. |
| `grand_gateway` | 9 x 6 x 2 / 64 | Buttresses and a three-wide passage. Power animates rune stones; Resonance grants Speed I 20s. |

Use `/cshow give prism_cottage`, place on a clear level site, and enter one block
above the full-height floor. North-facing footprints extend right and north from
the root corner; the gateway includes a buttress one cell left of the anchor.
Interior air and entrance cells stay unoccupied. Furniture placed separately in
that space survives grouped breaking. These are physical prefab blocks; they do
not add land ownership, beds, storage inventories or teleportation.

New templates use banked part indices (16 low values x bank count), up to 128 cells
in multiples of 16. Old layouts retain their saved state schema. Shared model
roles avoid emitting one identical geometry file per cell. Each role must use the
same cubes. Edit `tools/generate_buildings.py` to change these generated prefabs;
`generate_assets.py` regenerates their layouts and assets. Wood, roof, stone, floor,
canopy and window materials are separate; only selected parts animate when powered.

## Structure footprints and server abilities (1.3.0)

Nine new blocks and two new items bring the catalog to 136 specimens. Each structure
has a base anchored at the placed cell, follows cardinal direction, checks all occupied
cells before placement, and breaks as a group with exactly one drop. Bounding boxes
below describe the north-facing footprint (width X, height Y, depth toward north).
Hollow cells are deliberately unoccupied and do not block placement.

| ID | Size / occupied cells | Resonance ability |
| --- | --- | --- |
| `runic_arch` | 3 x 4 x 1 / 9 | Speed I, 20 seconds |
| `thermal_forge` | 3 x 2 x 2 / 12 | Haste II, 20 seconds |
| `crystal_conservatory` | 3 x 3 x 3 / 26 | Regeneration I, 10 seconds |
| `sky_dock` | 5 x 1 x 3 / 15 | Jump Boost II, 20 seconds |
| `storm_engine` | 4 x 2 x 2 / 16 | Resistance I, 15 seconds |
| `astral_beacon` | 5 x 6 x 3 / 32 | Night Vision I, 60 seconds |

Interact with an empty hand to toggle the whole structure's power, texture and light.
Offer one `resonance_cell` to any powered part to apply the listed effect to yourself.
Unpowered or incomplete structures preserve the cell. Repeated offers refresh effects;
there is no automatic area effect or fuel processing. `chromatic_lens` is a reusable
texture/power controller for powered/color expansion blocks. These actions execute
in `ShowcaseItem::onInteractBlock()` and `ExpansionBlocks::interact()` on PocketMine.

`molten_cell` (full cube), `tide_cell` (three-quarter height), and `aurora_tile`
(quarter height) toggle between static textures and animated, luminous materials.
Together with the six structures and existing reactor, ten eight-frame flipbook
materials are supplied: molten currents, tide waves, aurora bands, electric arcs,
crystal growth and rune flows. Animation is client-side; state, effects and light are
server-side. No repeating PHP timer or per-player world scan is needed.

`resources/expansion_blocks.json` is the editable layout/geometry/ability catalog.
`StructureLayout` validates unique integer offsets and supports legacy layouts of 2-32 cells within
8 blocks of the origin, including irregular and hollow layouts. `showcase:structure_part`
is a persisted custom state; each part has matching geometry, selection and collision.
The 32-part beacon uses 256 combinations (32 parts x 4 directions x 2 power values).
This is a server-managed system, separate from the native linear trait, which is
[limited to 2-4 parts](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/traits/multi_block?view=minecraft-bedrock-stable).
No invalid oversized `minecraft:multi_block` definition is sent to clients.

Operators can use Builder Compass for a paged cell-coordinate listing, or Inspector
for the present/expected part count and ability requirements. Failed placement reports
the blocked coordinate. Detailed NBT is opt-in and truncated; state/component lists
are split into short lines. These controls replace long unbounded debug chat dumps.

## Size, structure and animated-gear expansion (1.2.0)

Ten new blocks and seven new items are included. Each has its own icon/texture and
lore. See `model-preview.png` for geometry drawn at the labeled scale, and
`asset-preview.png` for the texture sheet.

| Block | Occupied cells / size | Function |
| --- | --- | --- |
| `sky_obelisk` | 1 x 4 vertical | Four-part rune tower; toggle illumination from any part. |
| `crystal_reactor` | 1 x 3 vertical | Three-part reactor; synchronized power and an eight-frame animated energy texture. |
| `suspended_chandelier` | 3 downward | Ceiling canopy, chain and lamp; bottom part emits light 12. |
| `holo_bench` | 3 horizontal | Three-part bench; all sections toggle illumination together. |
| `prism_bridge` | 4 horizontal | Walkable deck with raised side rails; grouped placement and breaking. |
| `prism_telescope` | 2 horizontal | Tripod and barrel; interact for Night Vision for 20 seconds. No FOV zoom. |
| `tiny_lantern` | 6 x 8 x 6 model pixels | A tiny switchable light with a matching small selection/collision box. |
| `mini_chess` | 4 x 8 x 4 model pixels | Collectible rook with three interactive material colors. |
| `pocket_planter` | 8 x 10 x 8 model pixels | Small planter with luminous leaves when activated. |
| `runic_pedestal` | 12 x 16 x 12 model pixels | Consume an offered Prism Berry for Regeneration II for 5 seconds. |

Multi-block traits reserve real neighboring cells. These are linear structures,
not unsupported 2-by-2-by-2 cube definitions. Every structure needs its entire
space free and within world bounds. Breaking any segment removes the group and
drops one item. Small models still occupy a grid cell; multiple miniatures cannot
share one cell. Bounds, client boxes and server collision are authored together in
`resources/expansion_blocks.json`. The bridge has separate deck/rail collisions.

| Item | Function |
| --- | --- |
| `prism_chestplate` | Armor 6, durability 384; articulated plates and animated shoulder satellites. |
| `prism_leggings` | Armor 5, durability 360; articulated legs and pulsing knee crystals. |
| `prism_boots` | Armor 2, durability 300; Jump Boost I while worn and cosmetic animated winglets. No flight. |
| `clockwork_staff` | Use in air: Haste I for 15 seconds, cooldown 8 seconds; modeled rotating orb and gear. |
| `wind_fan` | Use in air to dash forward with a little lift, cooldown 3 seconds; modeled spinning fan. |
| `builder_compass` | Use on a block to list the coordinates of its grouped cells; requires showcase permission. |
| `moon_mochi` | Food 4 / saturation 4.8; Jump Boost I for 15 seconds and Night Vision for 10 seconds. |

The existing Prism Helmet now has a matching crown rig and a rotating crystal.
Frost Blade also has a 3D held model. The pack contains four armor rigs, three held
rigs, 17 skeletal animations, two animation controllers, first/third-person poses,
a dedicated render controller, four model texture sheets, and a reactor flipbook.
Animations are cosmetic; flight, shield blocking and camera zoom are not added.
Geometry/bone/controller references are checked automatically. Player alignment,
slim-skin appearance, first-person offsets and animation timing still need a real
Minecraft client playtest. Pose values are editable in the generated JSON and
`tools/generate_gear.py`.

Update **both PHARs and the resource pack**, restart the server, and reconnect.
Obtain fresh items with `/cshow kit` or `/cshow give <id>`. The pack UUID is preserved
and its version is increased to 1.4.0 so clients can accept the update.

## Combined-feature additions (1.1.0)

| ID | Combined features and function |
| --- | --- |
| `signal_lamp` | Cardinal rotation + integer brightness + materials + light. Interact to cycle light levels 0, 5, 10, 15. |
| `lumen_cable` | Cardinal connections + conditional arms/collision + powered state. Connects to both cable variants; interact to toggle light 0/15. |
| `phase_gate` | Cardinal rotation + open state + geometry/collision/light permutations. Interact to open or close. |
| `spring_pad` | Quarter-height model/collision + powered state + landing callback. Interact to arm, land to bounce; sneak to suppress bouncing. |
| `prism_workbench` | Cardinal rotation + crafting-table metadata + real server crafting inventory. Interact for a 3-by-3 crafting grid. |
| `scout_wand` | Cooldown + glint + rarity + server effect action. Speed/Jump I for 10 seconds, Night Vision for 30 seconds; 5-second cooldown. |
| `repair_kit` | Stack limit + action label + server inventory operation. Hold a damaged durable item offhand and use in air to restore up to 64 durability; consumes one kit. |
| `energized_ration` | Real food + use animation + modifiers + effects. Restores 10 hunger/12 saturation and grants Speed I for 15 seconds. |
| `prism_elixir` | Always-consumable drink + effects + residue. Resistance I for 10 seconds and Night Vision for 30 seconds; returns a glass bottle. |
| `frost_blade` | Iron-tier sword + wear + damage/enchant/repair/swing metadata + hit effect. Applies Slowness I to living targets for 2 seconds. |

These additions include distinct textures and item icons, gate/pad/workbench models,
and descriptive lore. These examples were introduced in 1.1.0. Update both PHARs **and** the latest resource pack, restart,
and reconnect. The pack UUID is unchanged; its version is increased for client updates.

### Corner-stair correction

Corner stairs now support bottom and upside-down placement. Five explicit shapes
for each half replace conditional-bone rendering. The same shape table drives server
and client collision. Tests cover all 40 direction/half/shape cases, mixed-half
neighbors and neighbor removal. Old saved stairs without a vertical-half state load
as bottom-half stairs. The corner selector no longer also enables the conflicting
standalone cardinal selector. Cross-identifier/tag-expression corner joining remains
outside this showcase's implementation.

## Playable tour

| ID | Try it | Server behavior / data |
| --- | --- | --- |
| `prism_lamp` | Place, then interact | Boolean `showcase:powered`; light 0/15 and texture permutation |
| `machine` | Place while facing each compass point | Cardinal trait, 180-degree offset, visible front panel |
| `orienter` | Place looking up/down or horizontally | Six-facing trait and rotation permutations |
| `face_marker` | Place against each of six faces | `minecraft:block_face` and matching rotation |
| `slab` | Place against upper/lower side or top/bottom face | Vertical-half state, matching half-height geometry/collision |
| `cable` | Place a row and connect solid neighbors | Four connection states, conditional model bones and collision boxes |
| `corner_stairs` | Place perpendicular neighbors | Corner/cardinal/vertical-half states, explicit shape geometry and collision |
| `totem` | Place with three free vertical cells | Atomic three-part structure; part-specific textures; grouped break |
| `bench` | Place with two free horizontal cells | Cardinal + multi-block; matching two-part placement and grouped break |
| `rotor` | Place at different yaw angles; use wrench | 16-way state; 16 models with 22.5-degree bone rotations |
| `phase_block` | Interact three times | Integer `showcase:phase`; third phase has no collision |
| `ice_tile` | Walk across it | Slippery server friction plus client friction component |
| `glow_flower` | Place and walk through | Custom cross model, light 8, replaceable, no collision |
| `inspector` | Use on a block | Prints server state values and registered component names |
| `state_wrench` | Use on lamp, phase block or rotor | Cycles safe mutable state; leaves multi-block topology intact |
| `pulse_wand` | Use in the air | Speed I for five seconds; two-second server item cooldown |
| `prism` | Give and inspect | Offhand crystal with batch/quality NBT and custom lore |
| `ember_fuel` | Use as furnace fuel | 400 burn ticks / 20 seconds; fireproof item |
| `pigment` | Inspect | Dyeable metadata specimen with original icon; no dye gameplay handler |
| `prism_berry` | Eat while hungry | Restores 6 hunger and 7.2 saturation; Regeneration I for 3 seconds |
| `prism_pickaxe` | Mine stone, attack | Iron-tier mining/harvesting, damage, durability; client repair/enchant metadata |
| `prism_helmet` | Equip in head slot | 2 armor points, 256 durability; custom attachable and item icon |
| `prism_pebble` | Throw | Actual PocketMine snowball projectile; stack size 16 |

All textures and audio are original and deterministically generated by the included
tool. Models include connection bones, ten explicit stair shapes, half slabs, gates, pads, posts, and rotor
variants; arbitrary-angle rotations are in geometry, not quarter-turn wire indices.

## Component laboratory: what is implemented

`showcase:lab_block_<key>` / `showcase:lab_item_<key>` correspond to keys in
`component-coverage.json`. Each catalog entry supplies one component with illustrative
values; blocks also receive shared rendering components and items receive icons/names.
Companion components are added where needed (durability sensor, food, storage, etc.).

The laboratory is a **metadata and rendering test set**. It does not turn every
client component into a PocketMine gameplay implementation. In particular,
block-entity slots have no backing inventory tile; crafting-table metadata does not
open a custom workbench; liquid detection is not server waterlogging; piston,
leash, precipitation, flower-pot insertion, and random-offset gameplay are not
implemented. Wearable, kinetic/piercing weapon, storage/bundle UI, shooter,
record playback, composting, repairs, damage absorption, and durability-sensor
laboratory metadata require corresponding engine/server support. The playable
pickaxe/helmet/berry/pebble supply real server classes for their documented behavior.
The generic laboratory durability item is not a Durable item; use the pickaxe to
exercise actual wear. All component values serialize and register, but no claim
is made that every laboratory component is accepted or rendered by every client.

Component coverage is checked against actual source classes, so adding a new component
to Customies makes the coverage test fail until a specimen is added.

## Build and validate

```text
php examples/CustomiesShowcase/tests/verify.php /path/to/axolotl-checkout-with-vendor
python examples/CustomiesShowcase/tests/verify_assets.py
python examples/CustomiesShowcase/tools/build.py
```

The Axolotl checkout needs its Composer dependencies installed. PHP must have Phar;
the build command invokes `php -dphar.readonly=0` for packaging. The builder accepts
`--php /path/to/php` and `--output /path/to/dist`. Output includes both plugin PHARs,
`CustomiesShowcase.mcpack`, an install ZIP, and SHA-256 checksums. Generated archives
are kept out of source control; source assets are committed.

Asset regeneration only (not needed to build committed assets) needs Python Pillow
and ffmpeg with libvorbis:

```text
python -m pip install Pillow
python examples/CustomiesShowcase/tools/generate_assets.py
```

The PHP tests register every specimen against real Axolotl registries, exercise expansion placement/breaking and equipment behavior, and round-trip
every runtime block state through the disk serializer/deserializer. Asset tests
check JSON, atlases, textures, models, rotation variants, Ogg and UUIDs. A successful
server boot verifies load-time integration; a Minecraft client still needs to check
visuals, use animations, custom armor alignment, packet behavior and interaction.

See `asset-preview.png` for the texture sheet. License: MIT; see `LICENSE`.

Reference formats: [Microsoft attachables](https://learn.microsoft.com/en-us/minecraft/creator/documents/attachables?view=minecraft-bedrock-stable), [animation schema](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/visualreference/actor_animation.v1.8.0?view=minecraft-bedrock-stable). All new meshes, textures, audio and animations are original procedural assets.
