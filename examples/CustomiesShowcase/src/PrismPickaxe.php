<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use customiesdevs\customies\item\properties\{RepairItems, RepairAmount};
use pocketmine\item\{Pickaxe, ToolTier, ItemIdentifier};
final class PrismPickaxe extends Pickaxe implements ItemComponents {
	use ComponentItemTrait;
	public function __construct(int $id){
		parent::__construct(new ItemIdentifier($id),'Prism Pickaxe',ToolTier::IRON);
		$this->configureComponents('prism_pickaxe','Prism Pickaxe',1);
		$this->addComponent(new I\HandEquippedComponent());
		$this->addComponent(new I\DurabilityComponent($this->getMaxDurability()));
		$this->addComponent(new I\DamageComponent($this->getAttackPoints()));
		$this->addComponent(new I\EnchantableComponent('pickaxe',14));
		$this->addComponent(new I\DiggerComponent(true,[['block'=>['name'=>'minecraft:stone'],'speed'=>6]]));
		$this->addComponent(new I\RepairableComponent([new RepairItems(['minecraft:amethyst_shard'],RepairAmount::numeric(64))]));
	}
}
