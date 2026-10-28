<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\item\properties\SoundEvent;

final class SwingSoundsComponent implements ItemComponent {

	/**
	 * @param SoundEvent|string $critical Built-in enum or custom sound_definitions.json critical-hit sound.
	 * @param SoundEvent|string $hit Built-in enum or custom hit sound name.
	 * @param SoundEvent|string $miss Built-in enum or custom miss sound name.
	 */
	public function __construct(
		private SoundEvent|string $critical = SoundEvent::ATTACK_CRITICAL,
		private SoundEvent|string $hit = SoundEvent::ATTACK_STRONG,
		private SoundEvent|string $miss = SoundEvent::ATTACK_NODAMAGE,
	) {}

	public function getName(): string {
		return "minecraft:swing_sounds";
	}

	public function getValue(): array {
		return [
			"attack_critical_hit" => $this->critical instanceof SoundEvent ? $this->critical->value : $this->critical,
			"attack_hit" => $this->hit instanceof SoundEvent ? $this->hit->value : $this->hit,
			"attack_miss" => $this->miss instanceof SoundEvent ? $this->miss->value : $this->miss,
		];
	}

	public function getPropertyMapping(): ?array {
		return null;
	}
}
