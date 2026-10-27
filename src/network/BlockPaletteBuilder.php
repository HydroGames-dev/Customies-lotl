<?php
declare(strict_types=1);

namespace customiesdevs\customies\network;

use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;

/** Uses the same unsigned FNV-1 ordering as the runtime state dictionary. */
final class BlockPaletteBuilder {

	/** @param list<string> $names @return list<string> */
	public static function sortNames(array $names): array {
		$hashes = [];
		foreach($names as $name){ $hashes[$name] = hash('fnv164', $name); }
		usort($names, static fn(string $a, string $b): int => strcmp($hashes[$a], $hashes[$b]) ?: strcmp($a, $b));
		return $names;
	}

	/**
	 * Preserve other plugins' entries, replace matching custom entries and sort once.
	 * @param list<BlockPaletteEntry> $base
	 * @param list<BlockPaletteEntry> $custom
	 * @return list<BlockPaletteEntry>
	 */
	public static function build(array $base, array $custom): array {
		$entries = [];
		foreach([...$base, ...$custom] as $entry){ $entries[$entry->getName()] = $entry; }
		return array_map(static fn(string $name): BlockPaletteEntry => $entries[$name], self::sortNames(array_keys($entries)));
	}
}
