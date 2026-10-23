<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\util\ComponentValidation;

final class PiercingWeaponComponent implements ItemComponent {

	/** @var array The reach of the weapon in creative mode */
	private array $creativeReach;
	/** @var float The margin added to the hitbox of the weapon */
	private float $hitboxMargin;
	/** @var array The reach of the weapon in survival mode */
	private array $reach;

	/**
	 * The piercing weapon component defines the behavior of piercing weapons, which can hit multiple entities in a single attack.
	 * @param array|null $creativeReach The reach of the weapon in creative mode
	 * @param float $hitboxMargin The margin added to the hitbox of the weapon
	 * @param array $reach The reach of the weapon in survival mode
	 */
	public function __construct(
		?array $creativeReach = null,
		float $hitboxMargin = 0.0,
		array $reach = ['max' => 3.0, 'min' => 0.0]
	) {
		$this->creativeReach = self::validateRange($creativeReach ?? $reach, 'creative_reach');
		$this->reach = self::validateRange($reach, 'reach');
		if(!is_finite($hitboxMargin)){
			throw new \InvalidArgumentException('hitboxMargin must be finite');
		}
		$this->hitboxMargin = $hitboxMargin;
	}

	public function getName(): string {
		return 'minecraft:piercing_weapon';
	}

	public function getValue(): array {
		return [
			"creative_reach" => self::rangeToArray($this->creativeReach),
			"hitbox_margin" => (float) $this->hitboxMargin,
			"reach" => self::rangeToArray($this->reach)
		];
	}

	public function getPropertyMapping(): ?array {
		return null;
	}

	private static function validateRange(array $range, string $name): array {
		return ComponentValidation::floatRange($range, $name);
	}

	private static function rangeToArray(array $range): array {
		return [
			"max" => (float) $range['max'],
			"min" => (float) $range['min']
		];
	}
}
