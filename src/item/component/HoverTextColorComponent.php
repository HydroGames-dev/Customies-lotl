<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

use pocketmine\utils\TextFormat;

final class HoverTextColorComponent implements ItemComponent {

	private string $hoverTextColor;

	/** @var array<string, string> */
	private const COLOR_CODE_MAP = [
		'black' => TextFormat::BLACK,
		'dark_blue' => TextFormat::DARK_BLUE,
		'dark_green' => TextFormat::DARK_GREEN,
		'dark_aqua' => TextFormat::DARK_AQUA,
		'dark_red' => TextFormat::DARK_RED,
		'dark_purple' => TextFormat::DARK_PURPLE,
		'gold' => TextFormat::GOLD,
		'gray' => TextFormat::GRAY,
		'dark_gray' => TextFormat::DARK_GRAY,
		'blue' => TextFormat::BLUE,
		'green' => TextFormat::GREEN,
		'aqua' => TextFormat::AQUA,
		'red' => TextFormat::RED,
		'light_purple' => TextFormat::LIGHT_PURPLE,
		'yellow' => TextFormat::YELLOW,
		'white' => TextFormat::WHITE,
		'minecoin_gold' => TextFormat::MINECOIN_GOLD,
		'material_quartz' => TextFormat::MATERIAL_QUARTZ,
		'material_iron' => TextFormat::MATERIAL_IRON,
		'material_netherite' => TextFormat::MATERIAL_NETHERITE,
		'material_redstone' => TextFormat::MATERIAL_REDSTONE,
		'material_copper' => TextFormat::MATERIAL_COPPER,
		'material_gold' => TextFormat::MATERIAL_GOLD,
		'material_emerald' => TextFormat::MATERIAL_EMERALD,
		'material_diamond' => TextFormat::MATERIAL_DIAMOND,
		'material_lapis' => TextFormat::MATERIAL_LAPIS,
		'material_amethyst' => TextFormat::MATERIAL_AMETHYST,
		'material_resin' => TextFormat::MATERIAL_RESIN,
		'party_blue' => TextFormat::PARTY_BLUE,
	];

	/**
	 * Determines the color of the item name when hovering over it.
	 * @param string $hoverTextColor Specifies the color of the item's hover text
	 * @link [ColorCodes](https://minecraft.wiki/w/Formatting_codes#Color_codes)
	 */
	public function __construct(string $hoverTextColor) {
		$this->hoverTextColor = $hoverTextColor;
	}

	public function getName(): string {
		return 'minecraft:hover_text_color';
	}

	public function getValue(): array {
		return [
			"value" => $this->hoverTextColor
		];
	}

	public function getPropertyMapping(): ?array {
		return ['hover_text_color' => $this->convertToColorCode($this->hoverTextColor)];
	}

	private function convertToColorCode(string $color): string {
		return self::COLOR_CODE_MAP[$color] ?? $color;
	}
}