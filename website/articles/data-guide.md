# Data, NBT and persistence

Choose storage by what the data means and how long it must survive. A block state,
an item's named tag and a persistent server inventory are different systems.

## Choose the right storage

| Data | Appropriate storage | Reason |
| --- | --- | --- |
| Four-way orientation or power | Block states | Bounded values used in client permutations and runtime encoding |
| Item charges, quality or schema version | Namespaced item NBT | Per-stack data saved with the item |
| Custom container contents | A real server inventory plus persistence | Contents and transactions must be authoritative |
| Owner UUID, timestamps or a large counter | Tile/plugin database or other server storage | Avoid enormous block state products |
| Cosmetic description | Name/lore | Display text is not a trusted database |
| Temporary use cooldown | Host cooldown API / bounded server state | Not every transient event needs saved NBT |

NBT is typed: IntTag, StringTag and CompoundTag are not interchangeable. JSON in
the documentation is a readable representation, not a complete preservation of NBT
tag types. A JSON number alone does not say whether the wire field is byte, int or
float. Check wrapper code and the host's canonical data when format accuracy matters.

## Validate named tags before using them

Use a namespace your plugin owns. Validate type and bounds, then decide how to
handle missing, old and future schema versions. Do not silently interpret corrupt
or client-provided data as unlimited charges or an owner permission.

```php
use pocketmine\nbt\tag\IntTag;

$tag = $item->getNamedTag();
$chargesTag = $tag->getTag('mycontent:charges');
$schemaTag = $tag->getTag('mycontent:schema');
if(!$chargesTag instanceof IntTag || !$schemaTag instanceof IntTag){
    // Reject this action or migrate a recognized old format explicitly.
    return;
}
$charges = $chargesTag->getValue();
if($schemaTag->getValue() !== 1 || $charges < 0 || $charges > 100){
    return;
}
```

This is a validation fragment, not a complete item class. Read the
[charged-item tutorial](tutorial-data.html) for a working use hook.

## Clone ownership and inventory writes

Know whether a host getter returns a clone or a live object. An edited inventory
item often needs to be written back into its slot; changing an unrelated clone
does not update inventory. In an item-use hook, mutate the provided item once and
let the host apply the transaction rather than separately removing another stack.
Test count-one and stacked items, full inventories, cancellation and reconnects.

If you add mutable PHP properties to an Item or Block subclass, implement cloning
and persistence deliberately. A PHP property is not automatically saved to NBT.
TraitBlock handles its own state objects, but it cannot serialize arbitrary new
arrays, inventories or business rules you add to a subclass.

## Migrate schemas, not just numbers

1. Back up the saved world, player inventories and your plugin database together.
2. Identify an old format by identifier, schema version and expected tag types.
3. Validate old values, convert them and write the new schema marker atomically.
4. Reject unknown future versions instead of silently downgrading them.
5. Verify migration is idempotent: applying it twice must not duplicate rewards.
6. Test save/restart/reload with real old data and a rollback copy.

Block-state migration needs a compatible deserializer. Defaulting a genuinely
missing new field can be valid; swallowing all deserialization exceptions hides
corruption and unrelated errors. Preserve existing state names and types unless
you provide a deliberate migration path.

## Debug without flooding or leaking data

Log the identifier, position, schema version and a bounded summary of relevant
values. Do not dump entire inventories every tick or include unrelated player
data in a public bug report. Reproduce with a minimal item/world fixture and record
the host, Customies and pack versions separately.

Use the [state calculator](tool-states.html) to see the cost of block enums and
the [component inspector](tool-components.html) to compare readable wrapper values.
Those tools cannot validate a real saved NBT file or authorize inventory data.
