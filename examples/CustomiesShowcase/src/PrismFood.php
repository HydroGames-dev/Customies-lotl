<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Food, ItemIdentifier};
use pocketmine\entity\effect\{EffectInstance, VanillaEffects};
final class PrismFood extends Food implements ItemComponents {
	use ComponentItemTrait;
	public function __construct(int $id){
		parent::__construct(new ItemIdentifier($id),'Prism Berry');
		$this->configureComponents('prism_berry','Prism Berry',16);
		$this->addComponent(new I\FoodComponent(false,6,0.6));
		$this->addComponent(new I\UseAnimationComponent('eat'));
		$this->addComponent(new I\UseModifiersComponent(0.5,1.6));
		$this->addComponent(new I\CompostableComponent(65));
	}
	public function getMaxStackSize(): int { return 16; }
	public function getFoodRestore(): int { return 6; }
	public function getSaturationRestore(): float { return 7.2; }
	public function getAdditionalEffects(): array { return [new EffectInstance(VanillaEffects::REGENERATION(),60,0)]; }
}
