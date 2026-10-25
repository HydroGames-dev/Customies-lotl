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
	public const PLAYABLE = ['inspector','state_wrench','pulse_wand','prism','ember_fuel','pigment','scout_wand','repair_kit','clockwork_staff','wind_fan','builder_compass','chromatic_lens','resonance_cell'];
	public function __construct(int $id, string $specimen){
		$name = ucwords(str_replace('_',' ',$specimen));
		parent::__construct(new ItemIdentifier($id),$name);
		$this->configureComponents($specimen,$name,$this->stackSize($specimen));
		if(in_array($specimen,['inspector','state_wrench','pulse_wand','scout_wand','clockwork_staff','wind_fan','builder_compass','chromatic_lens'],true)){
			$this->addComponent(new I\HandEquippedComponent());
			$this->addComponent(new I\InteractButtonComponent(match($specimen){ 'pulse_wand'=>'Pulse','scout_wand'=>'Scout','clockwork_staff'=>'Haste','wind_fan'=>'Dash','builder_compass'=>'Survey','chromatic_lens'=>'Alter texture','state_wrench'=>'Cycle state',default=>'Inspect' }));
		}
		if(in_array($specimen,['clockwork_staff','wind_fan'],true)){ $this->addComponent(new I\CooldownComponent('showcase:'.$specimen,$specimen === 'clockwork_staff' ? 8.0 : 3.0)); $this->addComponent(new I\RarityComponent('rare')); }
		if($specimen === 'clockwork_staff'){ $this->addComponent(new I\GlintComponent()); }
		if($specimen === 'scout_wand'){ $this->addComponent(new I\CooldownComponent('showcase:scout',5.0)); $this->addComponent(new I\GlintComponent()); $this->addComponent(new I\RarityComponent('rare')); }
		if($specimen === 'repair_kit'){ $this->addComponent(new I\InteractButtonComponent('Repair offhand')); $this->addComponent(new I\RarityComponent('uncommon')); }
		if($specimen === 'pulse_wand'){ $this->addComponent(new I\CooldownComponent('showcase:pulse',2.0)); $this->addComponent(new I\GlintComponent()); }
		if($specimen === 'prism'){ $this->addComponent(new I\RarityComponent('rare')); $this->addComponent(new I\HoverTextColorComponent('aqua')); $this->addComponent(new I\AllowOffHandComponent()); }
		if($specimen === 'ember_fuel'){ $this->addComponent(new I\FuelComponent(20.0)); $this->addComponent(new I\FireResistantComponent()); }
		if($specimen === 'pigment'){ $this->addComponent(new I\DyeableComponent('#39d8ce')); }
	}
	private function stackSize(string $specimen): int {
		if(in_array($specimen,['inspector','state_wrench','pulse_wand','scout_wand','clockwork_staff','wind_fan','builder_compass','chromatic_lens','lab_wearable','lab_damage_absorption','lab_durability','lab_durability_sensor','lab_repairable','lab_shooter','lab_kinetic_weapon','lab_piercing_weapon','lab_storage_item','lab_bundle_interaction','lab_storage_weight_limit','lab_storage_weight_modifier'],true)){ return 1; }
		return in_array($specimen,['lab_max_stack_size','repair_kit'],true) ? 16 : 64;
	}
	public function getMaxStackSize(): int { return $this->stackSize($this->getSpecimen()); }
	public function getAttackPoints(): int { return $this->getSpecimen() === 'lab_damage' ? 6 : parent::getAttackPoints(); }
	public function getFuelTime(): int { return in_array($this->getSpecimen(),['ember_fuel','lab_fuel'],true) ? 400 : 0; }
	public function isFireProof(): bool { return in_array($this->getSpecimen(),['ember_fuel','lab_fire_resistant'],true); }
	public function getCooldownTicks(): int { return match($this->getSpecimen()){ 'pulse_wand'=>40,'scout_wand'=>100,'clockwork_staff'=>160,'wind_fan'=>60,default=>0 }; }
	public function getCooldownTag(): ?string { return match($this->getSpecimen()){ 'pulse_wand'=>'showcase:pulse','scout_wand'=>'showcase:scout','clockwork_staff'=>'showcase:clockwork_staff','wind_fan'=>'showcase:wind_fan',default=>null }; }
	/** @return EffectInstance[] */
	public function getAbilityEffects(): array {
		return $this->getSpecimen() === 'scout_wand' ? [new EffectInstance(VanillaEffects::SPEED(),200,0),new EffectInstance(VanillaEffects::JUMP_BOOST(),200,0),new EffectInstance(VanillaEffects::NIGHT_VISION(),600,0)] : [];
	}
	public function repairTarget(\pocketmine\item\Durable $target): bool {
		if($this->getSpecimen() !== 'repair_kit' || $this->getCount() < 1 || $target->getDamage() <= 0){ return false; }
		$target->setDamage(max(0,$target->getDamage()-64));
		$this->pop();
		return true;
	}
	public function onInteractBlock(Player $player, \pocketmine\block\Block $blockReplace, \pocketmine\block\Block $blockClicked, int $face, Vector3 $clickVector, array &$returnedItems): ItemUseResult {
		if(in_array($this->getSpecimen(),['chromatic_lens','resonance_cell'],true)){
			if($blockClicked instanceof ShowcaseBlock && ExpansionBlocks::has($blockClicked->getSpecimen()) && ($this->getSpecimen() !== 'chromatic_lens' || in_array(ExpansionBlocks::definitions()[$blockClicked->getSpecimen()]['mode'],['powered','color'],true)) && ExpansionBlocks::interact($blockClicked,$this,$player)){ return ItemUseResult::SUCCESS; }
			$player->sendActionBarMessage('Use on a compatible showcase block'.($this->getSpecimen() === 'resonance_cell' ? ' with a structure ability' : ''));
			return ItemUseResult::FAIL;
		}
		return parent::onInteractBlock($player,$blockReplace,$blockClicked,$face,$clickVector,$returnedItems);
	}
	public function onClickAir(Player $player, Vector3 $directionVector, array &$returnedItems): ItemUseResult {
		if($this->getSpecimen() === 'clockwork_staff'){
			$player->getEffects()->add(new EffectInstance(VanillaEffects::HASTE(),300,0));
			$player->sendActionBarMessage('Clockwork: Haste I for 15 seconds');
			return ItemUseResult::SUCCESS;
		}
		if($this->getSpecimen() === 'wind_fan'){
			$player->setMotion(new Vector3($directionVector->x*0.9,max(0.2,$directionVector->y*0.9+0.2),$directionVector->z*0.9));
			return ItemUseResult::SUCCESS;
		}
		if($this->getSpecimen() === 'repair_kit'){
			$offhand = $player->getOffHandInventory()->getItem(0);
			if(!$offhand instanceof \pocketmine\item\Durable || !$this->repairTarget($offhand)){ return ItemUseResult::FAIL; }
			$player->getOffHandInventory()->setItem(0,$offhand);
			return ItemUseResult::SUCCESS;
		}
		if($this->getSpecimen() === 'scout_wand'){
			foreach($this->getAbilityEffects() as $effect){ $player->getEffects()->add($effect); }
			$player->sendActionBarMessage('Scout: speed, jump and night vision');
			return ItemUseResult::SUCCESS;
		}
		if($this->getSpecimen() !== 'pulse_wand'){ return parent::onClickAir($player,$directionVector,$returnedItems); }
		$player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(),100,0));
		$player->sendActionBarMessage('Prism pulse: Speed I for 5 seconds');
		return ItemUseResult::SUCCESS;
	}
}
