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
	/** Client states/geometry; delegate server merging to SlabPlacement and match PHP collision/drops. */
	public static function slab(TraitBlock $block, string $bottomGeometry, string $topGeometry, string $doubleGeometry = 'minecraft:geometry.full_block'): void {
		$block->addTrait(PlacementPositionTrait::verticalHalf());
		$block->addState(\customiesdevs\customies\block\states\BlockState::bool(\customiesdevs\customies\block\SlabPlacement::DOUBLE));
		$double = "q.block_state('customies:double_slab')";
		$block->addComponent(new \customiesdevs\customies\block\component\GeometryComponent($bottomGeometry));
		$block->addComponent((new \customiesdevs\customies\block\component\CollisionBoxComponent())->addBox(new \customiesdevs\customies\block\properties\Box(new Vector3(-8,0,-8), new Vector3(16,8,16))));
		$block->addComponent(new \customiesdevs\customies\block\component\SelectionBoxComponent(true, new Vector3(-8,0,-8), new Vector3(16,8,16)));
		$block->addPermutation(new BlockPermutation("!$double && q.block_state('minecraft:vertical_half') == 'top'", new \customiesdevs\customies\block\component\GeometryComponent($topGeometry), [
			(new \customiesdevs\customies\block\component\CollisionBoxComponent())->addBox(new \customiesdevs\customies\block\properties\Box(new Vector3(-8,8,-8), new Vector3(16,8,16))),
			new \customiesdevs\customies\block\component\SelectionBoxComponent(true, new Vector3(-8,8,-8), new Vector3(16,8,16))
		]));
		$block->addPermutation(new BlockPermutation($double, new \customiesdevs\customies\block\component\GeometryComponent($doubleGeometry), [new \customiesdevs\customies\block\component\CollisionBoxComponent(), new \customiesdevs\customies\block\component\SelectionBoxComponent()]));
	}
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
