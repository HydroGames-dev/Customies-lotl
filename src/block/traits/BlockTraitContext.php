<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

final class BlockTraitContext {

	public function __construct(
		private readonly BlockTransaction $transaction,
		private readonly Item $item,
		private readonly Block $blockReplace,
		private readonly Block $blockClicked,
		private readonly int $face,
		private readonly Vector3 $clickVector,
		private readonly ?Player $player
	) {}

	public function getTransaction(): BlockTransaction {
		return $this->transaction;
	}

	public function getItem(): Item {
		return $this->item;
	}

	public function getBlockReplace(): Block {
		return $this->blockReplace;
	}

	public function getBlockClicked(): Block {
		return $this->blockClicked;
	}

	public function getFace(): int {
		return $this->face;
	}

	public function getClickVector(): Vector3 {
		return $this->clickVector;
	}

	public function getPlayer(): ?Player {
		return $this->player;
	}
}