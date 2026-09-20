<?php

namespace customiesdevs\customies\block\component;

use pocketmine\block\utils\RandomOffsetGenerator;
use pocketmine\math\Vector3;

# TODO: Not sure of this
final class RandomOffsetComponent implements BlockComponent {

	private Vector3 $min;
	private Vector3 $max;
	private Vector3 $steps;

	/**
	 * @param Vector3|null $min Minimum offset per axis (x, y, z)
	 * @param Vector3|null $max Maximum offset per axis (x, y, z)
	 * @param Vector3|null $steps Steps per axis (x, y, z)
	 */
	public function __construct(
		?Vector3 $min = null,
		?Vector3 $max = null,
		?Vector3 $steps = null
	) {
		$this->min = $min ?? new Vector3(0.0, 0.0, 0.0);
		$this->max = $max ?? new Vector3(0.0, 0.0, 0.0);
		$this->steps = $steps ?? new Vector3(0, 0, 0);
	}

	public function getName(): string {
		return 'minecraft:random_offset';
	}

	public function getValue(): array {
		return [
			"x" => [
				"steps" => (int) $this->steps->x,
				"range" => ["min" => $this->min->x, "max" => $this->max->x]
			],
			"y" => [
				"steps" => (int) $this->steps->y,
				"range" => ["min" => $this->min->y, "max" => $this->max->y]
			],
			"z" => [
				"steps" => (int) $this->steps->z,
				"range" => ["min" => $this->min->z, "max" => $this->max->z]
			],
		];
	}

	/**
	 * Computes the horizontal (XZ) random offset vector applied at a given block position.
	 * @param int $x Block world position X
	 * @param int $z Block world position Z
	 * @return Vector3 Offset vector with calculated X and Z, Y set to 0.0
	 */
	public function getOffset(int $x, int $z): Vector3 {
		[$offsetX, $offsetZ] = RandomOffsetGenerator::horizontal(
			$x,
			$z,
			$this->min->x,
			$this->max->x,
			(int) $this->steps->x
		);
		return new Vector3($offsetX, 0.0, $offsetZ);
	}

	public function setMin(Vector3 $min): self {
		$this->min = $min;
		return $this;
	}

	public function setMax(Vector3 $max): self {
		$this->max = $max;
		return $this;
	}

	public function setSteps(Vector3 $steps): self {
		$this->steps = $steps;
		return $this;
	}

	public function setX(float $min, float $max, int $steps = 0): self {
		$this->min->x = $min;
		$this->max->x = $max;
		$this->steps->x = $steps;
		return $this;
	}

	public function setY(float $min, float $max, int $steps = 0): self {
		$this->min->y = $min;
		$this->max->y = $max;
		$this->steps->y = $steps;
		return $this;
	}

	public function setZ(float $min, float $max, int $steps = 0): self {
		$this->min->z = $min;
		$this->max->z = $max;
		$this->steps->z = $steps;
		return $this;
	}
}