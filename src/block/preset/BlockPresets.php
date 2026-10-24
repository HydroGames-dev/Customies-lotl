<?php
declare(strict_types=1);
namespace customiesdevs\customies\block\preset;

use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\traits\PlacementDirectionTrait;
use customiesdevs\customies\block\traits\PlacementPositionTrait;
use customiesdevs\customies\block\traits\ConnectionTrait;
use customiesdevs\customies\block\traits\MultiBlockTrait;
use customiesdevs\customies\block\component\TransformationComponent;
use customiesdevs\customies\block\permutations\BlockPermutation;
use pocketmine\math\Vector3;

/** Apply presets in the block constructor BEFORE parent::__construct(). */
final class BlockPresets {
	public static function cardinal(TraitBlock $block, int $offset = 0): void {
		$block->addTrait(PlacementDirectionTrait::cardinal($offset));
		foreach(['north' => 0, 'west' => 90, 'south' => 180, 'east' => 270] as $face => $angle){
			$block->addPermutation(new BlockPermutation("q.block_state('minecraft:cardinal_direction') == '$face'", new TransformationComponent(new Vector3(0, $angle, 0))));
		}
	}

	public static function facing(TraitBlock $block, int $offset = 0): void {
		$block->addTrait(PlacementDirectionTrait::facing($offset));
		foreach(['north' => [0,0], 'west' => [0,90], 'south' => [0,180], 'east' => [0,270], 'up' => [90,0], 'down' => [270,0]] as $face => [$x,$y]){
			$block->addPermutation(new BlockPermutation("q.block_state('minecraft:facing_direction') == '$face'", new TransformationComponent(new Vector3($x, $y, 0))));
		}
	}

	public static function placementPosition(TraitBlock $block): void { $block->addTrait(PlacementPositionTrait::both()); }
	public static function connections(TraitBlock $block, ?\Closure $resolver = null): void { $block->addTrait(ConnectionTrait::cardinal($resolver)); }
	public static function multiBlock(TraitBlock $block, string $direction = 'up', int $parts = 2): void { $block->addTrait(MultiBlockTrait::create($direction, $parts)); }
}