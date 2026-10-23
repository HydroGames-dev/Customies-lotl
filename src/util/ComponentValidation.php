<?php
declare(strict_types=1);

namespace customiesdevs\customies\util;

use InvalidArgumentException;

/** Validation shared by component constructors; never silently truncates values. */
final class ComponentValidation {

	/** @return array{min: float, max: float} */
	public static function floatRange(array $range, string $name): array {
		foreach(['min', 'max'] as $key){
			$value = $range[$key] ?? null;
			if((!is_int($value) && !is_float($value)) || !is_finite((float) $value)){
				throw new InvalidArgumentException("$name.$key must be a finite number");
			}
		}
		if($range['min'] > $range['max']){
			throw new InvalidArgumentException("$name.min must be <= $name.max");
		}
		return ['min' => (float) $range['min'], 'max' => (float) $range['max']];
	}

	public static function short(int $value, string $name): int {
		if($value < -32768 || $value > 32767){
			throw new InvalidArgumentException("$name must fit a signed 16-bit integer");
		}
		return $value;
	}

	/** @param string[] $faces @return list<string> */
	public static function faces(array $faces): array {
		foreach($faces as $face){
			if(!in_array($face, ['down', 'up', 'north', 'south', 'west', 'east'], true)){
				throw new InvalidArgumentException('Invalid block face');
			}
		}
		return array_values(array_unique($faces));
	}
}
