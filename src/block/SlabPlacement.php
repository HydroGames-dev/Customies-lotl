<?php
declare(strict_types=1);
namespace customiesdevs\customies\block;

use customiesdevs\customies\block\states\BlockStates;
use customiesdevs\customies\block\traits\PlacementPositionTrait;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\item\Item;
use pocketmine\math\{Facing, Vector3};
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Shared server placement rules for custom slabs. Apply BlockPresets::slab() before construction. */
final class SlabPlacement {
	public const DOUBLE = 'customies:double_slab';
	public static function isDouble(BlockStates $block): bool { return $block->getState(self::DOUBLE)?->getCurrentValue() === true; }
	private static function sameHalfSlab(Block $self, Block $other): bool {
		return $other instanceof BlockStates && $other->hasState(self::DOUBLE) && $other->getTypeId() === $self->getTypeId() && !self::isDouble($other);
	}
	public static function canMerge(Block $self, Block $replace, Vector3 $click, int $face, bool $isClickedBlock): bool {
		if(!self::sameHalfSlab($self, $replace)){ return false; }
		return $replace->getState(PlacementPositionTrait::VERTICAL_HALF)->getCurrentValue() === 'top'
			? $click->y <= 0.5 || (!$isClickedBlock && $face === Facing::UP)
			: $click->y >= 0.5 || (!$isClickedBlock && $face === Facing::DOWN);
	}
	public static function place(TraitBlock $self, BlockTransaction $tx, Item $item, Block $replace, Block $clicked, int $face, Vector3 $click, ?Player $player): bool {
		$self->applyTraitPlacement($tx, $item, $replace, $clicked, $face, $click, $player);
		$merge = self::sameHalfSlab($self, $replace) && (
			$replace->getState(PlacementPositionTrait::VERTICAL_HALF)->getCurrentValue() === 'top'
				? $click->y <= 0.5 || $face === Facing::UP
				: $click->y >= 0.5 || $face === Facing::DOWN
		);
		$self->getState(self::DOUBLE)->setCurrentValue($merge);
		if($merge){ $self->getState(PlacementPositionTrait::VERTICAL_HALF)->setCurrentValue('bottom'); }
		$tx->addBlock($replace->getPosition(), $self);
		return true;
	}
	/** Missing double flags in older saved slabs mean a single slab; wrong tag types still fail. */
	public static function readState(BlockStates $block, BlockStateReader $in): void {
		foreach($block->getStates() as $state){
			$name = $state->getName();
			if($name === self::DOUBLE){
				try { $value = $in->readBool($name); }
				catch(BlockStateDeserializeException $e){ if(!str_contains($e->getMessage(), 'is missing')){ throw $e; } $value = false; }
			}else{
				$default = $state->getValues()[0];
				$value = match(true){ is_bool($default) => $in->readBool($name), is_int($default) => $in->readInt($name), default => $in->readString($name) };
			}
			$state->setCurrentValue($value);
		}
	}
}
