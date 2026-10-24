<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Food, ItemIdentifier};
use pocketmine\entity\effect\{EffectInstance, VanillaEffects};
class PrismFood extends Food implements ItemComponents {
	use ComponentItemTrait;
	public function __construct(int $id, string $specimen = 'prism_berry'){
		$name = ucwords(str_replace('_',' ',$specimen));
		parent::__construct(new ItemIdentifier($id),$name);
		$this->configureComponents($specimen,$name,$specimen === 'prism_elixir' ? 1 : 16);
		$this->addComponent(new I\FoodComponent($specimen === 'prism_elixir',$this->getFoodRestore(),0.6));
		$this->addComponent(new I\UseAnimationComponent($specimen === 'prism_elixir' ? 'drink' : 'eat'));
		$this->addComponent(new I\UseModifiersComponent(0.5,1.6));
		$this->addComponent(new I\CompostableComponent(65));
	}
	public function getMaxStackSize(): int { return $this->getSpecimen() === 'prism_elixir' ? 1 : 16; }
	public function getFoodRestore(): int { return match($this->getSpecimen()){ 'prism_elixir'=>0,'energized_ration'=>10,default=>6 }; }
	public function getSaturationRestore(): float { return $this->getFoodRestore()*1.2; }
	public function requiresHunger(): bool { return $this->getSpecimen() !== 'prism_elixir'; }
	public function getResidue(): \pocketmine\item\Item { return $this->getSpecimen() === 'prism_elixir' ? \pocketmine\item\VanillaItems::GLASS_BOTTLE() : parent::getResidue(); }
	public function getAdditionalEffects(): array { return match($this->getSpecimen()){ 'prism_elixir'=>[new EffectInstance(VanillaEffects::RESISTANCE(),200,0),new EffectInstance(VanillaEffects::NIGHT_VISION(),600,0)],'energized_ration'=>[new EffectInstance(VanillaEffects::SPEED(),300,0)],default=>[new EffectInstance(VanillaEffects::REGENERATION(),60,0)] }; }
}
