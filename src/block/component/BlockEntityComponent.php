<?php

namespace customiesdevs\customies\block\component;

final class BlockEntityComponent implements BlockComponent {

	private bool $dynamicProperties;
	private ?int $slotCount = null;
	private bool $entityStorage;
	private ?string $containerTitle;

	/**
	 * Client metadata; implement authoritative Tile/inventory/entity persistence separately in PHP.
	 * @param bool $dynamicProperties Advertise per-block dynamic property metadata.
	 * @param int|null $slotCount Optional container slot count, from 1 to 54.
	 * @param bool $entityStorage Experimental 26.60 entity storage metadata (Upcoming Creator Features).
	 * @param string|null $containerTitle Experimental literal/localization title, 1-256 characters; requires a container.
	 */
	public function __construct(bool $dynamicProperties = false, ?int $slotCount = null, bool $entityStorage = false, ?string $containerTitle = null){
		$this->dynamicProperties = $dynamicProperties;
		if($containerTitle !== null && ($slotCount === null || !mb_check_encoding($containerTitle, 'UTF-8') || mb_strlen($containerTitle, 'UTF-8') < 1 || mb_strlen($containerTitle, 'UTF-8') > 256)){
			throw new \InvalidArgumentException('Container title requires a container and 1-256 UTF-8 characters');
		}
		$this->entityStorage = $entityStorage;
		$this->containerTitle = $containerTitle;
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
		if($this->entityStorage){ $value['entity_storage'] = true; }
		if($this->slotCount !== null){
			$value["container"] = [
				"slot_count" => $this->slotCount
			];
			if($this->containerTitle !== null){ $value['container']['title'] = $this->containerTitle; }
		}
		return $value;
	}

	public function hasContainer(): bool {
		return $this->slotCount !== null;
	}
}
