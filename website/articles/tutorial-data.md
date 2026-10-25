# Tutorial: a charged item with saved data

Create a non-stackable wand whose charges survive inventory saves. Use an Item
subclass with ItemComponentsTrait, the basic preset and matching stack/cooldown
methods from the [item tutorial](coding.html#create-a-working-item). The code below
is the data/use portion to put inside that class, not a complete standalone plugin.

## Initialize new items

```php
// In your constructor, after the Item parent constructor:
$tag = $this->getNamedTag()
    ->setInt('mycontent:schema', 1)
    ->setInt('mycontent:charges', 5);
$this->setNamedTag($tag);
```

This initializes a freshly constructed item. Do not reset charges unconditionally
when an already saved stack is loaded, inspected or used.

## Validate and consume once

```php
public function onClickAir(
    \pocketmine\player\Player $player,
    \pocketmine\math\Vector3 $directionVector,
    array &$returnedItems
): \pocketmine\item\ItemUseResult {
    $tag = $this->getNamedTag();
    $schema = $tag->getTag('mycontent:schema');
    $stored = $tag->getTag('mycontent:charges');
    if(!$schema instanceof \pocketmine\nbt\tag\IntTag
        || !$stored instanceof \pocketmine\nbt\tag\IntTag
        || $schema->getValue() !== 1
        || $stored->getValue() < 1 || $stored->getValue() > 100){
        return \pocketmine\item\ItemUseResult::FAIL;
    }
    $player->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
        \pocketmine\entity\effect\VanillaEffects::SPEED(), 100, 0
    ));
    $tag->setInt('mycontent:charges', $stored->getValue() - 1);
    $this->setNamedTag($tag);
    return \pocketmine\item\ItemUseResult::SUCCESS;
}
```

The host receives the mutated item through the normal use flow. Do not also remove
a second inventory item. If your action has additional cancellable/transactional
work, commit the charge only after that work succeeds.

## Check the behavior

Start with five charges, use once, then save/restart and verify four remain.
Test zero, negative, overly large, wrong-type and future-schema values: none should
grant the effect. Test independent cloned stacks and your cooldown. Lore may show
remaining charges, but the validated NBT value remains authoritative.
