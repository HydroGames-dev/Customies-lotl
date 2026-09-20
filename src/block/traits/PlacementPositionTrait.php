<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use customiesdevs\customies\block\states\BlockState;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use InvalidArgumentException;

final class PlacementPositionTrait implements BlockTrait {

	public const BLOCK_FACE = "minecraft:block_face";
	public const VERTICAL_HALF = "minecraft:vertical_half";

	private BlockState $blockFace;
	private BlockState $verticalHalf;

	/**
	 * @param string[] $enabledStates
	 */
	public function __construct(
		private readonly array $enabledStates
	) {
		$this->validate();
		$this->blockFace = new BlockState(
			self::BLOCK_FACE,
			[
				"down",
				"up",
				"south",
				"north",
				"west",
				"east"
			]
		);
		$this->verticalHalf = new BlockState(self::VERTICAL_HALF, ["bottom", "top"]);
	}

	public static function blockFace(): self {
		return new self([
			self::BLOCK_FACE
		]);
	}

	public static function verticalHalf(): self {
		return new self([
			self::VERTICAL_HALF
		]);
	}

	public static function both(): self {
		return new self([
			self::BLOCK_FACE,
			self::VERTICAL_HALF
		]);
	}

	/**
	 * @param string[] $enabledStates
	 */
	public static function create(array $enabledStates): self {
		return new self($enabledStates);
	}

	public function getName(): string {
		return "minecraft:placement_position";
	}

	/**
	 * @return BlockState[]
	 */
	public function getStates(): array {
		$states = [];
		if($this->has(self::BLOCK_FACE)){
			$states[] = $this->blockFace;
		}
		if($this->has(self::VERTICAL_HALF)){
			$states[] = $this->verticalHalf;
		}
		return $states;
	}

	private function has(string $state): bool {
		return in_array($state, $this->enabledStates, true);
	}

	public function applyPlacement(BlockTraitContext $context): void {
		if($this->has(self::BLOCK_FACE)){
			$this->blockFace->setCurrentValue(
				match($context->getFace()){
					Facing::DOWN => "down",
					Facing::UP => "up",
					Facing::SOUTH => "south",
					Facing::NORTH => "north",
					Facing::WEST => "west",
					Facing::EAST => "east",
					default => "up",
				}
			);
		}
		if($this->has(self::VERTICAL_HALF)){
			$clickY = $context->getClickVector()->y;
			$this->verticalHalf->setCurrentValue($clickY >= 0.5 ? "top" : "bottom");
		}
	}

	public function update(BlockTraitContext $context): void {
		// Placement position is intrinsic to the placed block.
	}

	public function toNBT(): CompoundTag {
		$enabled = CompoundTag::create();
		$enabled->setByte(
			self::BLOCK_FACE,
			$this->has(self::BLOCK_FACE) ? 1 : 0
		);
		$enabled->setByte(
			self::VERTICAL_HALF,
			$this->has(self::VERTICAL_HALF) ? 1 : 0
		);
		return CompoundTag::create()
			->setString("name", $this->getName())
			->setTag("enabled_states", $enabled);
	}

	private function validate(): void {
		if($this->enabledStates === []){
			throw new InvalidArgumentException("Placement position requires at least one enabled state.");
		}
		foreach($this->enabledStates as $state){
			if(!in_array($state, [
				self::BLOCK_FACE,
				self::VERTICAL_HALF,
			], true)){
				throw new InvalidArgumentException("Invalid placement position state '{$state}'.");
			}
		}
	}
}