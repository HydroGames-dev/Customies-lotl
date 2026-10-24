# Customies Showcase: Prism Laboratory

A companion plugin and original Bedrock resource pack for this Customies branch.
Showcase 1.0.2 adds descriptive lore to all specimens; bundled Customies 1.5.41 includes the slab/bench selection-box fix.
When updating, replace both plugin PHARs, restart the server, and reconnect clients.
**46 blocks + 52 items**, including 23 playable examples and 75 component specimens.
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

Every one of the 98 specimens has purpose-specific lore. Playable examples explain
how to use them and give key values; laboratory examples explain their metadata
and identify behavior that needs additional server support. The same descriptions
appear in creative inventory, command kits, block drops, and inspector output.
Prism batch/quality details append to its description. All descriptions are kept
in `src/SpecimenDescriptions.php` for editing.

Lore persists with saved item stacks. Existing inventory stacks keep their saved
lore; obtain fresh specimens with `/cshow kit` or `/cshow give` after updating.
Placed blocks gain the new description when inspected or broken into an item.

## Commands

- `/cshow list [filter]` lists IDs; filters include `lab_block`, `lab_item`, `rotor`.
- `/cshow give <id> [amount]` gives a specimen; `showcase:` is optional.
- `/cshow kit [page]` gives nine specimens per page without replacing inventory contents.
- `/cshow inspect` shows held-item component names, server ID, stack limit and NBT.
- `/cshow inspect block` shows targeted block states and component names.

All commands and inspector/wrench actions require `customiesshowcase.command`
(default: operator). Inventory overflow is skipped, not dropped. Kit pages 1-3
contain the playable set first; later pages contain the laboratory.
`plugins/CustomiesShowcase/config.yml` can disable `component-laboratory`; restart
before testing changes. Keep the same registration configuration for saved test worlds.
Removing a registered specimen can make saved blocks/items unavailable.

## Playable tour

| ID | Try it | Server behavior / data |
| --- | --- | --- |
| `prism_lamp` | Place, then interact | Boolean `showcase:powered`; light 0/15 and texture permutation |
| `machine` | Place while facing each compass point | Cardinal trait, 180-degree offset, visible front panel |
| `orienter` | Place looking up/down or horizontally | Six-facing trait and rotation permutations |
| `face_marker` | Place against each of six faces | `minecraft:block_face` and matching rotation |
| `slab` | Place against upper/lower side or top/bottom face | Vertical-half state, matching half-height geometry/collision |
| `cable` | Place a row and connect solid neighbors | Four connection states, conditional model bones and collision boxes |
| `corner_stairs` | Place perpendicular neighbors | Corner/cardinal states, shape geometry and collision |
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
tool. Models include actual connection bones, stairs, half slabs, posts, and rotor
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

The PHP tests register every specimen against real Axolotl registries and round-trip
every runtime block state through the disk serializer/deserializer. Asset tests
check JSON, atlases, textures, models, rotation variants, Ogg and UUIDs. A successful
server boot verifies load-time integration; a Minecraft client still needs to check
visuals, use animations, custom armor alignment, packet behavior and interaction.

See `asset-preview.png` for the texture sheet. License: MIT; see `LICENSE`.
