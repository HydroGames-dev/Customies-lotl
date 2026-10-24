<?php
declare(strict_types=1);
namespace customiesdevs\customies\item\preset;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\component\IconComponent;
use customiesdevs\customies\item\component\DisplayNameComponent;
use customiesdevs\customies\item\component\MaxStackSizeComponent;

final class ItemPresets {
	/** Client components; implement the corresponding server item behavior in your Item subclass. */
	public static function basic(ItemComponents $item, string $texture, string $name, int $stackSize = 64): void {
		$item->addComponent(new IconComponent($texture));
		$item->addComponent(new DisplayNameComponent($name));
		$item->addComponent(new MaxStackSizeComponent($stackSize));
	}
}
