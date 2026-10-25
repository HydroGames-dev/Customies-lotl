# Items and server behavior

An item definition supplies client metadata such as icon, use animation or cooldown.
Its PocketMine class supplies authoritative behavior. Choose the base class first,
then add the matching Customies components and resource-pack assets.

## Choose the base class

| Feature | Starting point | PHP responsibilities |
| --- | --- | --- |
| Generic ability/token | `Item` + ItemComponents | Use hooks, stack rules, cooldown, effects |
| Consumable food/drink | `Food` | Hunger eligibility, nutrition, saturation, effects, residue |
| Armor | `Armor` | Slot, defense, durability, worn effects |
| Sword | `Sword` / suitable tool class | Tier, damage, wear, compatible on-hit behavior |
| Pickaxe or other tool | Corresponding Tool subclass | Tool type, tier, mining and harvest rules |
| Throwable/projectile | Suitable projectile-use base or explicit launch hook | Spawn, ownership, physics, hit behavior, consumption |
| Container/backpack | Item plus a real server inventory system | Persistent contents, transactions, weight/nesting/access rules |

`ItemComponentsTrait` stores your components, and `ItemPresets::basic()` supplies
icon, display name and client stack metadata. It does not replace the Item base
class or implement a storage container.

## Register once with a stable identifier

```php
$typeId = \pocketmine\item\ItemTypeIds::newId();
\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->registerItem(
    static fn() => new MyWand($typeId), 'mycontent:wand'
);
```

`MyWand` is your Item subclass, not a built-in Customies class. Allocate the ID
outside the closure. Use a stable string identifier for persistence and retrieval.
Get a registered item through the factory instead of inventing a network runtime ID.

## Use hooks and returned items

`onClickAir(Player, Vector3, array &$returnedItems)` handles an air-use action.
`onInteractBlock(Player, Block $blockReplace, Block $blockClicked, int $face,
Vector3 $clickVector, array &$returnedItems)` handles block use. Return the correct
`ItemUseResult`; distinguish a successful action from an unhandled or invalid one.
Respect host event cancellation and protection.

When an action consumes an item, mutate the provided stack once. Do not also remove
a second copy from the player's inventory. Use returned items/residue correctly
for containers, and test full-inventory and cancelled-use cases. Avoid doing
inventory writes both in a listener and the item hook for the same action.

## Keep the client and server synchronized

| Metadata | PHP side |
| --- | --- |
| `max_stack_size` | `getMaxStackSize()` |
| `cooldown` category + seconds | `getCooldownTag()` and `getCooldownTicks()` |
| `damage` | Weapon attack points / actual damage behavior |
| `durability` | Durable base class and max/wear/repair rules |
| `fuel` seconds | `getFuelTime()` ticks |
| `food` | Food restoration, use eligibility and residue |
| `wearable` | Armor slot, defense and worn behavior |
| `projectile` / `shooter` | Real launch, ammo, ownership, damage and consumption |

At a 20 TPS target, two seconds is 40 ticks and five seconds is 100 ticks.
The complete [wand tutorial](coding.html#create-a-working-item) demonstrates that
conversion and matching stack/cooldown values.

## Food and armor are not just icons

For food, combine food metadata with eat/drink animation and matching server timing.
Set the actual restore values and additional effects in Food methods. Return the
correct empty bottle/bowl if consumption leaves a container.

For armor, match the equipment slot, defense and durability in PHP. A custom mesh
needs an attachable with correct player-bone bindings, render controllers and
camera poses. Worn effects should refresh with a bounded cadence, not duplicate
the same effect or scan the whole world every tick.

## Item data and persistence

Use named tags for per-stack data that must be saved, with your own namespace.
Validate values before using them to control gameplay. Lore describes the item;
do not treat editable display text as authoritative data. Decide deliberately
which NBT differences should prevent stacking and keep client/server stack behavior
consistent. Keep identifiers stable when updating saved inventories.

## Tests worth running

- Registration and retrieval through the actual factory.
- NBT save/reload and clone independence for mutable custom data.
- Stack limit, cooldown group, use duration and event cancellation.
- Consumption exactly once, correct residue and full inventories.
- Attack/mining/armor behavior using the intended base class.
- Client held/worn model alignment and both camera perspectives.

Explore the [42 item component references](item-components.html), each with the
actual wrapper constructor, defaults, serialized sample and implementation link.
