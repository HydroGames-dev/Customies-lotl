<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Armor, ArmorTypeInfo, ItemIdentifier};
use pocketmine\inventory\ArmorInventory;
final class PrismHelmet extends Armor implements ItemComponents {
	use ComponentItemTrait;
	public function __construct(int $id){
		parent::__construct(new ItemIdentifier($id),'Prism Helmet',new ArmorTypeInfo(2,256,ArmorInventory::SLOT_HEAD));
		$this->configureComponents('prism_helmet','Prism Helmet',1);
		$this->addComponent(new I\WearableComponent(I\WearableComponent::SLOT_ARMOR_HEAD,2));
		$this->addComponent(new I\DurabilityComponent(256));
		$this->addComponent(new I\EnchantableComponent('armor_head',12));
		$this->addComponent(new I\DyeableComponent('#39d8ce'));
	}
}
