<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

final class MovableComponent implements BlockComponent {

	public const MOVEMENT_PUSH_PULL = 'push_pull';
	public const MOVEMENT_PUSH = 'push';
	public const MOVEMENT_POPPED = 'popped';
	public const MOVEMENT_IMMOVABLE = 'immovable';
	public const STICKY_NONE = 'none';
	public const STICKY_SAME = 'same';

	public function __construct(
		private string $movementType = self::MOVEMENT_PUSH_PULL,
		private string $sticky = self::STICKY_NONE
	) {
		if(!in_array($movementType, [self::MOVEMENT_PUSH_PULL, self::MOVEMENT_PUSH, self::MOVEMENT_POPPED, self::MOVEMENT_IMMOVABLE], true)){
			throw new \InvalidArgumentException('Invalid movement type');
		}
		if(!in_array($sticky, [self::STICKY_NONE, self::STICKY_SAME], true)){
			throw new \InvalidArgumentException('Invalid sticky type');
		}
		if($sticky === self::STICKY_SAME && $movementType !== self::MOVEMENT_PUSH_PULL){
			throw new \InvalidArgumentException('Sticky movement requires push_pull');
		}
	}

	public function getName(): string {
		return 'minecraft:movable';
	}

	public function getValue(): array {
		return ['movement_type' => $this->movementType, 'sticky' => $this->sticky];
	}
}
