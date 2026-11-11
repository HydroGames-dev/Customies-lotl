<?php
declare(strict_types=1);

/*
 * Compares Mojang's newest block/item component schemas with the components Customies wraps.
 *
 *   php tools/component-coverage.php /path/to/bedrock-samples [git-ref] [--beta] [--strict]
 *
 * Reads through git, so sparse or partial clones work (`git fetch origin preview` for previews).
 * "new" lists schema components Customies neither wraps nor classifies below: review those first
 * after a Bedrock update. --strict exits 1 when any exist.
 */

$arguments = array_values(array_filter(array_slice($argv, 1), static fn(string $a): bool => !str_starts_with($a, '--')));
$flags = array_filter(array_slice($argv, 1), static fn(string $a): bool => str_starts_with($a, '--'));
$samples = $arguments[0] ?? null;
$ref = $arguments[1] ?? 'HEAD';
if($samples === null || !is_dir($samples . '/.git')){
	fwrite(STDERR, "Usage: php tools/component-coverage.php /path/to/bedrock-samples [git-ref] [--beta] [--strict]\n");
	exit(2);
}

/**
 * Schema components Customies intentionally does not send to clients, with the PHP equivalent.
 * They describe server behavior that Axolotl implements (or lacks) in PHP. Mojang's 26.60 host data
 * confirms only that `loot` is stripped from networked vanilla definitions; the others do not occur
 * in vanilla data-driven blocks.
 */
const NOT_CLIENT_COMPONENTS = [
	'block' => [
		'minecraft:loot' => 'server drops: override getDropsForCompatibleTool()',
		'minecraft:tick' => 'server ticking: use onScheduledUpdate()/onRandomTick()',
		'minecraft:redstone_consumer' => 'server redstone: Axolotl computes power in PHP',
		'minecraft:redstone_producer' => 'wire connections are server block states on Axolotl 5.51: implement pocketmine\block\utils\RedstoneSignalEmitter',
		'minecraft:vibration_properties' => '26.60 sculk vibrations: Axolotl has no vibration system',
		'minecraft:neighbor_change' => '26.60 Script API event filter: override onNearbyBlockChange()',
		'minecraft:enchantment_power_transmitter' => '26.60 enchanting power: needs host support (Axolotl only counts bookshelves through air)',
		'minecraft:jukebox_playlist_source' => '1.26.70 preview, Upcoming Creator Features: not accepted by 26.60 clients'
	],
	'item' => [
		'minecraft:block_placer' => 'Customies registers block items itself',
		'minecraft:entity_placer' => 'spawn entities from PHP item use'
	]
];

function git(string $samples, string ...$args): string {
	$command = 'git -C ' . escapeshellarg($samples) . ' ' . implode(' ', array_map('escapeshellarg', $args));
	$output = shell_exec($command . ' 2>&1');
	if(!is_string($output)){ throw new RuntimeException("git failed: $command"); }
	return $output;
}

/** @return array{version: string, path: string}|null */
function newestSchema(array $files, string $pattern, bool $beta): ?array {
	$best = null;
	foreach($files as $file){
		if(preg_match($pattern, $file, $m) !== 1){ continue; }
		$version = $m[1];
		if($version === 'beta' && !$beta){ continue; }
		$rank = $version === 'beta' ? '999999' : $version;
		if($best === null || version_compare($rank, $best['rank'], '>')){ $best = ['version' => $version, 'path' => $file, 'rank' => $rank]; }
	}
	return $best === null ? null : ['version' => $best['version'], 'path' => $best['path']];
}

/** @return array<string, bool> component name => deprecated */
function schemaComponents(string $json): array {
	$schema = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
	$properties = $schema['properties']['components']['properties'] ?? $schema['properties'] ?? [];
	$components = [];
	foreach($properties as $name => $definition){
		if(str_starts_with((string) $name, 'minecraft:')){ $components[$name] = !empty($definition['deprecated']); }
	}
	ksort($components);
	return $components;
}

/** @return array<string, string> component name => class */
function customiesComponents(string $kind): array {
	$components = [];
	foreach(glob(dirname(__DIR__) . "/src/$kind/component/*Component.php") as $path){
		if(preg_match('/function\s+getName\s*\(\s*\)\s*:\s*string\s*\{\s*return\s+[\'"](minecraft:[a-z0-9_.]+)[\'"]/', (string) file_get_contents($path), $m) === 1){
			$components[$m[1]] = basename($path, '.php');
		}
	}
	return $components;
}

$files = array_filter(array_map('trim', explode("\n", git($samples, 'ls-tree', '-r', '--name-only', $ref, 'metadata/json_schemas/server'))));
$versionFile = json_decode(git($samples, 'show', "$ref:version.json"), true);
$beta = in_array('--beta', $flags, true);
$sources = [
	'block' => newestSchema($files, '#^metadata/json_schemas/server/block(?:_components)?/([0-9.]+|beta)/(?:Block )?Components\.json$#', $beta),
	'item' => newestSchema($files, '#^metadata/json_schemas/server/item/([0-9.]+|beta)/Item Components\.json$#', $beta)
];

echo 'Mojang bedrock-samples ', $ref, ' (', $versionFile['latest']['version'] ?? 'unknown version', ")\n";
$unclassified = 0;
foreach($sources as $kind => $source){
	if($source === null){ echo "\n[$kind] no component schema found\n"; continue; }
	$schema = schemaComponents(git($samples, 'show', $ref . ':' . $source['path']));
	$ours = customiesComponents($kind);
	$groups = ['supported' => [], 'server-side' => [], 'deprecated' => [], 'new' => []];
	foreach($schema as $name => $deprecated){
		if(isset($ours[$name])){ $groups['supported'][] = "$name ({$ours[$name]})"; }
		elseif(isset(NOT_CLIENT_COMPONENTS[$kind][$name])){ $groups['server-side'][] = "$name: " . NOT_CLIENT_COMPONENTS[$kind][$name]; }
		elseif($deprecated){ $groups['deprecated'][] = $name; }
		else{ $groups['new'][] = $name; ++$unclassified; }
	}
	$extra = array_diff(array_keys($ours), array_keys($schema));
	echo "\n[$kind] schema {$source['version']}: ", count($schema), ' components; ', count($groups['supported']), ' wrapped by Customies', "\n";
	foreach($groups as $group => $names){
		if($names === []){ continue; }
		echo "  $group (", count($names), "):\n";
		foreach($names as $name){ echo "    $name\n"; }
	}
	if($extra !== []){ echo '  wrapped but absent from this schema: ', implode(', ', $extra), "\n"; }
}
if($unclassified > 0){ echo "\n$unclassified new component(s): check the host's networked definitions, then wrap or classify them.\n"; }
exit($unclassified > 0 && in_array('--strict', $flags, true) ? 1 : 0);
