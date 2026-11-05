<?php
declare(strict_types=1);

namespace customiesdevs\customies\network;

use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;

/** Repairs early/cached login snapshots without changing the host's runtime IDs. */
final class ItemRegistryBuilder {

	/**
	 * Customies owns its definitions; retain all unrelated incoming entries.
	 * @param list<ItemTypeEntry> $base
	 * @param list<ItemTypeEntry> $custom
	 * @return list<ItemTypeEntry>
	 */
	public static function build(array $base, array $custom): array {
		$entries = [];
		foreach([...$base, ...$custom] as $entry){
			$entries[$entry->getStringId()] = $entry;
		}
		$ids = [];
		foreach($entries as $name => $entry){
			$id = $entry->getNumericId();
			if(isset($ids[$id])){
				throw new \UnexpectedValueException("Item registry ID $id conflicts between '{$ids[$id]}' and '$name'. Check content plugin registration; the login registry cannot be sent safely.");
			}
			$ids[$id] = $name;
		}
		return array_values($entries);
	}
}
