<?php
declare(strict_types=1);

namespace customiesdevs\customies\util;

use customiesdevs\customies\block\BlockPalette;
use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\block\states\BlockStates;
use customiesdevs\customies\entity\CustomiesEntityFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\network\RuntimeCompatibility;

final class Diagnostics {

	/** Read-only support information; no player data, paths, tokens or network requests. @return array<string, mixed> */
	public static function report(): array {
		$blocks = CustomiesBlockFactory::getInstance()->getBlockPaletteEntries();
		return RuntimeCompatibility::describe() + [
			'host' => \pocketmine\VersionInfo::NAME . ' ' . \pocketmine\VersionInfo::BASE_VERSION,
			'registry_frozen' => RegistrationGuard::isFrozen(),
			'custom_blocks' => count($blocks),
			'custom_block_states' => $blocks === [] ? 0 : count(BlockPalette::getInstance()->getCustomStates()),
			'custom_item_entries' => count(CustomiesItemFactory::getInstance()->getItemTableEntries()),
			'custom_entities' => count(CustomiesEntityFactory::getInstance()->getRegisteredIdentifiers())
		];
	}

	/** @return array<string, mixed> */
	public static function inspect(string $identifier): array {
		foreach(CustomiesBlockFactory::getInstance()->getBlockPaletteEntries() as $entry){
			if($entry->getName() !== $identifier){ continue; }
			$block = CustomiesBlockFactory::getInstance()->get($identifier);
			$states = [];
			if($block instanceof BlockStates){
				foreach($block->getStates() as $state){ $states[$state->getName()] = $state->getValues(); }
			}
			return ['kind' => 'block', 'identifier' => $identifier, 'class' => get_class($block), 'states' => $states, 'registry_nbt_bytes' => strlen($entry->getStates()->getEncodedNbt())];
		}
		foreach(CustomiesItemFactory::getInstance()->getItemTableEntries() as $entry){
			if($entry->getStringId() === $identifier){
				return ['kind' => 'item', 'identifier' => $identifier, 'network_id' => $entry->getNumericId(), 'component_based' => $entry->isComponentBased(), 'registry_version' => $entry->getVersion(), 'registry_nbt_bytes' => strlen($entry->getComponentNbt()->getEncodedNbt())];
			}
		}
		throw new \InvalidArgumentException("No Customies block or item registered as '$identifier'.");
	}
}
