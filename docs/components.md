# Component compatibility

The component catalogs used for this update are:

- [Mojang item schemas, 1.26.30](https://github.com/Mojang/bedrock-samples/tree/main/metadata/json_schemas/server/item/1.26.30), the latest version in the supplied item schema directory when checked.
- [Mojang block schemas, 1.26.20](https://github.com/Mojang/bedrock-samples/tree/main/metadata/json_schemas/server/block/1.26.20), as requested.
- [Bedrock Wiki block components](https://wiki.bedrock.dev/blocks/block-components) and [item components](https://wiki.bedrock.dev/items/item-components) for usage context.

There are wrappers for all 44 item identifiers and all 38 non-deprecated block identifiers in those catalogs. `tests/component-catalog.json` pins these identifiers for offline coverage checks. Deprecated block event, breathability, and unit-cube components are excluded.

These classes declare component metadata sent to Bedrock clients. Their `getValue()` methods return **client NBT payloads**, which can include explicit NBT tags and protocol keys that differ from behavior-pack JSON. For example, block transformations use `RX`/`RY`/`RZ`, liquid detection uses `detectionRules`, and mining hardness uses `value`. Existing protocol-specific fields are retained. A schema catalog match does not establish that every component behaves correctly in every client release.

## Added wrappers

| Namespace | Classes | Purpose |
| --- | --- | --- |
| `item\component` | `BlockPlacerComponent` | Block reference, placement filters, replacement item, aligned placement |
| `item\component` | `EntityPlacerComponent` | Entity reference, use and dispenser block filters |
| `block\component` | `CraftingTableComponent` | Recipe tags and table title |
| `block\component` | `EntityFallOnComponent` | Minimum fall distance metadata |
| `block\component` | `LootComponent` | Behavior-pack loot table path |
| `block\component` | `MovableComponent` | Piston movement and sticky behavior |
| `block\component` | `PrecipitationInteractionsComponent` | Rain, snow accumulation and snowlogging behavior |
| `block\component` | `RedstoneConductivityComponent` | Conductivity and wire step-down behavior |
| `block\component` | `RedstoneConsumerComponent` | Incoming signal threshold and propagation |
| `block\component` | `RedstoneProducerComponent` | Output strength, connected faces and strongly powered face |
| `block\component` | `TickComponent` | Tick interval range and looping |

All namespaces above are relative to `customiesdevs\customies`.

```php
use customiesdevs\customies\item\component\BlockPlacerComponent;
use customiesdevs\customies\block\component\MovableComponent;
use customiesdevs\customies\block\component\RedstoneProducerComponent;

$item->addComponent(new BlockPlacerComponent(
    block: 'example:custom_block',
    alignedPlacement: true
));
$block->addComponent(new MovableComponent(MovableComponent::MOVEMENT_IMMOVABLE));
$block->addComponent(new RedstoneProducerComponent(
    power: 15,
    stronglyPoweredFace: 'up',
    connectedFaces: ['north', 'south']
));
```

Placement filters accept block identifier strings or existing `BlockDescriptor` objects. Empty optional filters are omitted so they inherit the schema defaults. Explicit empty NBT lists are used where empty lists carry meaning, such as a redstone producer with no connected faces. Tick intervals support the full unsigned 32-bit range; values above `2147483647` use the equivalent signed NBT integer bit pattern.

Axolotl-MP owns server simulation. Placement, entity spawning, recipe handling, loot drops, piston movement, precipitation, redstone propagation and tick scheduling still require the corresponding server implementation. These wrappers do not load behavior-pack scripts or implement those mechanics. `RedstoneConsumerComponent` cannot be added to a `BlockPermutation`; Bedrock disallows it there. Experimental components require an appropriate client and feature settings.

## Migration notes

- `UseModifiersComponent` now defaults to `emitVibrations: true` and `startUsing: 'if_first'`. Pass `emitVibrations: false, startUsing: 'always'` explicitly to retain the previous defaults. Duration must be finite and non-negative; the movement multiplier must be between 0 and 1.
- `KineticWeaponComponent` now emits a flat payload and uses schema defaults: reach 0–3, creative reach inherited from reach, multiplier 1, delay 0, hitbox margin 0. Damage, dismount and knockback conditions default to absent (effect disabled). Pass an empty array to enable an effect with defaults: unlimited duration (`-1`) and zero minimum speeds. Explicit old constructor arguments continue to work. The old defaults were spear-specific values; configure those explicitly if needed.
- `PiercingWeaponComponent` now defaults to reach 0–3 and hitbox margin 0. Creative reach inherits reach unless explicitly provided. Set reach 2–4.5, creative reach 2–7.5 and hitbox margin 0.25 to retain the previous defaults.
- Weapon ranges reject reversed endpoints, missing bounds and non-finite values. Kinetic delay and effect duration must fit signed 16-bit NBT integers; negative effect durations remain valid for unlimited effects.
- Storage slots and weight limits must be 0–64; storage weight modifiers and wearable protection must be non-negative. Invalid inputs now throw `InvalidArgumentException` instead of producing unusable metadata.
- `DyeableComponent` defaults to white and rejects malformed hex colors. Sound-only and particle-only durability thresholds now serialize without null fields. Durability sensors require at least one threshold when serialized; damage absorption requires at least one cause. Fluent construction before adding entries remains supported.
- Component traits can be read and initialized immediately after construction. The factory emits `item_tags` as a list even when empty. Empty PHP arrays still represent NBT compounds for marker components such as `ReplaceableComponent`; use an explicit `ListTag` when an empty list is required.

## Verification

Use PHP with the extensions required by your Axolotl-MP installation and its installed vendor dependencies:

```sh
php tests/components.php /path/to/axolotl/vendor/autoload.php
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

The regression suite checks catalog coverage, constructor constraints, trait initialization, optional sensor effects, weapon defaults, the item factory's seconds-to-ticks conversion and icon placement, and round trips through the server's real network NBT serializer. It does not replace in-game client testing.
