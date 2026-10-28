# Traits, permutations and presets

Use `customiesdevs\customies\block\TraitBlock` for automatic placement, neighbor updates, runtime state encoding, cloning, and state serialization. Register traits and states **before** `parent::__construct()`; PocketMine calculates its state layout in that constructor. Traits are frozen after initialization. Existing blocks can still implement the interfaces directly, but must supply the same lifecycle hooks and deep cloning.

```php
use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\preset\BlockPresets;
use pocketmine\block\{BlockIdentifier, BlockTypeIds, BlockTypeInfo, BlockBreakInfo};

final class Lamp extends TraitBlock {
    public function __construct(int $typeId) {
        BlockPresets::cardinal($this, 180);
        parent::__construct(new BlockIdentifier($typeId), "Lamp",
            new BlockTypeInfo(BlockBreakInfo::instant()));
    }
}
```

Allocate a stable type ID per registered block type: the factory closure should reuse that ID when called again on workers. Add your geometry, materials, and other components before registration. Presets configure states and client permutations; they do not generate resource packs or evaluate Molang server-side. Server collision, drops, item use, and other gameplay must match the client components in your subclass.

Available block presets: `cardinal`, `facing`, `placementPosition`, `connections`, and `multiBlock`. `ItemPresets::basic($item, $texture, $name, $stackSize)` supplies icon, display name, and stack components; server item stack limits still need the corresponding Item override.

`ConnectionTrait::cardinal($resolver)` accepts an optional `Closure(Block $self, Block $neighbor, int $face): bool`. Its default connects to solid blocks. The four Boolean states are `minecraft:connection_north`, `south`, `west`, and `east` (each fully namespaced). This is separate from the `minecraft:cardinal_connections` enabled-state selector.

Multi-blocks place all 2–4 parts in one transaction, reject obstructed placement, and report matching parts through `getAffectedBlocks()` for breaking. Horizontal offsets follow cardinal direction when present. Only cardinal placement direction may accompany this trait. The base class adds an immovable component unless supplied explicitly. Do not combine rotation helpers with trait-owned states of the same name.

A permutation keeps its original single-component API and accepts additional components as a third constructor argument: `new BlockPermutation($condition, $firstComponent, [$secondComponent])`. Later duplicate component names replace earlier ones.

## Compatibility and limits

Customies 1.7.0 requires Axolotl API 5.51.0 and targets released 26.60 dependencies (protocol 2225), tested on the 5.51.0 release and stable branch plus pinned protocol master. See [the compatibility matrix](https://hydrogames-dev.github.io/Customies-lotl/protocol-updates.html) for exact refs. The suite round-trips registry/join packets and checks runtime cache invalidation. Customies does not negotiate or translate protocols. No live client validation of 1.26.52/1.26.60 has been performed.

Registration closes after the deferred startup worker snapshot. Register definitions in `onLoad()`/`onEnable()` and restart after changes. The library no longer registers in-game commands or command permissions. `php tools/doctor.php /path/to/host` checks a source checkout without starting a server.

The sixteen-way placement state is available, but arbitrary-angle geometry/bone permutations must be authored separately; the existing transformation wire format uses quarter-turn rotation indices. Corner updates currently support matching block types and vertical halves; cross-identifier `blocks_to_corner_with` descriptors and tag-expression matching need further implementation. Permutations describe client rendering; arbitrary Molang is not evaluated on the server. Multi-block integrity after explosions, piston interaction, and external block edits requires live-world validation.

## Verification

Install dependencies in an Axolotl source checkout with Composer, then run:

```text
php tests/run.php /path/to/axolotl-checkout
```

The suite covers state enumeration and metadata, clone ownership, serialization, atomic placement construction, obstruction rejection, connection refresh, placement halves, item initialization, and actual palette registration.

References: [placement direction](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/traits/placement_direction?view=minecraft-bedrock-stable), [connection](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/traits/connection?view=minecraft-bedrock-stable), [multi-block](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/traits/multi_block?view=minecraft-bedrock-stable), [Axolotl](https://github.com/axolotl-pm/PocketMine-MP).
