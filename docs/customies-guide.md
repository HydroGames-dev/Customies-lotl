# Customies developer and operator guide

This guide describes the HydroGames-dev/Customies-lotl branch and its Axolotl-PM
showcase, not every historical Customies fork. The runnable examples live in
[CustomiesShowcase](../examples/CustomiesShowcase/README.md). Its catalog contains
33 block component classes and 42 item component classes. Component support in
the client is different from gameplay support in PocketMine.

## Contents

- [Install and first tour](#install-and-first-tour)
- [The three layers](#the-three-layers)
- [Create a working block](#create-a-working-block)
- [Create a working item](#create-a-working-item)
- [States, rotation and permutations](#states-rotation-and-permutations)
- [Structures and the house](#structures-and-the-house)
- [Every block component](#every-block-component)
- [Every item component](#every-item-component)
- [Combine components into features](#combine-components-into-features)
- [Assets and animation](#assets-and-animation)
- [Performance and measurement](#performance-and-measurement)
- [Server sizing](#server-sizing)
- [Debugging and maintenance](#debugging-and-maintenance)

## Install and first tour

Use the linked [Axolotl-PM](https://github.com/axolotl-pm/PocketMine-MP) server and
its matching PHP binaries and dependencies. Validation here uses PHP 8.4.16 and
Axolotl 5.51.0 and the stable branch with released 26.60 dependencies (protocol 2225).
See [the pinned compatibility matrix](https://hydrogames-dev.github.io/Customies-lotl/protocol-updates.html).
Customies
does not negotiate new Minecraft protocols: host support determines which clients
can connect. 1.26.52/1.26.60 are not client-tested by this project.

Customies 1.7.0 registers no in-game commands or command permissions. Register definitions during
startup before the worker snapshot; registration closes afterward and requires a
restart. Use [runtime diagnostics](https://hydrogames-dev.github.io/Customies-lotl/diagnostics.html)
to understand counts, inspect states and record the host's dependency versions.

1. Back up the world and stop the server.
2. Copy both PHARs from the showcase install ZIP to `plugins/`, keeping one copy
   of Customies. The dependency name is `Customies-Axolotl`.
3. Put `CustomiesShowcase.mcpack` in `resource_packs/`, add it to the existing
   `resource_stack`, and merge the supplied resource-pack configuration example.
4. Start the server. Reconnect and accept the resource pack. Do not hot-reload
   block registrations into an existing session.
5. As an operator, use `/cshow list cottage`, `/cshow give prism_cottage`, and
   `/cshow give inspector`. Place a cottage on an empty flat area.
6. Use `/cshow inspect block`, `/cshow inspect block full`, and `/cshow debug on`
   to compare compact and detailed inspection. `/cshow kit [page]` gives nine
   specimens at a time without clearing inventory.

The laboratory can be disabled in `plugins/CustomiesShowcase/config.yml` before
starting a fresh test world. Do not remove registrations used by an existing world
without a migration plan. Identifiers are saved in block/item data.

For your own plugin, declare `depend: [Customies-Axolotl]` in `plugin.yml`. Register
definitions in `onEnable()` before Customies' deferred worker initialization runs.
Source folder loading needs DevTools; PHAR installation does not.

## The three layers

| Layer | Its responsibility | Example |
| --- | --- | --- |
| Customies definition | Advertise components, state values and rendering conditions to the client; map identifiers into the host registry | Geometry, icon, powered permutation |
| Resource pack | Supply textures, meshes, attachables, sounds and animations referenced by those definitions | A glowing lamp texture or worn armor rig |
| PocketMine PHP | Enforce authoritative gameplay, collision, placement, drops, effects, inventory changes and permissions | Toggle a state, consume fuel, apply Haste |

Adding `FoodComponent` to a plain `Item` does not make PocketMine feed a player.
Adding a storage component does not create a server inventory. Adding collision
metadata does not change PHP collision boxes. Implement both sides and keep their
values equal. The laboratory deliberately demonstrates individual metadata
components, including features without a complete server implementation; playable
specimens show actual combined behavior.

Components with the same name replace earlier components in `addComponent()`.
For example, adding two `MaterialInstancesComponent`s does not merge their maps.
Put all material slots in one component when you need several simultaneously.

## Create a working block

Put this in `src/TutorialLamp.php` under your plugin's `tutorial` namespace. It
combines cardinal placement, one persistent power state, a client material/light
permutation and the matching server interaction. The geometry is a built-in cube;
`tutorial_lamp` and `tutorial_lamp_on` must exist in your terrain atlas.

```php
<?php
declare(strict_types=1);
namespace tutorial;

use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\properties\Material;
use customiesdevs\customies\block\states\BlockState;
use customiesdevs\customies\block\preset\BlockPresets;
use customiesdevs\customies\block\permutations\BlockPermutation;
use pocketmine\block\{BlockIdentifier, BlockTypeInfo, BlockBreakInfo};
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class TutorialLamp extends TraitBlock {
    public function __construct(int $id) {
        BlockPresets::cardinal($this);
        $this->addState(BlockState::bool('tutorial:powered'));
        $this->addComponent(new B\DisplayNameComponent('Tutorial Lamp'));
        $this->addComponent(new B\GeometryComponent());
        $this->addComponent(new B\MaterialInstancesComponent([
            new Material('*', 'tutorial_lamp')
        ]));
        $this->addComponent(new B\CollisionBoxComponent());
        $this->addComponent(new B\SelectionBoxComponent());
        $this->addComponent(new B\DestructibleByMiningComponent(0.6));
        $this->addComponent(new B\LightEmissionComponent(0));
        $this->addPermutation(new BlockPermutation(
            "q.block_state('tutorial:powered')",
            new B\LightEmissionComponent(15),
            [new B\MaterialInstancesComponent([
                new Material('*', 'tutorial_lamp_on')
            ])]
        ));
        parent::__construct(new BlockIdentifier($id), 'Tutorial Lamp',
            new BlockTypeInfo(new BlockBreakInfo(0.6)));
    }
    public function getLightLevel(): int {
        return $this->getState('tutorial:powered')->getCurrentValue() ? 15 : 0;
    }
    public function onInteract(Item $item, int $face, Vector3 $clickVector,
        ?Player $player = null, array &$returnedItems = []): bool {
        $state = $this->getState('tutorial:powered');
        $state->setCurrentValue(!$state->getCurrentValue());
        $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
        return true;
    }
}
```

For a plugin whose entry class is `tutorial\Main`, use this manifest and put your
`Main extends PluginBase` class in `src/Main.php` with the registration calls below
inside its `onEnable()` method:

```yaml
name: TutorialContent
main: tutorial\Main
src-namespace-prefix: tutorial
version: 1.0.0
api: 5.51.0
load: POSTWORLD
depend: [Customies-Axolotl]
```

In your `PluginBase::onEnable()`, allocate one ID outside each factory closure:

```php
$blockId = \pocketmine\block\BlockTypeIds::newId();
\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->registerBlock(
    static fn() => new \tutorial\TutorialLamp($blockId), 'tutorial:lamp'
);
```

Do not allocate a different ID every time the closure executes. Worker registration
must reconstruct the same type. TraitBlock already handles state layout,
serialization, cloning, trait placement and neighbor updates. Register states and
traits before its parent constructor calculates the runtime state layout.

## Create a working item

Put this in `src/TutorialWand.php`. It supplies matching client/server stack limits
and cooldowns, then applies a real PocketMine effect. The effect duration is in
ticks; the component cooldown duration is in seconds.

```php
<?php
declare(strict_types=1);
namespace tutorial;

use customiesdevs\customies\item\{ItemComponents, ItemComponentsTrait};
use customiesdevs\customies\item\preset\ItemPresets;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Item, ItemIdentifier, ItemUseResult};
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\entity\effect\{EffectInstance, VanillaEffects};

final class TutorialWand extends Item implements ItemComponents {
    use ItemComponentsTrait;
    public function __construct(int $id) {
        parent::__construct(new ItemIdentifier($id), 'Tutorial Wand');
        ItemPresets::basic($this, 'tutorial_wand', 'Tutorial Wand', 1);
        $this->addComponent(new I\HandEquippedComponent());
        $this->addComponent(new I\CooldownComponent('tutorial:wand', 2.0));
        $this->setLore(['Use in air: Speed I for 5 seconds.']);
    }
    public function getMaxStackSize(): int { return 1; }
    public function getCooldownTicks(): int { return 40; }
    public function getCooldownTag(): ?string { return 'tutorial:wand'; }
    public function onClickAir(Player $player, Vector3 $directionVector,
        array &$returnedItems): ItemUseResult {
        $player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), 100, 0));
        return ItemUseResult::SUCCESS;
    }
}
```

Register it in `onEnable()` and add `tutorial_wand` to `textures/item_texture.json`:

```php
$itemId = \pocketmine\item\ItemTypeIds::newId();
\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->registerItem(
    static fn() => new \tutorial\TutorialWand($itemId), 'tutorial:wand'
);
```

Use `Food`, `Armor`, `Sword`, `Pickaxe`, or the appropriate PocketMine base class
for those behaviors instead of reimplementing them on plain `Item`. See
`PrismFood`, `PrismArmor`, `FrostBlade`, and `PrismPickaxe` in the showcase.
For consumable block offers, return the correct `ItemUseResult` from
`onInteractBlock()` and mutate the provided item stack once; do not also manually
remove a second inventory item. Respect normal interaction events and protection.

## States, rotation and permutations

A state contains persistent discrete data; a permutation describes which client
components apply for a combination of state values. PHP changes the state and
writes the updated block into the world. Molang conditions are interpreted by the
client; arbitrary Molang is not a PHP gameplay script.

| Need | API / example | Server work still needed |
| --- | --- | --- |
| Four horizontal orientations | `BlockPresets::cardinal($this)` | Rotate asymmetric collision boxes too |
| Six facing directions | `BlockPresets::facing($this)` | Match collision/interaction direction |
| Upper/lower slab | `PlacementPositionTrait::verticalHalf()` | Half-specific collision and selection |
| Clicked block face | `PlacementPositionTrait::blockFace()` | Face-specific model/permutations |
| Connected cable | `BlockPresets::connections($this, $resolver)` | Connected geometry and PHP arm boxes |
| Corner stairs | `PlacementDirectionTrait::cornerAndCardinal()` plus vertical-half trait | Five corner meshes per half, matching boxes |
| Sixteen rotations | `PlacementDirectionTrait::sixteenWay()` | Authored bone rotations; transformation encoding uses quarter turns |
| Linear multi-block | `BlockPresets::multiBlock($this, 'up', 3)` | Per-part meshes, boxes and root-only drops |

Do not register the same state twice through a preset and a manual state. Native
multi-blocks accept only the cardinal-direction placement trait alongside their
own trait. Their base movement must be immovable or popped. Use the separate
showcase layout system for houses and other footprints beyond the native chain.

State combinations multiply: 4 directions x 2 power values = 8 runtime variants;
96 parts x 4 directions x 2 power values = 768. Adding a 16-level charge state to
the house would raise that to 12,288. Keep inventory data and continuous counters
in suitable server storage instead of adding unnecessary block states. Microsoft's
[state/permutation reference](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockstatesandpermutations?view=minecraft-bedrock-stable)
warns about the performance impact of very large total permutation counts.

## Structures and the house

| New prefab | Bounding size | Occupied cells | Feature |
| --- | --- | --- | --- |
| `prism_cottage` | 5 x 5 x 5 | 96 | Floor, two-block-high entrance, hollow room, open window frames, stepped roof and chimney details |
| `market_hall` | 7 x 5 x 5 | 96 | Canopy, counters, side entrances and covered interior |
| `grand_gateway` | 9 x 6 x 2 | 64 | Buttresses, illuminated stone runes, three-wide passage |

Give yourself one with `/cshow give prism_cottage`. For the north-facing cottage,
part zero is the front floor corner: the footprint extends 4 cells to the right,
4 north and 4 upward. Cardinal rotation rotates the entire footprint. The gateway
also extends one cell left of its anchor. Builder Compass lists the actual cells.

Place on clear, level ground; every occupied cell must be replaceable and within
world height. The floor itself occupies a full cell. The cottage entrance begins
one cell above its floor. Interior and entrance cells are unoccupied, so place
furniture inside after building. Existing blocks in the hollow volume are preserved.
This prefab system does not check foundations or claim land for a player.

Empty-hand interaction toggles the group. Cottage window frames, market counters
and gateway runes use selective animated materials while floors, roof and timber
keep their authored textures. Offer a Resonance Cell to a complete, powered
structure: cottage grants Regeneration I for 10s, market Haste I for 20s, gateway
Speed I for 20s. These are buffs, not beds, storage inventories, automatic crafting,
teleport portals or ownership protection.

Breaking any matching part removes the prefab with one drop. Separately placed
furniture and unrelated blocks substituted into missing parts are preserved.
External world-edit tools or explosions can leave incomplete groups; inspection
shows present/expected cells, and ability activation refuses incomplete structures.
Test explosion/piston/plugin interactions on a copy of the world before production.

`StructureLayout` uses explicit relative coordinates, not a giant mesh extending
outside a single server block. It checks the footprint before constructing the
placement transaction, reports all matching parts to PocketMine's break logic, and
does not scan the world every tick. Legacy layouts retain their old part state.
New banked layouts use `structure_part` 0-15 and `structure_bank`; the index is
`bank * 16 + part`. They support 16-128 cells in multiples of 16, with offsets
within 8 blocks of the anchor. The house has six banks, so all 96 indices are used.
Existing state schemas are preserved; do not toggle banking on a saved identifier.

Edit `tools/generate_buildings.py` for these three generated templates, then run
`generate_assets.py`. Other expansion layouts are in
`resources/expansion_blocks.json`. Each segment defines local cubes, selection
bounds, collision boxes, a shared model role, static texture and optional powered
texture. Duplicate roles must have identical meshes; do not reuse a role name for
a different shape. Include every occupied cell, with part zero at `[0,0,0]`.

## Every block component

Names below are catalog keys; their PHP classes live in
[`src/block/component`](../src/block/component). The constructor examples for all
33 are in [`ComponentCatalog::blocks()`](../examples/CustomiesShowcase/src/ComponentCatalog.php).
The last column identifies the additional PHP responsibility; it is not a claim
that all laboratory specimens implement that behavior.

| Component | What it describes | PocketMine responsibility / combination |
| --- | --- | --- |
| `block_entity` | Dynamic properties, container slots/title and experimental entity-storage metadata | Implement an actual Tile/inventory and persistence; metadata alone is not a chest |
| `block_tags` | Named tags on a block definition | Use explicit PHP matching when server rules need tags |
| `chest_obstruction` | How the client considers obstruction above chests | Check host inventory/obstruction behavior for custom shapes |
| `collision_box` | One or more physical client boxes, or disabled collision | Override PHP collision boxes to match |
| `connection_rule` | Client connection acceptance rules | Use a connection resolver for server-owned states |
| `crafting_table` | Crafting recipe tags and UI name | Open `CraftingTableInventory`; custom recipes must also be registered |
| `destructible_by_explosion` | Client explosion resistance metadata | Set matching `BlockBreakInfo` blast resistance |
| `destructible_by_mining` | Client mining duration metadata | Set PHP hardness, tool type and harvest behavior |
| `destruction_particles` | Break particle count and texture | Supply the texture; verify host/client particle handling |
| `display_name` | Visible block label | Keep PHP block name/localization consistent |
| `embedded_visual` | Geometry/material visual definition embedded in a component | Supply meshes/textures; does not add gameplay |
| `entity_fall_on` | Fall-contact trigger metadata | Implement `onEntityLand()` for authoritative behavior |
| `flammable` | Catch, destroy and lava-flammability metadata | Match PHP fire behavior; do not assume metadata runs a fire simulation |
| `flower_pottable` | Client flower-pot eligibility | Implement/verify server pot insertion support |
| `friction` | Client surface friction | Match `getFrictionFactor()`; the conventions differ (see ice tile) |
| `geometry` | Mesh identifier, optional bone visibility/culling | Supply geometry; collision is separate |
| `instrument_sound` | Instrument sound metadata | Implement note interaction/sound emission if desired |
| `item_visual` | Mesh/material appearance of the block item | Supply referenced assets; no inventory logic |
| `leashable` | Client block leash metadata and offset | Implement authoritative leash attachment/removal |
| `light_dampening` | Client light filtering | Match `getLightFilter()` |
| `light_emission` | Client emitted light, 0-15 | Match `getLightLevel()` and update the block on changes |
| `liquid_detection` | Client liquid interaction metadata | Match host water/liquid behavior explicitly |
| `map_color` | Color shown for the block on maps | Visual metadata; does not color its texture |
| `material_instances` | Texture and render settings per material slot | Supply atlas entries; choose opaque/alpha modes appropriately |
| `movable` | Piston movement policy | Match host movement implementation; multi-blocks need immovable/popped |
| `placement_filter` | Allowed faces and supporting block descriptors | Enforce placement checks in PHP too |
| `precipitation_interactions` | Rain/snow behavior metadata | Host weather behavior requires corresponding server support |
| `random_offset` | Client visual placement jitter | Do not let visual jitter imply server collision moved too |
| `replaceable` | Client replaceability | Match `canBeReplaced()` |
| `selection_box` | Client targeting box | Keep server ray selection compatible; separate from collision |
| `sound` | Sound group metadata | Supply/verify sound assets and host sounds |
| `support` | Client support shape metadata | Enforce structural support in PHP when gameplay needs it |
| `transformation` | Rotation, scale and translation of a visual | Transform PHP geometry/collision separately; changing visual size does not claim more cells |

## Every item component

Classes live in [`src/item/component`](../src/item/component); all 42 constructor
examples are in `ComponentCatalog::items()`. A client accepting a component does
not prove its entire Bedrock-native feature exists in the host server.

| Component | What it describes | PocketMine responsibility / combination |
| --- | --- | --- |
| `allow_off_hand` | Offhand placement permission metadata | Implement meaningful offhand use if desired |
| `bundle_interaction` | Bundle interaction configuration | Implement storage transactions, UI and persistence |
| `can_destroy_in_creative` | Whether client creative attacks destroy blocks | Enforce creative-use rules server-side |
| `compostable` | Compost probability | Host composter handling must recognize the custom item |
| `cooldown` | Named cooldown group and seconds | Match `getCooldownTag()` and `getCooldownTicks()` |
| `damage_absorption` | Damage causes absorbed by wearable metadata | Implement authoritative damage/armor handling |
| `damage` | Client attack damage metadata | Match `getAttackPoints()` or weapon base-class values |
| `digger` | Client mining targets/speeds | Extend the proper Tool class and match mining rules |
| `display_name` | Client visible name | Keep the PHP Item name and lore consistent |
| `durability` | Client maximum durability | Extend `Durable`/a tool/armor class and match max durability |
| `durability_sensor` | Threshold feedback with built-in or custom pack sounds | Implement/verify server feedback and wear paths |
| `dyeable` | Dye capability/default color metadata | Implement dye application and save resulting color |
| `enchantable` | Slot and enchantability metadata | Match host enchant compatibility and enchantment logic |
| `fire_resistant` | Client fire-resistance metadata | Match `isFireProof()` for dropped-item behavior |
| `food` | Nutrition, saturation modifier and consumption metadata | Extend `Food`; match restore values, effects and residue |
| `fuel` | Burn duration in seconds | Match `getFuelTime()` in ticks and furnace acceptance |
| `glint` | Enchantment-like visual shimmer | Cosmetic; does not add enchantment effects |
| `hand_equipped` | Tool-style held rendering | Cosmetic; use attachables for custom 3D rigs |
| `hover_text_color` | Tooltip text color | Cosmetic; no rarity/gameplay change by itself |
| `icon` | Item-atlas texture key | Supply `textures/item_texture.json` and PNG |
| `interact_button` | Client action-button label | Implement an item use hook for the actual action |
| `item_tags` | Definition tags | Use explicit host logic for recipes/conditions needing them |
| `kinetic_weapon` | Kinetic weapon behavior metadata | Implement/verify host movement/damage mechanics |
| `liquid_clipped` | Client targeting through/against liquids | Match authoritative target selection |
| `max_stack_size` | Client stack capacity | Match `getMaxStackSize()` to prevent inventory disagreement |
| `piercing_weapon` | Piercing weapon metadata | Implement authoritative targeting/damage and wear |
| `projectile` | Projectile type/power metadata | Spawn and simulate the actual server projectile |
| `rarity` | Rarity presentation | Cosmetic; does not set drop rates or value |
| `record` | Record sound, duration and comparator metadata | Supply sound definitions; implement/verify jukebox support |
| `repairable` | Repair ingredients and amounts | Implement compatible repair/anvil logic; showcase repair kit is a separate real action |
| `shooter` | Ammo and charge configuration | Implement ammo search, charge, launch, cooldown and damage |
| `should_despawn` | Client dropped-item despawn policy | Match server dropped-entity lifecycle |
| `stacked_by_data` | Stack grouping metadata | Ensure server NBT/data comparison matches intended stacking |
| `storage_item` | Client storage metadata | Implement real server inventory and serialize contents |
| `storage_weight_limit` | Client storage capacity by weight | Enforce capacity server-side |
| `storage_weight_modifier` | Weight contribution metadata | Enforce nested-storage/weight rules server-side |
| `swing_duration` | Client swing timing | Match server attack/use cooldown if gameplay depends on it |
| `swing_sounds` | Client swing sounds, including custom sound-definition names | Supply/emit intended sounds where supported |
| `throwable` | Client throw/use configuration | Implement the projectile/use hook and stack consumption |
| `use_animation` | Eat, drink, bow or other use animation | Match the server consumption/charging behavior |
| `use_modifiers` | Use duration and movement metadata | Match server use duration and movement effects |
| `wearable` | Equipment slot and protection metadata | Extend Armor and match slot, defense, durability and worn effects |

## Combine components into features

| Feature | Combine | Authoritative implementation / runnable reference |
| --- | --- | --- |
| Powered reactor | Geometry + materials + Boolean state + light permutation + flipbook | `ExpansionBlocks` toggles all parts; `crystal_reactor` uses a native three-part chain |
| Tiny lamp | Small geometry + selection + collision + powered light/material | `tiny_lantern` matches small PHP collision; a small mesh still occupies one cell |
| Corner stairs | Half + corner/cardinal traits + 10 meshes + collision permutations | `StairShapes` and `ShowcaseBlock`; test every half/corner/direction |
| Glowing cable | Connections + bone visibility + arm collision + power state | Cable resolver owns topology; light and power stay separate |
| Functional crafting station | Geometry/material + cardinal + crafting-table component | `prism_workbench` opens a real crafting inventory |
| Food with a buff | Icon + stack + food + use-animation + modifiers | `PrismFood` supplies restore values, timed effects and optional bottle residue |
| Weapon with an on-hit effect | Icon + damage + durability + enchantability + held model | `FrostBlade` uses Sword behavior and applies Slowness |
| Worn animated armor | Icon + wearable + durability + attachable + animation/controller | `PrismArmor` supplies actual defense/slot/wear; boots add a bounded worn effect |
| Throwable crystal | Icon + projectile + throwable + stack limit | `PrismPebble` uses actual snowball physics and consumption |
| Powered house | Banked part states + cardinal + per-part meshes/materials + grouped placement/breaking | `StructureLayout`, cottage template and Resonance Cell interaction |

Build one working behavior first. Add visuals, then state changes, then combinations.
Do not paste all components onto one item: bow/food use modes, incompatible slots
and storage mechanics need distinct, coherent behavior. The catalog is a reference,
not a recipe to enable everything simultaneously.

## Assets and animation

The resource pack has two separate atlases: `textures/terrain_texture.json` for
block material keys, and `textures/item_texture.json` for item icons. Paths omit
`.png`. Geometry identifiers must exactly match the referenced resource-pack
model, including namespace. Cube units are model pixels: 16 pixels is one block.
Block mesh X/Z convention normally runs -8 to 8, Y 0 to 16 in one occupied cell.
PHP collision uses block units 0 to 1; do not paste model-pixel values into an AABB.

For the tutorial block, merge these keys into your terrain atlas (do not overwrite
other content when combining packs):

```json
{"resource_pack_name":"TutorialContent","texture_name":"atlas.terrain","texture_data":{"tutorial_lamp":{"textures":"textures/blocks/tutorial_lamp"},"tutorial_lamp_on":{"textures":"textures/blocks/tutorial_lamp_on"}}}
```

For the tutorial item, the item atlas has a separate entry:

```json
{"resource_pack_name":"TutorialContent","texture_name":"atlas.items","texture_data":{"tutorial_wand":{"textures":"textures/items/tutorial_wand"}}}
```

Create the three PNGs at those paths in a resource pack with its own manifest UUIDs.
Use the showcase pack manifest as a format example, assigning new UUIDs for your
own pack and keeping them stable for subsequent updates.

Use `models/blocks/*.geo.json` for block meshes. Armor and held 3D items use
`attachables/`, `models/entity/`, render controllers, animations and animation
controllers. Attachables bind to player bones; wearing statistics remain PHP.
First-person and third-person alignment must be checked in a real client.

For animated block materials, use a vertical PNG strip of square frames plus
`textures/flipbook_textures.json`. These assets use eight 16x16 frames in a 16x128
strip. A state permutation selects the animated atlas tile; the static/off tile
remains separate. Animation playback does not require a repeating server task.

Build and check the supplied pack:

```text
python examples/CustomiesShowcase/tools/generate_assets.py
php examples/CustomiesShowcase/tests/verify.php /path/to/axolotl-checkout
python examples/CustomiesShowcase/tests/verify_assets.py
python examples/CustomiesShowcase/tools/build.py --php /path/to/pmmp/php
```

Asset generation needs Python, Pillow and ffmpeg. Building already committed
assets only needs Python and PHP with Phar. The asset test checks UTF-8, JSON,
atlas/geometry references, PNG sizes, animation/controller bones and flipbooks.
`model-preview.png` is an authored-geometry diagram, not an in-game screenshot.
Increase pack header/module versions together after changes, retain UUIDs for
updates, restart the server and reconnect clients. Do not edit a cached pack and
expect existing sessions to reload it automatically.

## Performance and measurement

Customies has no universal milliseconds-per-item cost. Registration constructs
block definitions and all runtime state combinations; workers also need compatible
registrations. This branch batches palette state insertion. Definitions are sent
to clients and assets are downloaded, so a large catalog affects startup, worker
memory, joining and client loading even when no custom block is placed.

Placed simple blocks have no mandatory custom per-tick loop. The showcase caches
layout definitions. Structure placement, toggling, inspection and breaking do work
proportional to the occupied cell count. A 96-cell house still causes 96 world
changes when toggled/broken, with lighting, neighbor notifications, protection
events and network updates in a real world. Do not use repeated whole-house power
changes as a high-frequency animation. Use client flipbooks for that.

Client costs differ from server costs: detailed meshes, transparent layers,
animated materials and many visible attachables can lower client frame rate while
server TPS remains healthy. Keep cube counts/textures modest, reuse model roles,
avoid needless alpha blending, and test on the weakest supported mobile client.

Reproduce a **synthetic** benchmark with:

```text
php examples/CustomiesShowcase/tools/benchmark.php /path/to/axolotl-checkout
```

It runs the validation harness, then measures placement/apply, toggle and grouped
breaking in an in-memory test world. It reports medians/p95 and PHP memory with
scope labels. It excludes live chunk loading/saving, lighting, network clients,
packet compression and protection-plugin load; it cannot establish player capacity
or a production TPS guarantee. See [the recorded sample](../examples/CustomiesShowcase/benchmark-sample.json).

Measure production separately: record baseline startup time, idle/main-thread
memory, worker/process memory, join/pack-transfer time, loaded chunks and tick
time; then repeat with the same world and plugins plus the custom catalog. Warm
the server, test intended simultaneous players, and stress normal interactions
and exploration. Report p95/p99 tick time, not just average TPS. A 20 TPS target
allows 50 ms per tick; leave headroom for occasional world/network work. Use the
host's profiler/timings tools to attribute actual costs before changing hardware.

## Server sizing

There is no honest fixed RAM minimum or player-per-core promise for Customies
alone. These are conservative **initial test allocations**, not measured capacity
claims or official PocketMine requirements:

| Workload to test | Starting allocation for the whole server | What to verify |
| --- | --- | --- |
| Local development / a few testers | 2 fast modern CPU cores, 2-4 GiB RAM, SSD | Startup, pack joining and a small loaded area |
| Small public server with moderate plugins/content | 4 fast cores, 4-8 GiB RAM, SSD/NVMe | Main-thread headroom, worker RAM, normal player movement and joins |
| Many worlds, large catalogs, heavy gameplay or bursty exploration | Start evaluation at 6+ fast cores and 8-16+ GiB RAM; derive final size from profiling | World/plugin/network load may dominate Customies; scale or split instances as measured |

Core counts are host allocations, not proof of main-thread parallelism. Prioritize
strong sustained single-core performance and low CPU contention; extra cores help
background jobs but cannot fix slow synchronous gameplay. Leave memory for the OS,
filesystem cache and workers, not just the main PHP process. Use 64-bit host/PHP
builds with the extensions required by Axolotl's `composer.json`, not stock web PHP.
SSD storage matters for world I/O; pre-generating areas reduces generation spikes.

Reserve disk for worlds, backups and logs rather than sizing it from the tiny
plugin PHAR alone. Size bandwidth from measured pack size and concurrent joins:
pack bytes x joining clients is a useful transfer-volume estimate, plus normal
game traffic and protocol overhead. Do not assume RAM upgrades cure CPU, disk,
client-rendering or upload bottlenecks. No particular paid host is required.

## Debugging and maintenance

| Symptom | Check |
| --- | --- |
| Pink/missing texture | Atlas key, file path/case, assigned material slot, accepted current pack |
| Invisible model | Geometry identifier, bones, dimensions, material references and pack version |
| Can see through / cannot select or break | Client selection, PHP collision/ray targeting, material mode and correct installed PHAR |
| Slab/stairs render and collide differently | Half/direction state, all relevant permutation boxes, stair neighbor half |
| Item claims a feature but nothing happens | Proper PocketMine base class and server use/event override; component alone is metadata |
| House will not place | Blocked coordinate, occupied cells, world height and place-event cancellation |
| House ability does not consume a cell | Check power and present/expected cells; incomplete/off structures intentionally refuse |
| Lag at startup/join | State-product growth, worker copies, definition/pack transfer; profile before blaming placed blocks |
| Lag only when interacting | Footprint size, repeated updates, lighting/protection listeners and packet volume |
| Low FPS with healthy TPS | Client mesh/alpha/animation cost; test simpler assets |
| World loses old content after update | Identifier/state schema or registration configuration changed; restore/migrate from backup |

Use `/cshow inspect` for held-item metadata, `/cshow inspect full` for bounded NBT,
and `/cshow inspect block full` for component names and server IDs. Builder Compass
prints coordinate batches. Inspector/wrench/compass actions are permission-gated
and throttled to one per second. Gameplay lens/cell use follows normal item/block
interaction flow and does not require operator-only debugging permission.

Keep block identifiers stable; migrating state schemas requires a deserializer
that recognizes old data. The existing corner-stair migration illustrates this.
Back up, test save/restart/reload and old inventories, then upgrade production.
Never infer client compatibility solely from passing PHP tests: test placement,
targeting, breaking, held/worn assets and animations on each supported client.

## Further reference

- [Traits and presets in this branch](traits-and-presets.md)
- [Runnable component catalog](../examples/CustomiesShowcase/src/ComponentCatalog.php)
- [Microsoft block component reference](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/blockcomponentslist?view=minecraft-bedrock-stable)
- [Microsoft item component reference](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/itemreference/examples/itemcomponentlist?view=minecraft-bedrock-stable)
- [Microsoft native multi-block restrictions](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/traits/multi_block?view=minecraft-bedrock-stable)
- [Axolotl source and dependency requirements](https://github.com/axolotl-pm/PocketMine-MP)

Component tables describe the repository wrappers. Engine feature availability and
experimental requirements can change; check the linked version-specific references
and the actual client content log before adopting a newer component in production.
