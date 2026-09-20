<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use customiesdevs\customies\block\states\BlockState;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use InvalidArgumentException;

final class PlacementDirectionTrait implements BlockTrait {

	public const CARDINAL_DIRECTION = "minecraft:cardinal_direction";
	public const FACING_DIRECTION = "minecraft:facing_direction";
	public const SIXTEEN_WAY_ROTATION = "minecraft:sixteen_way_rotation";
	public const CORNER_AND_CARDINAL_DIRECTION = "minecraft:corner_and_cardinal_direction";
	private const CORNER = "minecraft:corner";

	/** @var string[] */
	private const VALID_STATES = [
		self::CARDINAL_DIRECTION,
		self::FACING_DIRECTION,
		self::SIXTEEN_WAY_ROTATION,
		self::CORNER_AND_CARDINAL_DIRECTION,
	];

	private BlockState $cardinal;
	private BlockState $facing;
	private BlockState $sixteenWay;
	private BlockState $corner;

	/**
	 * @param string[] $enabledStates
	 * @param string[] $blocksToCornerWith
	 */
	public function __construct(
		private readonly array $enabledStates,
		private readonly int $yRotationOffset = 0,
		private readonly array $blocksToCornerWith = []
	) {
		$this->validate();
		$this->cardinal = new BlockState(
			self::CARDINAL_DIRECTION,
			["south", "west", "north", "east"]
		);
		$this->facing = new BlockState(
			self::FACING_DIRECTION,
			["down", "up", "south", "north", "west", "east"]
		);
		$this->sixteenWay = new BlockState(
			self::SIXTEEN_WAY_ROTATION,
			range(0, 15)
		);
		$this->corner = new BlockState(
			self::CORNER,
			["none", "inner_left", "inner_right", "outer_left", "outer_right"]
		);
	}

	public static function cardinal(int $yRotationOffset = 0): self {
		return new self([
			self::CARDINAL_DIRECTION
		], $yRotationOffset);
	}

	public static function facing(int $yRotationOffset = 0): self {
		return new self([
			self::FACING_DIRECTION
		], $yRotationOffset);
	}

	public static function sixteenWay(int $yRotationOffset = 0): self {
		return new self([
			self::SIXTEEN_WAY_ROTATION
		], $yRotationOffset);
	}

	/**
	 * @param string[] $blocksToCornerWith
	 */
	public static function cornerAndCardinal(
		array $blocksToCornerWith = [],
		int $yRotationOffset = 0
	): self {
		return new self(
			[self::CORNER_AND_CARDINAL_DIRECTION],
			$yRotationOffset,
			$blocksToCornerWith
		);
	}

	/**
	 * @param string[] $enabledStates
	 * @param string[] $blocksToCornerWith
	 */
	public static function create(
		array $enabledStates,
		int $yRotationOffset = 0,
		array $blocksToCornerWith = []
	): self {
		return new self(
			$enabledStates,
			$yRotationOffset,
			$blocksToCornerWith
		);
	}

	public function getName(): string {
		return "minecraft:placement_direction";
	}

	/**
	 * @return BlockState[]
	 */
	public function getStates(): array {
		$states = [];
		if($this->has(self::CARDINAL_DIRECTION)){
			$states[] = $this->cardinal;
		}
		if($this->has(self::FACING_DIRECTION)){
			$states[] = $this->facing;
		}
		if($this->has(self::SIXTEEN_WAY_ROTATION)){
			$states[] = $this->sixteenWay;
		}
		if($this->has(self::CORNER_AND_CARDINAL_DIRECTION)){
			$states[] = $this->cardinal;
			$states[] = $this->corner;
		}
		return $states;
	}

	private function has(string $state): bool {
		return in_array($state, $this->enabledStates, true);
	}

	/**
	 * Applies placement direction from the player's horizontal/vertical
	 * facing direction.
	 */
	public function applyPlacement(BlockTraitContext $context): void {
		$player = $context->getPlayer();
		if($player === null){
			return;
		}
		$horizontal = $player->getHorizontalFacing();
		if($this->has(self::CARDINAL_DIRECTION) ||
			$this->has(self::CORNER_AND_CARDINAL_DIRECTION)){
			$this->cardinal->setCurrentValue(
				$this->rotateCardinal($horizontal)
			);
		}
		if($this->has(self::FACING_DIRECTION)){
			$this->facing->setCurrentValue(
				$this->facingName($player->getHorizontalFacing())
			);
		}
		if($this->has(self::SIXTEEN_WAY_ROTATION)){
			$this->sixteenWay->setCurrentValue(
				$this->getSixteenWayRotation($player->getLocation()->getYaw())
			);
		}
	}

