<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;


use customiesdevs\customies\block\states\BlockState;
use pocketmine\block\Block;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\CompoundTag;
use Closure;
use InvalidArgumentException;

final class ConnectionTrait implements BlockTrait {

	public const CARDINAL_CONNECTIONS = "minecraft:cardinal_connections";
	public const NORTH = "minecraft:connection_north";
	public const SOUTH = "minecraft:connection_south";
	public const WEST = "minecraft:connection_west";
	public const EAST = "minecraft:connection_east";

	private BlockState $north;
	private BlockState $south;
	private BlockState $west;
	private BlockState $east;

	/**
	 * @param Closure(Block, Block, int): bool|null $connectionResolver
	 */
	public function __construct(
		private readonly bool $cardinalConnections = true,
		private readonly ?Closure $connectionResolver = null
	) {
		if(!$cardinalConnections){
			throw new InvalidArgumentException("minecraft:connection requires minecraft:cardinal_connections.");
		}
		$this->north = new BlockState(self::NORTH, [false, true]);
		$this->south = new BlockState(self::SOUTH, [false, true]);
		$this->west = new BlockState(self::WEST, [false, true]);
		$this->east = new BlockState(self::EAST, [false, true]);
	}

	public static function cardinal(
		?Closure $connectionResolver = null
	): self {
		return new self(true, $connectionResolver);
	}

	public function getName(): string {
		return "minecraft:connection";
	}

	public function getStates(): array {
		return [
			$this->north,
			$this->south,
			$this->west,
			$this->east,
		];
	}

	public function applyPlacement(BlockTraitContext $context): void {
		// See refresh() below, should be sent by Plugin.
	}

	public function update(BlockTraitContext $context): void {
		// See refresh() below, should be sent by Plugin.
	}

	/**
	 * Recalculates all four connection states.
	 * a block listener can call it whenever this block or one of its neighbours changes.
	 */
	public function refresh(
		Block $self,
		?Block $north,
		?Block $south,
		?Block $west,
		?Block $east
	): void {
		$this->north->setCurrentValue($this->canConnect($self, $north, Facing::NORTH));
		$this->south->setCurrentValue($this->canConnect($self, $south, Facing::SOUTH));
		$this->west->setCurrentValue($this->canConnect($self, $west, Facing::WEST));
		$this->east->setCurrentValue($this->canConnect($self, $east, Facing::EAST));
	}

	private function canConnect(
		Block $self,
		?Block $other,
		int $face
	): bool {
		if($other === null){
			return false;
		}
		if($this->connectionResolver !== null){
			return ($this->connectionResolver)($self, $other, $face);
		}
		return $other->isSolid();
	}

	public function toNBT(): CompoundTag {
		return CompoundTag::create()
			->setString("name", $this->getName())
			->setTag(
				"enabled_states",
				CompoundTag::create()
					->setByte(self::CARDINAL_CONNECTIONS, 1)
			);
	}
}