<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\item\properties\ParticleType;
use customiesdevs\customies\item\properties\SoundEvent;

final class DurabilitySensorComponent implements ItemComponent {

	/**
	 * @var array<int, array{
	 *     durability: int,
	 *     particle_type?: string,
	 *     sound_event?: string
	 * }>
	 */
	private array $durabilityThresholds = [];

	/**
	 * Enables an item to emit effects when it receives damage. Because of this, the item also needs a `minecraft:durability` component.
	 * @param array<int, array{
	 *     durability: int,
	 *     particle_type?: ParticleType|null,
	 *     sound_event?: SoundEvent|null
	 * }> $durabilityThresholds
	 */
	public function __construct(array $durabilityThresholds = []) {
		if(isset($durabilityThresholds['durability'])){
			$durabilityThresholds = [$durabilityThresholds];
		}
		foreach($durabilityThresholds as $threshold){
			if(!is_array($threshold) || !isset($threshold['durability']) || !is_int($threshold['durability'])){
				throw new \InvalidArgumentException('Durability threshold must contain an integer durability');
			}
			$this->addDurabilityThreshold(
				$threshold['durability'],
				$threshold['particle_type'] ?? null,
				$threshold['sound_event'] ?? null
			);
		}
	}

	public function getName(): string {
		return 'minecraft:durability_sensor';
	}

	public function getValue(): array {
		if($this->durabilityThresholds === []){
			throw new \InvalidArgumentException('At least one durability threshold is required');
		}
		return ['durability_thresholds' => $this->durabilityThresholds];
	}

	public function getPropertyMapping(): ?array {
		return null;
	}

	/**
	 * Adds a durability threshold that triggers effects when the item's durability reaches the specified value.
	 * @param int $durability The durability threshold at which the effects are triggered. Must be >= 0.
	 * @param ParticleType|null $particleType The type of particle effect to emit when the threshold is reached. If null, no particle effect is emitted.
	 * @param SoundEvent|null $soundEvent The sound event to play when the threshold is reached. If null, no sound is played.
	 */
	public function addDurabilityThreshold(
		int $durability,
		?ParticleType $particleType = null,
		?SoundEvent $soundEvent = null
	): self {
		if($durability < 0){
			throw new \InvalidArgumentException("Durability threshold must be >= 0, $durability given");
		}
		if($particleType === null && $soundEvent === null){
			throw new \InvalidArgumentException("At least one of particle_type or sound_event must be specified");
		}
		$threshold = ['durability' => $durability];
		if($particleType !== null){
			$threshold['particle_type'] = $particleType->value;
		}
		if($soundEvent !== null){
			$threshold['sound_event'] = $soundEvent->value;
		}
		$this->durabilityThresholds[] = $threshold;
		return $this;
	}
}
