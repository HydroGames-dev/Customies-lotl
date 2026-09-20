<?php

namespace customiesdevs\customies\block\component;

# TODO: Not sure of this
final class SoundComponent implements BlockComponent {

	public string $sound;

	public function __construct(string $sound
	) {
		$this->sound = $sound;
	}

	public function getName(): string {
		return 'minecraft:sound';
	}

	public function getValue(): array {
		return [
			"sound" => $this->sound
		];
	}
}