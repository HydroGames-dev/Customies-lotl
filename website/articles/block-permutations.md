# Block permutations

Permutations apply client components when a state condition matches. They let one
block type choose a model, material, light value, collision box or transformation.
They are not PHP callbacks and they do not automatically implement server gameplay.

## A powered material and light

```php
use customiesdevs\customies\block\states\BlockState;
use customiesdevs\customies\block\permutations\BlockPermutation;
use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\properties\Material;

$this->addState(BlockState::bool('mycontent:powered'));
$this->addComponent(new B\LightEmissionComponent(0));
$this->addComponent(new B\MaterialInstancesComponent([
    new Material('*', 'mycontent_lamp_off')
]));
$this->addPermutation(new BlockPermutation(
    "q.block_state('mycontent:powered')",
    new B\LightEmissionComponent(15),
    [new B\MaterialInstancesComponent([
        new Material('*', 'mycontent_lamp_on')
    ])]
));
```

This branch keeps a first-component argument and accepts additional components as
the third argument. The expression reads your declared state. Provide both atlas
tiles and a matching `getLightLevel()` PHP override. Change power in a use/event
hook, then write the updated block to its world position.

## Combine state conditions

```php
$condition = "q.block_state('minecraft:vertical_half') == 'top'"
    . " && q.block_state('mycontent:powered')";
```

Treat each condition as selecting part of a component map. Different matching
permutations can affect different components. When multiple matches assign the
same component, author their order deliberately and test the final result.
Duplicate component names within an assembled permutation replace earlier values.
Do not depend on a later material map merging individual material slots.

## Rendering is not server logic

| Client permutation | Required server counterpart |
| --- | --- |
| Higher light emission | `getLightLevel()` and a block update |
| Disabled collision | PHP collision returns no boxes |
| Smaller or rotated collision | Matching transformed PHP AABBs |
| Replaceable visual/metadata | `canBeReplaced()` and placement rules |
| Different geometry | Targeting/collision remain compatible with the shape |
| Animated texture | Client flipbook; no server frame-advance loop |

The library does not evaluate arbitrary Molang to derive PHP gameplay. Use the
same state values explicitly in your methods. Client-only experiments or queries
are not a mechanism for running server-side conditions.

## Avoid a combinatorial explosion

Visual permutation entries and runtime state combinations are different counts.
Two power permutations may still coexist with four direction values, producing
eight runtime variants. Count the Cartesian product of state values when estimating
registration cost. Keep unrelated data outside block states and profile your
definition catalog, workers and join behavior.

## Debug a permutation

1. Check the exact namespaced state name and scalar type.
2. Read the actual placed state; do not assume the constructor default survived placement.
3. Check matching-entry order and duplicate component names.
4. Verify all model/material references and the accepted pack version.
5. Compare the PHP behavior with the intended client selection.
6. Test every combination that gameplay can place, and test old saved blocks.

Read [block states](block-states.html) first, then
[traits](block-traits.html) for state families maintained by placement/connectivity.
Source: [BlockPermutation](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/src/block/permutations/BlockPermutation.php).
