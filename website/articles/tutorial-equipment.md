# Tutorial: food and wearable equipment

Choose a PocketMine base class that already models the behavior. Components then
describe the same behavior to the client; attachables supply the appearance.

## A restorative food

In a `Food` subclass implementing ItemComponents, combine the basic icon/name/stack
preset with food metadata and use animation. These are constructor fragments:

```php
$this->addComponent(new \customiesdevs\customies\item\component\FoodComponent(
    false, 4, 0.6
));
$this->addComponent(new \customiesdevs\customies\item\component\UseAnimationComponent('eat'));
$this->addComponent(new \customiesdevs\customies\item\component\UseModifiersComponent(0.5, 1.6));
```

Match server food restoration and saturation with `getFoodRestore()` and
`getSaturationRestore()`. For this nutrition/modifier pair, the example's server
restoration is 4 food and 4.8 saturation. Return effects from the host Food hooks
and the correct residue for a bottle/bowl. Verify the target host's consumption
timing rather than inventing a new hook name.

## A helmet with real defense

The host Armor constructor accepts `ArmorTypeInfo` for defense, durability and
equipment slot. A constructor fragment for a head item is:

```php
parent::__construct(
    new \pocketmine\item\ItemIdentifier($id), 'My Helmet',
    new \pocketmine\item\ArmorTypeInfo(
        2, 256, \pocketmine\inventory\ArmorInventory::SLOT_HEAD
    )
);
$this->addComponent(new \customiesdevs\customies\item\component\WearableComponent(
    'slot.armor.head', 2
));
$this->addComponent(new \customiesdevs\customies\item\component\DurabilityComponent(256));
```

Add basic item components and match stack size. The wearable component is not the
defense implementation; the Armor base class supplies server equipment behavior.
A custom worn mesh needs an attachable, matching player bones, render controllers
and both camera poses. Test wear, repair, death drops and actual damage separately
from animation playback.

## Bounded worn effects

If you add an effect while worn, inspect the current effect duration and refresh
only near expiry. Respect the host's `onTickWorn(Living $entity): bool` signature
and return semantics. Do not create a whole-world player scan or rewrite unrelated
inventory slots every tick. A decorative wing on a boot does not imply a flight
ability unless you deliberately implement one.
