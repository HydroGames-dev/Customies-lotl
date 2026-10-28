# Learn by building

Follow these projects in order, or jump to the feature you need. Each walkthrough
points to real, tested source in this repository. Client components describe the
feature; PHP implements its authoritative behavior; the resource pack supplies its
appearance. Keep all three in sync.

## 01 / Your first powered lamp

**Result:** a directional block that switches texture and emits light when used.

1. Complete the [installation](showcase-install.html) and create your plugin manifest
   with `depend: [Customies-Axolotl]`.
2. Follow the complete `TutorialLamp` class in [coding blocks](coding.html#create-a-working-block).
   Define the power state and cardinal preset before the parent constructor.
3. Add off/on PNGs to the terrain atlas. The strings in `Material` must match the
   atlas keys, not the PNG filenames with extensions.
4. Register one stable type ID outside the factory closure in `onEnable()`.
5. Keep `getLightLevel()` consistent with the power permutation. In `onInteract()`
   change the state, then write the updated block into the world.
6. Restart, accept the pack, place four lamps facing different directions, and
   switch each. Save/restart and confirm power persists.

**Check:** the inspector reports the power state and the actual server light changes.
If only the texture changes, the PHP light override is missing. If neither changes,
check the interaction hook and the installed PHAR.

## 02 / A real ability item

**Result:** a wand that grants Speed I for five seconds with a two-second cooldown.

Use the complete `TutorialWand` class in [coding items](coding.html#create-a-working-item).
Combine icon, display name, stack limit, held style and cooldown metadata. Override
`getMaxStackSize()`, `getCooldownTicks()` and `getCooldownTag()` with matching values.
Apply the actual effect in `onClickAir()` and return `ItemUseResult::SUCCESS`.

The component's cooldown uses seconds; PHP cooldown/effect durations use ticks.
Twenty ticks is one second. The example uses 40 cooldown ticks and 100 effect ticks.
Test repeated use, survival inventory stacking and reconnects. A button label or
shimmer does not implement the ability by itself.

## 03 / Food, tools and armor

Start from the appropriate PocketMine class rather than plain `Item`:

| Goal | Source to study | What to keep synchronized |
| --- | --- | --- |
| Restorative food | `PrismFood.php` | Nutrition, saturation, eating/drinking, residue, effects |
| Mining tool | `PrismPickaxe.php` | Tier, target blocks, speed, durability |
| On-hit effect | `FrostBlade.php` | Damage, wear, actual living-target effect |
| Worn gear | `PrismArmor.php` | Armor slot, defense, durability, worn effect |
| Throwable item | `PrismPebble.php` | Projectile creation, physics, consumption |

Read the [item component reference](item-components.html) and
[combination recipes](recipes.html) before adding more components. Test consumption
exactly once, correct returned items, full inventories, cooldown enforcement and
ordinary protection/event cancellation. Client storage metadata needs a real server
inventory; adding it is not enough to make a backpack.

## 04 / Rotate a slab or corner stair

Use `BlockPresets::slab()` and the reusable `SlabBlock` base for your own slabs.
It merges matching halves in one cell, saves the double flag and drops two slabs.
The Showcase's shared block class delegates to `SlabPlacement` instead. For stairs, add corner/cardinal placement and
provide five corner shapes for each half. Match neighbors only when their halves
are compatible. [States and traits](states.html) explains the lifecycle.

The supplied regression suite checks 40 stair shape/half/direction cases and the
legacy saved-state migration. Do not rewrite an old identifier's state schema
without a migration: a working new placement does not prove old chunks still load.

## 05 / Place and customize a house

**Result:** one item places a hollow, 96-cell cottage as a grouped structure.

```text
/cshow give prism_cottage
/cshow give builder_compass
/cshow give resonance_cell
```

Choose a clear level site. A north-facing cottage extends four blocks right and
four north from the placed floor corner. Its floor occupies one full cell; the
two-block-high entrance starts above the floor. Enter the empty room, place a
separate furnishing, and use Builder Compass to inspect its grouped footprint.

Use an empty hand to toggle window-frame animation and lighting. Offer a Resonance
Cell while the structure is powered and complete for Regeneration I for 10 seconds.
Break any matching cottage part: the house yields one item and the furnishing stays.
The house is a prefab, not a bed, land claim, chest or ownership system.

To change its shape, edit `generate_buildings.py`, which generates its layout and
shared model roles. Maintain the anchor `[0,0,0]`, unique integer offsets, matching
local boxes and a multiple of 16 cells for banked layouts. Keep the existing schema
for saved identifiers. [Structures](structures.html) covers the bank/part encoding.

## 06 / Make a reactor-style animated material

1. Draw eight square frames and place them vertically in a 16 × 128 PNG.
2. Add the texture key to `textures/terrain_texture.json`.
3. Add its path, atlas tile and positive `ticks_per_frame` to
   `textures/flipbook_textures.json`.
4. Select that tile in a powered-state material permutation.
5. Update power in PHP only when gameplay changes; do not advance visual frames
   with a repeating server task.

The [asset gallery](gallery.html) shows all 13 supplied flipbooks. The cottage,
market and gateway change selected materials while retaining static floors and
roof textures. [Assets](assets.html) explains manifests, atlas paths and attachables.

## 07 / Equip an animated attachment

Open the Prism armor or Clockwork Staff attachable in the resource pack. Follow
its geometry, texture, render controller, animation aliases and animation controller.
Keep bone names consistent. Bind held items to the appropriate item-slot bone and
provide first-person and third-person poses. Match PHP Armor/Tool values separately.

Run `verify_assets.py` to check references and bones, then test wearing/holding it
in Minecraft from both camera perspectives. A geometry diagram is not a substitute
for checking alignment, clipping, enchantment shimmer and animation playback.

## 08 / Validate, measure and ship

```text
php tests/run.php /path/to/axolotl
php examples/CustomiesShowcase/tests/guide.php /path/to/axolotl
python examples/CustomiesShowcase/tests/verify_assets.py
php examples/CustomiesShowcase/tools/benchmark.php /path/to/axolotl
python examples/CustomiesShowcase/tools/build.py --php /path/to/pmmp/php
```

First verify server behavior, persistence and asset references. Next test a real
client. Finally profile the intended world and concurrent-player workload. The
included benchmark is synthetic and excludes live lighting, disk I/O and networking.
Use the [performance and sizing guide](showcase-validation.html) to interpret it.

Increase the resource-pack header and module versions together, retain UUIDs for
updates, replace both PHARs and the pack, restart and reconnect. Back up old worlds
and inventories before updating registrations or state schemas.
