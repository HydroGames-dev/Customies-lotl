<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use customiesdevs\customies\item\properties\{RepairItems,RepairAmount};
use pocketmine\item\{Sword,ToolTier,ItemIdentifier};
use pocketmine\entity\{Entity,Living};
use pocketmine\entity\effect\{EffectInstance,VanillaEffects};
final class FrostBlade extends Sword implements ItemComponents {
	use ComponentItemTrait;
	public function __construct(int $id){
		parent::__construct(new ItemIdentifier($id),'Frost Blade',ToolTier::IRON);
		$this->configureComponents('frost_blade','Frost Blade',1);
		$this->addComponent(new I\DurabilityComponent($this->getMaxDurability()));
		$this->addComponent(new I\DamageComponent($this->getAttackPoints()));
		$this->addComponent(new I\HandEquippedComponent());
		$this->addComponent(new I\EnchantableComponent('sword',14));
		$this->addComponent(new I\GlintComponent());
		$this->addComponent(new I\SwingSoundsComponent());
		$this->addComponent(new I\RepairableComponent([new RepairItems(['minecraft:amethyst_shard'],RepairAmount::numeric(64))]));
	}
	public function onAttackEntity(Entity $victim, array &$returnedItems): bool {
		$changed = parent::onAttackEntity($victim,$returnedItems);
		if($victim instanceof Living){ $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(),40,0)); }
		return $changed;
	}
}
