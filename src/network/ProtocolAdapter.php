<?php
declare(strict_types=1);

namespace customiesdevs\customies\network;

use customiesdevs\customies\block\properties\Box;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function max;
use function min;

/**
 * Rewrites Customies' client definitions for one client version.
 *
 * Definitions are built in the newest format the host speaks. Older clients on multi-protocol hosts get the
 * documented older forms: one collision box instead of a list, and item components in the legacy packet. Fields an
 * older client does not know are left in place; unknown NBT fields are not removed.
 */
final class ProtocolAdapter {

	/** Legacy single collision boxes span Y 0-16 (pixels). */
	private const LEGACY_COLLISION_MAX_Y = 16.0;

	private function __construct() {}

	/**
	 * @param list<BlockPaletteEntry> $entries
	 * @return list<BlockPaletteEntry>
	 */
	public static function adaptBlockPalette(array $entries, string $version): array {
		$current = BedrockVersion::supports('collision_box_list', $version) && BedrockVersion::getCollisionBoxLimit($version) >= BedrockVersion::getCollisionBoxLimit();
		if($current){ return $entries; }
		return array_map(static fn(BlockPaletteEntry $entry): BlockPaletteEntry => new BlockPaletteEntry(
			$entry->getName(),
			new CacheableNbt(self::adaptBlockProperties(clone $entry->getStates()->getRoot(), $version))
		), $entries);
	}

	/** Adapts a block definition root (its components and every permutation's components) in place. */
	public static function adaptBlockProperties(CompoundTag $root, string $version): CompoundTag {
		$componentSets = [$root->getCompoundTag('components')];
		foreach($root->getListTag('permutations') ?? [] as $permutation){
			if($permutation instanceof CompoundTag){ $componentSets[] = $permutation->getCompoundTag('components'); }
		}
		foreach($componentSets as $components){
			$collision = $components?->getCompoundTag('minecraft:collision_box');
			if($components !== null && $collision !== null){
				$components->setTag('minecraft:collision_box', self::adaptCollisionBox($collision, $version));
			}
		}
		return $root;
	}

	/** Converts a `boxes` collision component to what $version accepts. */
	public static function adaptCollisionBox(CompoundTag $collision, string $version): CompoundTag {
		$boxes = [];
		foreach($collision->getListTag('boxes') ?? [] as $box){
			if($box instanceof CompoundTag){
				$boxes[] = [$box->getFloat('minX'), $box->getFloat('minY'), $box->getFloat('minZ'), $box->getFloat('maxX'), $box->getFloat('maxY'), $box->getFloat('maxZ')];
			}
		}
		$enabled = $collision->getByte('enabled', 1) !== 0 && $boxes !== [];
		if(!BedrockVersion::supports('collision_box_list', $version)){
			return self::legacyCollisionBox($enabled ? self::enclose($boxes) : null);
		}
		$limit = BedrockVersion::getCollisionBoxLimit($version);
		if(count($boxes) <= $limit){ return $collision; }
		// Keep the first boxes and enclose the rest, so the client never lets an entity through a solid part.
		$kept = [...array_slice($boxes, 0, $limit - 1), self::enclose(array_slice($boxes, $limit - 1))];
		return CompoundTag::create()
			->setTag('boxes', new ListTag(array_map(static fn(array $b): CompoundTag => CompoundTag::create()
				->setFloat('minX', $b[0])->setFloat('minY', $b[1])->setFloat('minZ', $b[2])
				->setFloat('maxX', $b[3])->setFloat('maxY', $b[4])->setFloat('maxZ', $b[5]), $kept)))
			->setByte('enabled', 1);
	}

	/**
	 * Clients older than 1.21.60 take item components from the legacy component packet, which only lists
	 * component-based items. Block items and plain items are already in the StartGame item table.
	 * @param list<ItemTypeEntry> $entries
	 * @return list<ItemTypeEntry>
	 */
	public static function legacyItemComponentEntries(array $entries): array {
		return array_values(array_filter($entries, static fn(ItemTypeEntry $entry): bool => $entry->isComponentBased()));
	}

	/**
	 * One `origin`/`size` box in geometry pixels: origin (-8, 0, -8) to (8, 16, 8). Disabled collision keeps the
	 * full-block placeholder the legacy format requires.
	 * @param array{float, float, float, float, float, float}|null $voxel min/max in voxel pixels
	 */
	private static function legacyCollisionBox(?array $voxel): CompoundTag {
		$origin = new Vector3(-8.0, 0.0, -8.0);
		$size = new Vector3(16.0, 16.0, 16.0);
		if($voxel !== null){
			// Clip to the legacy height. Collision entirely above it keeps a one-pixel box at the top.
			$maxY = min($voxel[4], self::LEGACY_COLLISION_MAX_Y);
			$minY = min($voxel[1], $maxY - 1.0);
			$box = Box::fromMinMax(new Vector3($voxel[0], $minY, $voxel[2]), new Vector3($voxel[3], $maxY, $voxel[5]));
			$origin = $box->getOrigin();
			$size = $box->getSize();
		}
		return CompoundTag::create()
			->setByte('enabled', $voxel === null ? 0 : 1)
			->setTag('origin', new ListTag([new FloatTag($origin->x), new FloatTag($origin->y), new FloatTag($origin->z)]))
			->setTag('size', new ListTag([new FloatTag($size->x), new FloatTag($size->y), new FloatTag($size->z)]));
	}

	/**
	 * @param non-empty-list<array{float, float, float, float, float, float}> $boxes
	 * @return array{float, float, float, float, float, float}
	 */
	private static function enclose(array $boxes): array {
		$result = $boxes[0];
		foreach($boxes as $box){
			for($i = 0; $i < 3; ++$i){
				$result[$i] = min($result[$i], $box[$i]);
				$result[$i + 3] = max($result[$i + 3], $box[$i + 3]);
			}
		}
		return $result;
	}
}
