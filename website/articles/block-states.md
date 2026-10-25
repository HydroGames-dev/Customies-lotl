# Block states

A state is a persistent, discrete property of a block. The block identifier says
what type it is; states distinguish its variants. Use states for orientation,
power, a bounded phase or a placement half. Use server storage for inventories,
owner UUIDs, timestamps and large or continuously changing counters.

## Define the state before construction

`TraitBlock` calculates its runtime state layout during the parent constructor.
Add your custom states and traits first. This branch supports Boolean, integer
and string enumerations through `BlockState`.

```php
use customiesdevs\customies\block\states\BlockState;

$this->addState(BlockState::bool('mycontent:powered'));
$this->addState(BlockState::range('mycontent:phase', 0, 2));
$this->addState(new BlockState('mycontent:mode', ['idle', 'working', 'blocked']));
// Then call TraitBlock's parent constructor.
```

Use a nonempty, namespaced name and a nonempty list of unique values of one scalar
type. The current value initially equals the first enum value. Mixed types,
duplicates and values outside the enum are rejected by this wrapper. Declare
ascending ranges explicitly; do not rely on accidentally reversed `range()` input.

## Read and change a value

```php
$state = $this->getState('mycontent:powered');
if($state !== null){
    $state->setCurrentValue(!$state->getCurrentValue());
    $position = $this->getPosition();
    $position->getWorld()->setBlock($position, $this);
}
```

`getState()` returns a BlockState or null. `setCurrentValue()` validates the type
and enum membership. Updating the object is not enough to update a placed block;
write it into the world. Match PHP lighting, collision and interactions to the new
value. Always follow normal placement/interaction events rather than bypassing
protection with an unrelated repeating task.

## States versus traits versus permutations

| Concept | Purpose | Example |
| --- | --- | --- |
| State | Stores one bounded value | `mycontent:powered = true` |
| Trait | Supplies placement/connection-owned states and update logic | Cardinal direction when placed |
| Permutation | Selects client components using state conditions | Powered material and light |
| PHP override | Enforces the authoritative behavior | Server `getLightLevel()` |

Do not manually add a state already supplied by a trait or preset. Duplicate names
are rejected. `minecraft:cardinal_connections` is the connection trait's selector;
this branch exposes four Boolean connection states, not a single numeric mask.

## Count the combinations

Multiply the number of values in every state. Four directions × two power values
× three phases means 24 runtime combinations. Adding 16 charge values raises that
to 384, even if your gameplay only reaches a small fraction of them. Registration,
serialization and client palettes must account for those combinations.

Keep enums small, especially for client portability. The Customies constructor
accepting an enum is not proof every value count is accepted by every client.
Split unrelated block types when that makes the state product smaller, and measure
startup/join memory before expanding large catalogs.

## Persistence and schema changes

TraitBlock and the state trait handle runtime encoding and disk serialization.
The serializer writes bool/int/string according to the state's type. Preserve names
and enum types used in old chunks. Removing or changing a property can make old
saved states unreadable. Write an explicit backward-compatible deserializer when
migrating; do not catch and ignore every deserialize error.

Clones need independent state objects. TraitBlock deep-clones states and trait-owned
states; a custom implementation of the interfaces must provide the same ownership
and lifecycle guarantees. Avoid adding states after registration.

## Common problems

| Symptom | Likely cause |
| --- | --- |
| Duplicate-state exception | A preset and your code both declared the same property |
| State changes but the world stays unchanged | Updated object was not written into the world |
| Visual changes but collision/light does not | Missing PHP behavior matching the state |
| Old chunks fail after an update | State schema changed without migration |
| Unexpected startup/join cost | State values multiplied into a large palette |
| Two block clones change together | Shared mutable state objects in a custom implementation |

Next: [Block traits](block-traits.html) and [Block permutations](block-permutations.html).
Implementation: [BlockState](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/src/block/states/BlockState.php),
[state lifecycle](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/src/block/states/BlockStatesTrait.php).
