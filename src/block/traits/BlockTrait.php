<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use customiesdevs\customies\block\states\BlockState;
use pocketmine\nbt\tag\CompoundTag;

interface BlockTrait {

	public function getName(): string;

	/**
	 * @return BlockState[]
	 */
	public function getStates(): array;

	/**
	 * Serializes the Bedrock block-definition trait.
	 */
	public function toNBT(): CompoundTag;

	/**
	 * Called when the block is placed.
	 */
	public function applyPlacement(BlockTraitContext $context): void;

	/**
	 * Called when a neighbouring block changes.
	 */
	public function update(BlockTraitContext $context): void;
}