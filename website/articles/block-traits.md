# Block traits

Traits describe state families maintained by placement, position or connectivity.
In this branch, a `BlockTrait` also implements server placement/update hooks.
Use `TraitBlock` to integrate those hooks with runtime state encoding, cloning and
persistence. Add traits before its parent constructor, then register the block.

Microsoft's [trait introduction](https://learn.microsoft.com/en-us/minecraft/creator/documents/intro-block-traits?view=minecraft-bedrock-stable)
explains the native state families. The examples below describe this repository's
PHP helpers and limitations, rather than assuming behavior-pack JSON runs as PHP.

## Placement direction

```php
use customiesdevs\customies\block\preset\BlockPresets;
use customiesdevs\customies\block\traits\PlacementDirectionTrait;

BlockPresets::cardinal($this);        // state + four visual transformations
// OR:
BlockPresets::facing($this);          // state + six visual transformations
// For authored 16-angle meshes:
$this->addTrait(PlacementDirectionTrait::sixteenWay());
```

Choose the form your block actually needs; these lines are alternatives, not a
recommendation to register all three simultaneously.

| Selector / helper | State produced | Values |
| --- | --- | --- |
| `cardinal()` | `minecraft:cardinal_direction` | south, west, north, east |
| `facing()` | `minecraft:facing_direction` | down, up, south, north, west, east |
| `sixteenWay()` | `minecraft:sixteen_way_rotation` | 0 through 15 |
| `cornerAndCardinal()` | cardinal direction plus `minecraft:corner` | none, inner_left, inner_right, outer_left, outer_right |

`yRotationOffset` accepts 0, 90, 180 or 270. Match model orientation to that choice.
The cardinal/facing **preset** creates visual transformations; a raw placement
**trait** supplies state/placement logic, so you must author visual permutations.
Neither automatically rotates your PHP collision boxes.

The corner helper emits the combined corner/cardinal selector and calculates
compatible corners on the server. Current matching is limited to the supported
same-type/half behavior; do not assume arbitrary tag expressions or cross-identifier
descriptors are fully evaluated. Sixteen-way state support also requires authored
geometry: this branch's transformation wire representation uses quarter turns.

## Placement position

```php
use customiesdevs\customies\block\traits\PlacementPositionTrait;

$this->addTrait(PlacementPositionTrait::verticalHalf());
// Alternatives:
// PlacementPositionTrait::blockFace()
// PlacementPositionTrait::both()
```

| State | Meaning | Values |
| --- | --- | --- |
| `minecraft:vertical_half` | Upper/lower placement selection | bottom, top |
| `minecraft:block_face` | The placement face | down, up, south, north, west, east |

Author half/face-specific meshes and client boxes, then match PHP collision. Test
placement on top, bottom and side faces and clicks above/below the halfway point.
Combining direction and half increases the state product: four directions × two
halves already means eight combinations before corner shape or power.

## Cardinal connections

```php
use pocketmine\block\Block;
use customiesdevs\customies\block\preset\BlockPresets;

BlockPresets::connections($this,
    static fn(Block $self, Block $other, int $face): bool => $other->isSolid()
);
```

The resolver receives the block, the neighbor and the tested face. The default
resolver connects to solid neighbors. The state names in this branch are
`minecraft:connection_north`, `minecraft:connection_south`,
`minecraft:connection_west` and `minecraft:connection_east`, each Boolean. The
native `minecraft:cardinal_connections` name selects this family in trait metadata.

Use these values in bone visibility or geometry permutations and in your PHP arm
collision calculation. TraitBlock refreshes neighbor-dependent state. Avoid an
unconditional world scan every tick; test joins/disconnections after placement and
removal, including chunk boundaries and host-specific neighbor notifications.

## Native linear multi-blocks

```php
BlockPresets::cardinal($this);
BlockPresets::multiBlock($this, 'north', 2);
```

This helper creates a straight 2–4-part group. Its part state is
`minecraft:multi_block_part`, starting at zero. `up` and `down` remain vertical;
horizontal placement can follow the cardinal direction. Placement checks use one
transaction and affected-block discovery selects matching parts for grouped breaks.
Your block still needs correct per-part models, boxes and root-only drops.

Only cardinal placement direction may accompany the native multi-block trait.
Keep movement immovable or popped in base components and avoid forbidden
permutation overrides. The [official multi-block reference](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/traits/multi_block?view=minecraft-bedrock-stable)
contains engine restrictions and version/experiment conditions. Larger/nonlinear
footprints require your plugin's own layout/grouping system; they are not native
multi-block trait support.

## Registration and lifecycle checklist

1. Choose required traits; do not declare their states a second time.
2. Add states/traits before the TraitBlock parent constructor.
3. Add geometry, materials, selection, collision and state-dependent permutations.
4. Match PHP collision, light, replaceability, drops and interactions.
5. Register one stable type ID through the factory closure.
6. Test clones, every orientation/half/part, save/restart and actual clients.

The wrapper's ability to emit a trait does not prove every client version supports
it. Host protocol/data dependencies, content format and experimental features are
separate compatibility requirements. Review the target version instead of copying
an experiment setting from an older tutorial.
