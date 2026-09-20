<?php

namespace customiesdevs\customies\block\component;

final class BlockEntityComponent implements BlockComponent {

	private bool $dynamicProperties;
	private ?int $slotCount = null;

	public function __construct(bool $dynamicProperties = false, ?int $slotCount = null){
		$this->dynamicProperties = $dynamicProperties;
		if($slotCount !== null){
			if($slotCount < 1 || $slotCount > 54){
				throw new \InvalidArgumentException("Block entity container slot count must be between 1 and 54, got {$slotCount}");
			}
			$this->slotCount = $slotCount;
		}
	}

	public function getName(): string {
		return 'minecraft:block_entity';
	}

	public function getValue(): array {
		$value = [
			"dynamic_properties" => $this->dynamicProperties
		];
		if($this->slotCount !== null){
			$value["container"] = [
				"slot_count" => $this->slotCount
			];
		}
		return $value;
	}

	public function hasContainer(): bool {
		return $this->slotCount !== null;
	}
}