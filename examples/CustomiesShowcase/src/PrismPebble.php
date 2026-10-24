<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component as I;
use pocketmine\item\{Snowball, ItemIdentifier};
final class PrismPebble extends Snowball implements ItemComponents {
	use ComponentItemTrait;
	public function __construct(int $id){
		parent::__construct(new ItemIdentifier($id),'Prism Pebble');
		$this->configureComponents('prism_pebble','Prism Pebble',16);
		$this->addComponent(new I\ThrowableComponent(true,1.0,0.0,1.5));
		$this->addComponent(new I\ProjectileComponent(0.25,'minecraft:snowball'));
	}
}