	public function update(BlockTraitContext $context): void {
		// Placement direction is intrinsic to the block and does not
		// change when neighbouring blocks are modified.
	}

	private function rotateCardinal(int $facing): string {
		$directions = [
			Facing::SOUTH => 0,
			Facing::WEST => 1,
			Facing::NORTH => 2,
			Facing::EAST => 3,
		];
		$index = $directions[$facing] ?? 0;
		$offset = match($this->yRotationOffset){
			90 => 1,
			180, -180 => 2,
			270, -90 => 3,
			default => 0,
		};
		return [
			"south",
			"west",
			"north",
			"east",
		][($index + $offset) & 3];
	}

	private function facingName(int $facing): string {
		return match($facing){
			Facing::DOWN => "down",
			Facing::UP => "up",
			Facing::SOUTH => "south",
			Facing::NORTH => "north",
			Facing::WEST => "west",
			Facing::EAST => "east",
			default => "south",
		};
	}

	private function getSixteenWayRotation(float $yaw): int {
		$yaw = fmod($yaw, 360.0);
		if($yaw < 0){
			$yaw += 360.0;
		}
		/*
		 * Bedrock:
		 * 0  = south
		 * 4  = west
		 * 8  = north
		 * 12 = east
		 */
		$rotation = (int) round($yaw / 22.5);
		$rotation += match($this->yRotationOffset){
			90 => 4,
			180, -180 => 8,
			270, -90 => 12,
			default => 0,
		};
		return $rotation & 15;
	}

	public function toNBT(): CompoundTag {
		$enabled = CompoundTag::create();
		$enabled->setByte(
			"minecraft:cardinal_direction",
			$this->has(self::CARDINAL_DIRECTION) ||
			$this->has(self::CORNER_AND_CARDINAL_DIRECTION) ? 1 : 0
		);
		$enabled->setByte(
			"minecraft:corner_and_cardinal_direction",
			$this->has(self::CORNER_AND_CARDINAL_DIRECTION) ? 1 : 0
		);
		$enabled->setByte(
			"minecraft:facing_direction",
			$this->has(self::FACING_DIRECTION) ? 1 : 0
		);
		$enabled->setByte(
			"minecraft:sixteen_way_rotation",
			$this->has(self::SIXTEEN_WAY_ROTATION) ? 1 : 0
		);
		return CompoundTag::create()
			->setString("name", $this->getName())
			->setTag("enabled_states", $enabled)
			->setFloat("y_rotation_offset", (float) $this->yRotationOffset)
			->setTag(
				"blocks_to_corner_with",
				new ListTag(array_map(
					static fn(string $id) => new StringTag($id),
					$this->blocksToCornerWith
				))
			);
	}

	private function validate(): void {
		if($this->enabledStates === []){
			throw new InvalidArgumentException("Placement direction requires at least one enabled state.");
		}
		foreach($this->enabledStates as $state){
			if(!in_array($state, self::VALID_STATES, true)){
				throw new InvalidArgumentException("Invalid placement direction state '{$state}'.");
			}
		}
		if(
			in_array(self::CORNER_AND_CARDINAL_DIRECTION, $this->enabledStates, true) &&
			count($this->enabledStates) !== 1
		){
			throw new InvalidArgumentException("minecraft:corner_and_cardinal_direction cannot be combined with other placement direction states.");
		}
		if(
			$this->blocksToCornerWith !== [] &&
			!in_array(self::CORNER_AND_CARDINAL_DIRECTION, $this->enabledStates, true)
		){
			throw new InvalidArgumentException("blocks_to_corner_with requires minecraft:corner_and_cardinal_direction.");
		}
		if($this->yRotationOffset % 90 !== 0){
			throw new InvalidArgumentException("y_rotation_offset must be a multiple of 90 degrees.");
		}
	}
}