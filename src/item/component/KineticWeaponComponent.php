<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use customiesdevs\customies\util\ComponentValidation;
use pocketmine\nbt\tag\ShortTag;

final class KineticWeaponComponent implements ItemComponent {

	/** @var array The reach of the weapon in creative mode */
	private array $creativeReach;
	/** @var array|null Conditions for damage; null disables this effect. */
	private ?array $damageConditions;
	/** @var float A flat modifier added to the damage dealt by the weapon */
	private float $damageModifier;
	/** @var float A multiplier applied to the damage dealt by the weapon */
	private float $damageMultiplier;
	/** @var int The delay between uses of the weapon, in ticks */
	private int $delay;
	/** @var array|null Conditions for dismounting; null disables this effect. */
	private ?array $dismountConditions;
	/** @var float The margin added to the hitbox of the weapon */
	private float $hitboxMargin;
	/** @var array|null Conditions for knockback; null disables this effect. */
	private ?array $knockbackConditions;
	/** @var array The reach of the weapon in survival mode */
	private array $reach;

	/**
	 * The kinetic weapon component defines the behavior of kinetic weapons, which deal damage based on their speed and other conditions.
	 * @param array|null $creativeReach The reach of the weapon in creative mode
	 * @param array|null $damageConditions Conditions that must be met for the weapon to deal damage
	 * @param float $damageModifier A flat modifier added to the damage dealt by the weapon
	 * @param float $damageMultiplier A multiplier applied to the damage dealt by the weapon
	 * @param int $delay The delay between uses of the weapon, in ticks
	 * @param array|null $dismountConditions Conditions that must be met for the weapon to dismount entities
	 * @param float $hitboxMargin The margin added to the hitbox of the weapon
	 * @param array|null $knockbackConditions Conditions that must be met for the weapon to apply knockback
	 * @param array $reach The reach of the weapon in survival mode
	 */
	public function __construct(
		?array $creativeReach = null,
		?array $damageConditions = null,
		float $damageModifier = 0.0,
		float $damageMultiplier = 1.0,
		int $delay = 0,
		?array $dismountConditions = null,
		float $hitboxMargin = 0.0,
		?array $knockbackConditions = null,
		array $reach = ['max' => 3.0, 'min' => 0.0]
	) {
		$this->creativeReach = self::validateRange($creativeReach ?? $reach, 'creative_reach');
		$this->reach = self::validateRange($reach, 'reach');
		$this->damageConditions = $damageConditions === null ? null : self::validateConditions($damageConditions, 'damage_conditions');
		$this->dismountConditions = $dismountConditions === null ? null : self::validateConditions($dismountConditions, 'dismount_conditions');
		$this->knockbackConditions = $knockbackConditions === null ? null : self::validateConditions($knockbackConditions, 'knockback_conditions');
		if(!is_finite($damageModifier) || !is_finite($damageMultiplier) || !is_finite($hitboxMargin)){
			throw new \InvalidArgumentException('Weapon modifiers must be finite');
		}
		$this->damageModifier = $damageModifier;
		$this->damageMultiplier = $damageMultiplier;
		$this->delay = ComponentValidation::short($delay, 'delay');
		$this->hitboxMargin = $hitboxMargin;
	}

	public function getName(): string {
		return 'minecraft:kinetic_weapon';
	}

	public function getValue(): array {
		$data = [
			'creative_reach' => $this->creativeReach,
			'damage_modifier' => $this->damageModifier,
			'damage_multiplier' => $this->damageMultiplier,
			'delay' => new ShortTag($this->delay),
			'hitbox_margin' => $this->hitboxMargin,
			'reach' => $this->reach
		];
		foreach([
			'damage_conditions' => $this->damageConditions,
			'dismount_conditions' => $this->dismountConditions,
			'knockback_conditions' => $this->knockbackConditions
		] as $name => $conditions){
			if($conditions !== null){
				$data[$name] = self::conditionsToArray($conditions);
			}
		}
		return $data;
	}

	public function getPropertyMapping(): ?array {
		return null;
	}

	private static function validateRange(array $range, string $name): array {
		return ComponentValidation::floatRange($range, $name);
	}

	private static function validateConditions(array $conditions, string $name): array {
		$conditions += ['max_duration' => -1, 'min_relative_speed' => 0.0, 'min_speed' => 0.0];
		if(!is_int($conditions['max_duration'])){
			throw new \InvalidArgumentException("$name.max_duration must be an integer");
		}
		ComponentValidation::short($conditions['max_duration'], "$name.max_duration");
		foreach(['min_relative_speed', 'min_speed'] as $key){
			$value = $conditions[$key];
			if((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0){
				throw new \InvalidArgumentException("$name.$key must be finite and >= 0");
			}
		}
		return [
			'max_duration' => (int) $conditions['max_duration'],
			'min_relative_speed' => (float) $conditions['min_relative_speed'],
			'min_speed' => (float) $conditions['min_speed']
		];
	}
	private static function conditionsToArray(array $conditions): array {
		return [
			"max_duration" => new ShortTag($conditions['max_duration']), // Short
			"min_relative_speed" => (float) $conditions['min_relative_speed'], // Float
			"min_speed" => (float) $conditions['min_speed'] // Float
		];
	}
}
