<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

/**
 * Client redstone metadata: whether the block conducts power and whether wire can step down over it.
 *
 * The network field names are the processed ones the client receives (`redstoneConductor`,
 * `allowsWireToStepDown`), not the behavior-pack JSON names. They match every vanilla data-driven
 * block definition shipped with the 26.60 host, and `tests/mc2660.php` compares them.
 *
 * Axolotl 5.51 computes redstone-wire connection states on the server, approximating "conductor" as
 * `isFullCube() && !isTransparent()`. Set `$redstoneConductor` to the same value for your block, or the
 * client's prediction and the server's wire states will disagree.
 */
final class RedstoneConductivityComponent implements BlockComponent {

	/**
	 * @param bool $redstoneConductor Whether the block can be powered by redstone. Mojang's default is false.
	 * @param bool $allowsWireToStepDown Whether redstone wire can stair-step downward on this block. Mojang's default is true.
	 */
	public function __construct(
		private bool $redstoneConductor = false,
		private bool $allowsWireToStepDown = true
	) {}

	public function getName(): string {
		return 'minecraft:redstone_conductivity';
	}

	public function getValue(): array {
		return [
			"allowsWireToStepDown" => $this->allowsWireToStepDown,
			"redstoneConductor" => $this->redstoneConductor
		];
	}

	public function isRedstoneConductor(): bool {
		return $this->redstoneConductor;
	}

	public function allowsWireToStepDown(): bool {
		return $this->allowsWireToStepDown;
	}
}
