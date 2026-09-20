<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use pocketmine\block\Block;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

interface BlockTraits {

	/**
	 * @return BlockTrait[]
	 */
	public function getTraits(): array;

	public function addTrait(BlockTrait $trait): void;

	public function hasTrait(string $name): bool;

	public function getTrait(string $name): ?BlockTrait;

	public function initializeTraits(): void;

	public function applyTraitPlacement(
		BlockTransaction $tx,
		Item $item,
		Block $blockReplace,
		Block $blockClicked,
		int $face,
		Vector3 $clickVector,
		?Player $player
	): bool;

	public function updateTraitState(): void;

	public function serializeTraitState(BlockStateWriter $out): void;

	public function deserializeTraitState(BlockStateReader $in): void;
}