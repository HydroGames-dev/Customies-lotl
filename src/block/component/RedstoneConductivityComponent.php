<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

final class RedstoneConductivityComponent implements BlockComponent {

	public function __construct(
		private bool $redstoneConductor = false,
		private bool $allowsWireToStepDown = true
	) {}

	public function getName(): string {
		return 'minecraft:redstone_conductivity';
	}

	public function getValue(): array {
		return [
			'redstone_conductor' => $this->redstoneConductor,
			'allows_wire_to_step_down' => $this->allowsWireToStepDown
		];
	}
}
