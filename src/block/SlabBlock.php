<?php
declare(strict_types=1);
namespace customiesdevs\customies\block;

use pocketmine\block\Block;
use pocketmine\block\utils\SupportType;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\item\Item;
use pocketmine\math\{AxisAlignedBB, Facing, Vector3};
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Apply BlockPresets::slab() before parent construction to supply its state layout and client shapes. */
class SlabBlock extends TraitBlock {
	public function deserializeState(BlockStateReader $in): void { SlabPlacement::readState($this, $in); }
	public function canBePlacedAt(Block $replace, Vector3 $click, int $face, bool $isClickedBlock): bool {
		return parent::canBePlacedAt($replace, $click, $face, $isClickedBlock) || SlabPlacement::canMerge($this, $replace, $click, $face, $isClickedBlock);
	}
	public function place(BlockTransaction $tx, Item $item, Block $replace, Block $clicked, int $face, Vector3 $click, ?Player $player = null): bool {
		return SlabPlacement::place($this, $tx, $item, $replace, $clicked, $face, $click, $player);
	}
	public function isSolid(): bool { return SlabPlacement::isDouble($this); }
	public function isTransparent(): bool { return !SlabPlacement::isDouble($this); }
	public function getSupportType(int $facing): SupportType {
		$top = $this->getState('minecraft:vertical_half')->getCurrentValue() === 'top';
		return SlabPlacement::isDouble($this) || ($top && $facing === Facing::UP) || (!$top && $facing === Facing::DOWN) ? SupportType::FULL : SupportType::NONE;
	}
	protected function recalculateCollisionBoxes(): array {
		if(SlabPlacement::isDouble($this)){ return [AxisAlignedBB::one()]; }
		return $this->getState('minecraft:vertical_half')->getCurrentValue() === 'top'
			? [new AxisAlignedBB(0,.5,0,1,1,1)] : [new AxisAlignedBB(0,0,0,1,.5,1)];
	}
	public function getDropsForCompatibleTool(Item $item): array { return [$this->asItem()->setCount(SlabPlacement::isDouble($this) ? 2 : 1)]; }
}
