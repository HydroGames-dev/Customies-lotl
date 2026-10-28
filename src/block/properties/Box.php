<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\properties;

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

/**
 * Represents a collision box definition used by the `minecraft:collision_box`
 * (and similar) block components.
 *
 * Constructor coordinates use geometry origin/size pixels: a full cube spans
 * `(-8, 0, -8)` to `(8, 16, 8)`; collision may extend up to Y=24.
 * Wire/26.60 min/max coordinates use 0-16 X/Z and mirror geometry X.
 *
 * ### Constraints
 * - **Origin** is clamped within `(-8, 0, -8)` to `(8, 24, 8)`
 * - **Size** must be positive and is capped at `(16, 24, 16)`
 * - **Origin + Size** must not exceed `(8, 24, 8)`
 *
 * Out-of-bounds geometry coordinates are clamped; non-finite or empty boxes are rejected.
 * Use fromMinMax() for strict voxel coordinates or fromBlockAABB() for server units.
 */
final class Box {

	/** @var Vector3 Origin (minimum corner) of the box in block-relative coordinates. */
	private Vector3 $origin;
	/** @var Vector3 Size (width, height, depth) of the box. */
	private Vector3 $size;

	/**
	 * Creates a new collision box definition.
	 * Values are clamped to Minecraft's valid block collision bounds.
	 * @param Vector3 $origin Minimum corner of the box.
	 * @param Vector3 $size Dimensions of the box.
	 */
	public function __construct(Vector3 $origin, Vector3 $size) {
		foreach([$origin->x, $origin->y, $origin->z, $size->x, $size->y, $size->z] as $coordinate){
			if(!is_finite((float) $coordinate)){ throw new \InvalidArgumentException('Collision box coordinates must be finite'); }
		}
		if($size->x <= 0 || $size->y <= 0 || $size->z <= 0){ throw new \InvalidArgumentException('Collision box sizes must be positive'); }
		// Clamp origin
		$originX = max(-8.0, min(8.0, $origin->x));
		$originY = max(0.0, min(24.0, $origin->y));
		$originZ = max(-8.0, min(8.0, $origin->z));
		// Clamp size
		$sizeX = min(16.0, $size->x);
		$sizeY = min(24.0, $size->y);
		$sizeZ = min(16.0, $size->z);
		// Clamp to ensure origin + size is valid
		$sizeX = min($sizeX, 8.0 - $originX);
		$sizeY = min($sizeY, 24.0 - $originY);
		$sizeZ = min($sizeZ, 8.0 - $originZ);
		if($sizeX <= 0 || $sizeY <= 0 || $sizeZ <= 0){ throw new \InvalidArgumentException('Collision box lies outside the valid block bounds'); }
		$this->origin = new Vector3($originX, $originY, $originZ);
		$this->size = new Vector3($sizeX, $sizeY, $sizeZ);
	}

	/** 26.60 voxel coordinates: X/Z 0-16, Y 0-24. X is mirrored relative to geometry origin/size. */
	public static function fromMinMax(Vector3 $min, Vector3 $max): self {
		foreach([[$min->x, $max->x, 16], [$min->y, $max->y, 24], [$min->z, $max->z, 16]] as [$low, $high, $limit]){
			if(!is_finite((float) $low) || !is_finite((float) $high) || $low < 0 || $high > $limit || $low >= $high){
				throw new \InvalidArgumentException('Min/max collision bounds must be finite, increasing and within 16x24x16 pixels');
			}
		}
		return new self(new Vector3(8 - $max->x, $min->y, $min->z - 8), $max->subtractVector($min));
	}

	/** Local PocketMine block units, without a world position. */
	public static function fromBlockAABB(AxisAlignedBB $bb): self {
		return self::fromMinMax(new Vector3($bb->minX * 16, $bb->minY * 16, $bb->minZ * 16), new Vector3($bb->maxX * 16, $bb->maxY * 16, $bb->maxZ * 16));
	}

	/** Local PocketMine block units, ready for recalculateCollisionBoxes(). */
	public function toBlockAABB(): AxisAlignedBB {
		$bounds = $this->toNbtArray();
		return new AxisAlignedBB($bounds['minX'] / 16, $bounds['minY'] / 16, $bounds['minZ'] / 16, $bounds['maxX'] / 16, $bounds['maxY'] / 16, $bounds['maxZ'] / 16);
	}

	/**
	 * Legacy geometry-pixel AABB conversion. Use fromBlockAABB() for local server block units.
	 * @param AxisAlignedBB $bb The geometry-pixel bounding box to convert.
	 * @return self
	 */
	public static function fromAABB(AxisAlignedBB $bb): self {
		return new self(
			new Vector3($bb->minX, $bb->minY, $bb->minZ),
			new Vector3($bb->maxX - $bb->minX, $bb->maxY - $bb->minY, $bb->maxZ - $bb->minZ)
		);
	}

	/**
	 * Returns the origin (minimum corner) of the box.
	 * @return Vector3
	 */
	public function getOrigin(): Vector3 { return $this->origin; }

	/**
	 * Returns the size of the box.
	 * @return Vector3
	 */
	public function getSize(): Vector3 { return $this->size; }

	/**
	 * Returns the maximum corner of the box (origin + size).
	 * @return Vector3
	 */
	public function getMax(): Vector3 { return $this->origin->addVector($this->size); }

	/**
	 * Converts the box into Bedrock voxel min/max NBT, including the geometry X mirror.
	 *
	 * @return array{
	 *     minX: float,
	 *     minY: float,
	 *     minZ: float,
	 *     maxX: float,
	 *     maxY: float,
	 *     maxZ: float
	 * }
	 */
	public function toNbtArray(): array {
		$max = $this->getMax();
		return [
			"minX" => (float) (8 - $max->x),
			"minY" => (float) $this->origin->y,
			"minZ" => (float) ($this->origin->z + 8),
			"maxX" => (float) (8 - $this->origin->x),
			"maxY" => (float) $max->y,
			"maxZ" => (float) ($max->z + 8),
		];
	}

	/**
	 * Legacy geometry-pixel AABB output. Use toBlockAABB() for local server block units.
	 * @return AxisAlignedBB
	 */
	public function toAxisAlignedBB(): AxisAlignedBB {
		$max = $this->getMax();
		return new AxisAlignedBB(
			$this->origin->x, $this->origin->y, $this->origin->z,
			$max->x, $max->y, $max->z
		);
	}

	/**
	 * Returns a default full block collision box.
	 * @return Box
	 */
	public static function defaultBox(): Box {
		return new self(new Vector3(-8.0, 0.0, -8.0), new Vector3(16.0, 16.0, 16.0));
	}
}
