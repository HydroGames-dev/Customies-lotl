<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\block\properties\BlockDescriptor;

final class BlockPlacerComponent implements ItemComponent {

	/** @param list<string|BlockDescriptor> $useOn */
	public function __construct(
		private string $block,
		private array $useOn = [],
		private bool $replaceBlockItem = false,
		private bool $alignedPlacement = false
	) {
		if($block === ''){
			throw new \InvalidArgumentException('block must not be empty');
		}
		if(count($useOn) > 256){
			throw new \InvalidArgumentException('useOn supports at most 256 block descriptors');
		}
		foreach($useOn as $descriptor){
			if(!is_string($descriptor) && !$descriptor instanceof BlockDescriptor){
				throw new \InvalidArgumentException('useOn entries must be block identifiers or BlockDescriptor instances');
			}
		}
	}

	public function getName(): string {
		return 'minecraft:block_placer';
	}

	public function getValue(): array {
		$data = [
			'block' => $this->block,
			'replace_block_item' => $this->replaceBlockItem,
			'aligned_placement' => $this->alignedPlacement
		];
		if($this->useOn !== []){
			$data['use_on'] = array_values(array_map(
				static fn(string|BlockDescriptor $block) => $block instanceof BlockDescriptor ? $block->toArray() : $block,
				$this->useOn
			));
		}
		return $data;
	}

	public function getPropertyMapping(): ?array {
		return null;
	}
}
