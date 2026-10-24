<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Armor,ArmorTypeInfo,ItemIdentifier};
use pocketmine\inventory\ArmorInventory;
use pocketmine\entity\Living;
use pocketmine\entity\effect\{EffectInstance,VanillaEffects};
abstract class PrismArmor extends Armor implements ItemComponents {
	use ComponentItemTrait;
	public const PROFILES = [
		'prism_helmet'=>[2,256,ArmorInventory::SLOT_HEAD,'slot.armor.head','armor_head'],
		'prism_chestplate'=>[6,384,ArmorInventory::SLOT_CHEST,'slot.armor.chest','armor_torso'],
		'prism_leggings'=>[5,360,ArmorInventory::SLOT_LEGS,'slot.armor.legs','armor_legs'],
		'prism_boots'=>[2,300,ArmorInventory::SLOT_FEET,'slot.armor.feet','armor_feet']
	];
	public function __construct(int $id,string $specimen){
		[$armor,$durability,$slot,$wearable,$enchantable] = self::PROFILES[$specimen];
		$name = ucwords(str_replace('_',' ',$specimen));
		parent::__construct(new ItemIdentifier($id),$name,new ArmorTypeInfo($armor,$durability,$slot));
		$this->configureComponents($specimen,$name,1);
		$this->addComponent(new I\WearableComponent($wearable,$armor));
		$this->addComponent(new I\DurabilityComponent($durability));
		$this->addComponent(new I\EnchantableComponent($enchantable,12));
		$this->addComponent(new I\DyeableComponent('#39d8ce'));
	}
	public function onTickWorn(Living $entity): bool {
		if($this->getSpecimen() === 'prism_boots'){
			$current = $entity->getEffects()->get(VanillaEffects::JUMP_BOOST());
			if($current === null || $current->getDuration() < 20){ $entity->getEffects()->add(new EffectInstance(VanillaEffects::JUMP_BOOST(),40,0)); }
		}
		return false;
	}
}
