<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

use pocketmine\nbt\tag\IntTag;

/** Declares ticking metadata; server tick scheduling must be implemented separately. */
final class TickComponent implements BlockComponent {

	/** @param array{0: int, 1: int} $intervalRange Inclusive tick interval. */
	public function __construct(private array $intervalRange, private bool $looping = true) {
		if(!array_is_list($intervalRange) || count($intervalRange) !== 2 ||
			!is_int($intervalRange[0]) || !is_int($intervalRange[1]) ||
			$intervalRange[0] < 0 || $intervalRange[0] > $intervalRange[1] ||
			$intervalRange[1] > 4294967295){
			throw new \InvalidArgumentException('intervalRange must contain two ordered unsigned 32-bit integers');
		}
	}

	public function getName(): string {
		return 'minecraft:tick';
	}

	public function getValue(): array {
		// NBT ints are signed; preserve the bit pattern of Bedrock's uint32 interval.
		$interval = array_map(static fn(int $ticks): IntTag => new IntTag(
			$ticks > 2147483647 ? $ticks - 4294967296 : $ticks
		), $this->intervalRange);
		return ['interval_range' => $interval, 'looping' => $this->looping];
	}
}
