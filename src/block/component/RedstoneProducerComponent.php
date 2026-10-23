<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

use customiesdevs\customies\util\ComponentValidation;
use pocketmine\nbt\tag\ListTag;

final class RedstoneProducerComponent implements BlockComponent {

	/** @param list<string> $connectedFaces */
	public function __construct(
		private int $power,
		private ?string $stronglyPoweredFace = null,
		private array $connectedFaces = ['down', 'up', 'north', 'south', 'west', 'east'],
		private bool $transformRelative = true
	) {
		if($power < 0 || $power > 15){
			throw new \InvalidArgumentException('power must be between 0 and 15');
		}
		$this->connectedFaces = ComponentValidation::faces($connectedFaces);
		if($stronglyPoweredFace !== null){
			ComponentValidation::faces([$stronglyPoweredFace]);
		}
	}

	public function getName(): string {
		return 'minecraft:redstone_producer';
	}

	public function getValue(): array {
		$data = [
			'power' => $this->power,
			'connected_faces' => $this->connectedFaces === [] ? new ListTag() : $this->connectedFaces,
			'transform_relative' => $this->transformRelative
		];
		if($this->stronglyPoweredFace !== null){
			$data['strongly_powered_face'] = $this->stronglyPoweredFace;
		}
		return $data;
	}
}
