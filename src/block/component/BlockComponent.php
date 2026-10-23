<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

interface BlockComponent {

	/**
	 * The component identifier, e.g. "minecraft:collision_box"
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Component payload for client NBT; some keys differ from behavior-pack JSON.
	 * @return mixed
	 */
	public function getValue(): mixed;
}
