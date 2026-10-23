<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

/** Server drops still need to be implemented by the custom block. */
final class LootComponent implements BlockComponent {

	public function __construct(private string $path) {
		if($path === '' || strlen($path) > 256){
			throw new \InvalidArgumentException('Loot table path must contain 1 to 256 characters');
		}
	}

	public function getName(): string {
		return 'minecraft:loot';
	}

	public function getValue(): string {
		return $this->path;
	}
}
