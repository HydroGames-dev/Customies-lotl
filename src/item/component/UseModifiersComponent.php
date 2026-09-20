<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\item\properties\SoundEvent;

final class UseModifiersComponent implements ItemComponent {

	private float $useDuration;
	private float $movementModifier;
	private bool $emitVibrations;
	private ?string $startSound;
	private ?string $startUsing;

	/**
	 * Determines how an item behaves while being used.
	 * @param float       $movementModifier Modifier applied to player movement speed
	 * @param float       $useDuration How long the item takes to use (seconds)
	 * @param bool        $emitVibrations Whether the item emits vibration events
	 * @param SoundEvent|string|null $startSound Sound played when item starts being used.
	 * @param string      $startUsing When use should start: "always" or "if_first".
	 */
	public function __construct(
		float $movementModifier = 1.0,
		float $useDuration = 0.0,
		bool $emitVibrations = false,
		SoundEvent|string|null $startSound = null,
		string $startUsing = 'always'
	) {
		if(!in_array($startUsing, ['always', 'if_first'], true)){
			throw new \InvalidArgumentException('startUsing must be either "always" or "if_first", got "' . $startUsing . '"');
		}
		$this->movementModifier = $movementModifier;
		$this->useDuration = $useDuration;
		$this->emitVibrations = $emitVibrations;
		$this->startSound = $startSound instanceof SoundEvent ? (string) $startSound->value : $startSound;
		$this->startUsing = $startUsing;
	}

	public function getName(): string {
		return 'minecraft:use_modifiers';
	}

	public function getValue(): array {
		$value = [
			"emit_vibrations" => $this->emitVibrations,
			"movement_modifier" => $this->movementModifier,
			"use_duration" => $this->useDuration,
			'start_using' => $this->startUsing,
		];
		if($this->startSound !== null){
			$value['start_sound'] = $this->startSound;
		}
		return $value;
	}

	public function getPropertyMapping(): ?array {
		return ['use_duration' => (float) $this->useDuration];
	}
}