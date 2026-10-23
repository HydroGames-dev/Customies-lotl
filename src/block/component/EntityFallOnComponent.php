<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

final class EntityFallOnComponent implements BlockComponent {

	public function __construct(private float $minFallDistance = 0.0) {
		if(!is_finite($minFallDistance)){
			throw new \InvalidArgumentException('minFallDistance must be finite');
		}
	}

	public function getName(): string {
		return 'minecraft:entity_fall_on';
	}

	public function getValue(): array {
		return ['min_fall_distance' => $this->minFallDistance];
	}
}