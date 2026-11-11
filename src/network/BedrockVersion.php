<?php
declare(strict_types=1);

namespace customiesdevs\customies\network;

use InvalidArgumentException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use function implode;
use function preg_match;
use function version_compare;

/**
 * Version gates for client behavior that changes between Bedrock releases.
 *
 * Customies uses the host's codec and never negotiates protocols, so the host's network version
 * (`ProtocolInfo::MINECRAFT_VERSION_NETWORK`) is the version every connected client runs. A gated
 * behavior switches on when Axolotl moves to that Minecraft version; nothing changes before then.
 *
 * Add a gate only for a documented client change. Preview-derived entries are re-checked against the
 * released schemas before the host ships that version.
 */
final class BedrockVersion {

	/**
	 * Feature key => first Bedrock network version that accepts it.
	 * `collision_box_32`: Mojang 1.26.70 collision schema raises maxItems from 16 to 32 (preview-derived).
	 */
	public const FEATURES = [
		'collision_box_32' => '1.26.70'
	];

	public const COLLISION_BOX_LIMIT = 16;
	public const COLLISION_BOX_LIMIT_1_26_70 = 32;

	private function __construct() {}

	/** The Bedrock network version of the running host, e.g. "1.26.60". */
	public static function host(): string {
		return ProtocolInfo::MINECRAFT_VERSION_NETWORK;
	}

	/**
	 * Whether $version (default: the host) is at least $minimum. Build numbers such as the ".21" in
	 * preview "1.26.70.21" are ignored: gates apply to a whole minor release.
	 */
	public static function isAtLeast(string $minimum, ?string $version = null): bool {
		return version_compare(self::normalize($version ?? self::host()), self::normalize($minimum), '>=');
	}

	public static function supports(string $feature, ?string $version = null): bool {
		$minimum = self::FEATURES[$feature] ?? throw new InvalidArgumentException("Unknown Bedrock feature gate $feature");
		return self::isAtLeast($minimum, $version);
	}

	/** Maximum number of boxes one collision_box component may carry for $version (default: the host). */
	public static function getCollisionBoxLimit(?string $version = null): int {
		return self::supports('collision_box_32', $version) ? self::COLLISION_BOX_LIMIT_1_26_70 : self::COLLISION_BOX_LIMIT;
	}

	/** @return array<string, array{minimum: string, enabled: bool}> */
	public static function describe(?string $version = null): array {
		$features = [];
		foreach(self::FEATURES as $feature => $minimum){
			$features[$feature] = ['minimum' => $minimum, 'enabled' => self::isAtLeast($minimum, $version)];
		}
		return $features;
	}

	/**
	 * Accepts network versions ("1.26.60", "1.26.70.21") and two-part display versions ("26.60",
	 * "v26.60", which is network version 1.26.60). Returns major.minor.patch.
	 */
	public static function normalize(string $version): string {
		if(preg_match('/^1\.(\d+)(?:\.(\d+))?(?:\.\d+)?$/', $version, $matches) === 1){
			return implode('.', ['1', $matches[1], $matches[2] ?? '0']);
		}
		if(preg_match('/^v?(\d+)\.(\d+)$/', $version, $matches) === 1){
			return implode('.', ['1', $matches[1], $matches[2]]);
		}
		throw new InvalidArgumentException("Invalid Bedrock version \"$version\"");
	}
}
