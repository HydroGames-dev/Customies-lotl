<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Item, ItemIdentifier, ItemUseResult};
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\entity\effect\{EffectInstance, VanillaEffects};

final class ShowcaseItem extends Item implements ItemComponents {
	use ComponentItemTrait;
	public const PLAYABLE = ['inspector','state_wrench','pulse_wand','prism','ember_fuel','pigment'];
	public function __construct(int $id, string $specimen){
		$name = ucwords(str_replace('_',' ',$specimen));
		parent::__construct(new ItemIdentifier($id),$name);
		$this->configureComponents($specimen,$name,$this->stackSize($specimen));
		if(in_array($specimen,['inspector','state_wrench','pulse_wand'],true)){
			$this->addComponent(new I\HandEquippedComponent());
			$this->addComponent(new I\InteractButtonComponent($specimen === 'pulse_wand' ? 'Pulse' : 'Inspect'));
		}
		if($specimen === 'pulse_wand'){ $this->addComponent(new I\CooldownComponent('showcase:pulse',2.0)); $this->addComponent(new I\GlintComponent()); }
		if($specimen === 'prism'){ $this->addComponent(new I\RarityComponent('rare')); $this->addComponent(new I\HoverTextColorComponent('aqua')); $this->addComponent(new I\AllowOffHandComponent()); }
		if($specimen === 'ember_fuel'){ $this->addComponent(new I\FuelComponent(20.0)); $this->addComponent(new I\FireResistantComponent()); }
		if($specimen === 'pigment'){ $this->addComponent(new I\DyeableComponent('#39d8ce')); }
	}
	private function stackSize(string $specimen): int {
		if(in_array($specimen,['inspector','state_wrench','pulse_wand','lab_wearable','lab_damage_absorption','lab_durability','lab_durability_sensor','lab_repairable','lab_shooter','lab_kinetic_weapon','lab_piercing_weapon','lab_storage_item','lab_bundle_interaction','lab_storage_weight_limit','lab_storage_weight_modifier'],true)){ return 1; }
		return $specimen === 'lab_max_stack_size' ? 16 : 64;
	}
	public function getMaxStackSize(): int { return $this->stackSize($this->getSpecimen()); }
	public function getAttackPoints(): int { return $this->getSpecimen() === 'lab_damage' ? 6 : parent::getAttackPoints(); }
	public function getFuelTime(): int { return in_array($this->getSpecimen(),['ember_fuel','lab_fuel'],true) ? 400 : 0; }
	public function isFireProof(): bool { return in_array($this->getSpecimen(),['ember_fuel','lab_fire_resistant'],true); }
	public function getCooldownTicks(): int { return $this->getSpecimen() === 'pulse_wand' ? 40 : 0; }
	public function getCooldownTag(): ?string { return $this->getSpecimen() === 'pulse_wand' ? 'showcase:pulse' : null; }
	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult {
		if($this->getSpecimen() !== 'pulse_wand'){ return parent::onClickAir($player,$directionVector,$returnedItems); }
		$player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(),100,0));
		$player->sendActionBarMessage('Prism pulse: Speed I for 5 seconds');
		return ItemUseResult::SUCCESS;
	}
}
