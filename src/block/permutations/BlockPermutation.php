<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\permutations;

use customiesdevs\customies\block\component\BlockComponent;

class BlockPermutation {

	/**
	 * @param string $condition The condition to evaluate for this permutation
	 * @param BlockComponent $components The components to apply if the condition is met
	 */
	public function __construct(
		private readonly string $condition,
		private readonly BlockComponent $components,
		private readonly array $additionalComponents = []
	) {
		foreach($additionalComponents as $component){
			if(!$component instanceof BlockComponent){ throw new \InvalidArgumentException("Permutation components must implement BlockComponent"); }
		}
	}

	/**
	 * Gets the condition string for this permutation.
	 */
	public function getCondition(): string {
		return $this->condition;
	}

	/**
	 * Gets the components associated with this permutation.
	 */
	public function getComponents(): BlockComponent {
		return $this->components;
	}

	/**
	 * Converts the BlockPermutation to an array format.
	 */
	public function toArray(): array {
		$components = [];
		foreach([$this->components, ...$this->additionalComponents] as $component){
			$components[$component->getName()] = $component->getValue();
		}
		return ["condition" => $this->condition, "components" => $components];
	}
}