<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

/** Not supported inside block permutations by Bedrock. */
final class RedstoneConsumerComponent implements BlockComponent {

	public function __construct(private int $minPower = 0, private bool $propagatesPower = false) {
		if($minPower < 0 || $minPower > 15){
			throw new \InvalidArgumentException('minPower must be between 0 and 15');
		}
	}

	public function getName(): string {
		return 'minecraft:redstone_consumer';
	}

	public function getValue(): array {
		return ['min_power' => $this->minPower, 'propagates_power' => $this->propagatesPower];
	}
}
