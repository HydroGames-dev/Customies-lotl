<?php
declare(strict_types=1);

namespace customiesdevs\customies\item\component;

interface ItemComponent {

	/**
	 * The component identifier, e.g. "minecraft:display_name"
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Component payload for client NBT. Explicit Tag values preserve wire types.
	 * @return mixed
	 */
	public function getValue(): mixed;

	/**
	 * Returns property mappings if this component maps to item_properties.
	 * @return array<string, mixed>|null [propertyName => propertyValue] or null if not a property
	 */
	public function getPropertyMapping(): ?array;
}
