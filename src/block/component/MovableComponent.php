<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

final class MovableComponent implements BlockComponent {

	public function __construct(private readonly string $movementType = 'immovable') {
		if(!in_array($movementType, ['immovable', 'popped', 'push_pull', 'push'], true)){
			throw new \InvalidArgumentException('Invalid movement_type');
		}
	}

	public function getName(): string {
		return 'minecraft:movable';
		}
	public function getValue(): array {
		return ['movement_type' => $this->movementType];
	}
}