<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

final class StorageWeightLimitComponent implements ItemComponent {

	private int $maxWeightLimit;

	/**
	 * Specifies the maximum weight limit that a storage item can hold.
	 * @param int $maxWeightLimit The maximum allowed weight of the sum of all contained items. Maximum is 64. Default is 64. Value must be >= 0.
	 */
	public function __construct(int $maxWeightLimit = 64) {
		if($maxWeightLimit < 0 || $maxWeightLimit > 64){
			throw new \InvalidArgumentException('maxWeightLimit must be between 0 and 64');
		}
		$this->maxWeightLimit = $maxWeightLimit;
	}

	public function getName(): string {
		return 'minecraft:storage_weight_limit';
	}

	public function getValue(): array {
		return [
			"max_weight_limit" => $this->maxWeightLimit
		];
	}

	public function getPropertyMapping(): ?array {
		return null;
	}
}
