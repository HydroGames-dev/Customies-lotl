<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use customiesdevs\customies\block\states\BlockState;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use InvalidArgumentException;

final class MultiBlockTrait implements BlockTrait {

	public const PART = "minecraft:multi_block_part";
	/** @var string[] */
	private const DIRECTIONS = [
		"down",
		"up",
		"north",
		"south",
		"west",
		"east",
	];

	private BlockState $part;

	public function __construct(
		private readonly string $direction,
		private readonly int $parts
	) {
		$this->validate();
		$this->part = new BlockState(self::PART, range(0, $parts - 1));
	}

	public static function create(
		string $direction,
		int $parts
	): self {
		return new self($direction, $parts);
	}

	public static function vertical(int $parts = 2): self {
		return new self("up", $parts);
	}

	public static function down(int $parts = 2): self {
		return new self("down", $parts);
	}

	public static function north(int $parts = 2): self {
		return new self("north", $parts);
	}

	public static function south(int $parts = 2): self {
		return new self("south", $parts);
	}

	public static function west(int $parts = 2): self {
		return new self("west", $parts);
	}

	public static function east(int $parts = 2): self {
		return new self("east", $parts);
	}

	public function __clone(){
		$this->part = clone $this->part;
	}

	public function getName(): string {
		return "minecraft:multi_block";
	}

	public function getDirection(): string {
		return $this->direction;
	}

	public function getParts(): int {
		return $this->parts;
	}

	public function getStates(): array {
		return [$this->part];
	}

	/**
	 * Returns the relative position for a particular part.
	 *
	 * Part 0 is always the originally placed block.
	 */
	public function getOffsetForPart(int $part, ?string $cardinalDirection = null): Vector3 {
		if($part < 0 || $part >= $this->parts){
			throw new InvalidArgumentException("Invalid multi-block part {$part}; valid range is 0-" . ($this->parts - 1) . ".");
		}
		return match(in_array($this->direction, ["up", "down"], true) ? $this->direction : ($cardinalDirection ?? $this->direction)){
			"down" => new Vector3(0, -$part, 0),
			"up" => new Vector3(0, $part, 0),
			"north" => new Vector3(0, 0, -$part),
			"south" => new Vector3(0, 0, $part),
			"west" => new Vector3(-$part, 0, 0),
			"east" => new Vector3($part, 0, 0),
		};
	}

	public function applyPlacement(BlockTraitContext $context): void {
		$this->part->setCurrentValue(0);
		$block = $context->getBlock();
		$pos = $context->getBlockReplace()->getPosition();
		$direction = $block->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)?->getCurrentValue();
		for($i = 1; $i < $this->parts; ++$i){
			$target = $pos->addVector($this->getOffsetForPart($i, $direction));
			if(!$context->getTransaction()->fetchBlock($target)->canBeReplaced()){
				throw new \RuntimeException("Multi-block placement is obstructed");
			}
			$part = clone $block;
			$part->getState(self::PART)->setCurrentValue($i);
			$context->getTransaction()->addBlock($target, $part);
		}
	}

	public function update(BlockTraitContext $context): void {
		// Multi-block topology is established at placement time.
	}

	public function toNBT(): CompoundTag {
		return CompoundTag::create()
			->setString("name", $this->getName())
			->setTag(
				"enabled_states",
				CompoundTag::create()
					->setByte(self::PART, 1)
			)
			->setString("direction", $this->direction)
			->setInt("parts", $this->parts);
	}

	private function validate(): void {
		if(!in_array($this->direction, self::DIRECTIONS, true)){
			throw new InvalidArgumentException("Invalid multi-block direction '{$this->direction}'.");
		}
		if($this->parts < 2 || $this->parts > 4){
			throw new InvalidArgumentException("Multi-block parts must be between 2 and 4.");
		}
	}
}