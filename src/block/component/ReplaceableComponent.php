<?php

namespace customiesdevs\customies\block\component;

use pocketmine\nbt\tag\CompoundTag;

# TODO: Not sure of this
final class ReplaceableComponent implements BlockComponent {

	public function __construct(){}

	public function getName(): string {
		return 'minecraft:replaceable';
	}

	public function getValue(): CompoundTag{
		return CompoundTag::create();
	}
}