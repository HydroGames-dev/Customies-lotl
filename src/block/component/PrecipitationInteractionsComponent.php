<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

final class PrecipitationInteractionsComponent implements BlockComponent {

	public const NONE = 'none';
	public const OBSTRUCT_RAIN = 'obstruct_rain';
	public const OBSTRUCT_RAIN_ACCUMULATE_SNOW = 'obstruct_rain_accumulate_snow';
	public const SNOWLOGGING = 'snowlogging';

	public function __construct(private string $precipitationBehavior = self::OBSTRUCT_RAIN_ACCUMULATE_SNOW) {
		if(!in_array($precipitationBehavior, [self::NONE, self::OBSTRUCT_RAIN, self::OBSTRUCT_RAIN_ACCUMULATE_SNOW, self::SNOWLOGGING], true)){
			throw new \InvalidArgumentException('Invalid precipitation behavior');
		}
	}

	public function getName(): string {
		return 'minecraft:precipitation_interactions';
	}

	public function getValue(): array {
		return ['precipitation_behavior' => $this->precipitationBehavior];
	}
}
