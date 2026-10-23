<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\block\properties\BlockDescriptor;

final class EntityPlacerComponent implements ItemComponent {

	/**
	 * @param list<string|BlockDescriptor> $useOn
	 * @param list<string|BlockDescriptor> $dispenseOn
	 */
	public function __construct(
		private string $entity,
		private array $useOn = [],
		private array $dispenseOn = []
	) {
		if($entity === ''){
			throw new \InvalidArgumentException('entity must not be empty');
		}
		foreach([$useOn, $dispenseOn] as $descriptors){
			foreach($descriptors as $descriptor){
				if(!is_string($descriptor) && !$descriptor instanceof BlockDescriptor){
					throw new \InvalidArgumentException('Placement entries must be block identifiers or BlockDescriptor instances');
				}
			}
		}
	}

	public function getName(): string {
		return 'minecraft:entity_placer';
	}

	public function getValue(): array {
		$data = ['entity' => $this->entity];
		foreach(['use_on' => $this->useOn, 'dispense_on' => $this->dispenseOn] as $key => $descriptors){
			if($descriptors !== []){
				$data[$key] = array_values(array_map(
					static fn(string|BlockDescriptor $block) => $block instanceof BlockDescriptor ? $block->toArray() : $block,
					$descriptors
				));
			}
		}
		return $data;
	}

	public function getPropertyMapping(): ?array {
		return null;
	}
}
