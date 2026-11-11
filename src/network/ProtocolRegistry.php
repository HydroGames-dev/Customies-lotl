<?php
declare(strict_types=1);

namespace customiesdevs\customies\network;

use Closure;
use InvalidArgumentException;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use ReflectionClass;
use function array_unique;
use function array_values;
use function constant;
use function defined;
use function is_int;
use function method_exists;
use function preg_match;
use function sort;

/**
 * The client protocols the host accepts, and the per-protocol registries that serve them.
 *
 * Single-protocol hosts (Axolotl, PocketMine-MP) have one TypeConverter for ProtocolInfo::CURRENT_PROTOCOL.
 * Multi-protocol hosts (NetherGames PocketMine-MP) list every accepted protocol in ProtocolInfo::ACCEPTED_PROTOCOL
 * and create one TypeConverter per protocol on demand, each with its own block state and item type dictionary.
 * Customies registers its content in every one of them.
 */
final class ProtocolRegistry {

	/** @var array<int, string>|null protocol => Minecraft network version */
	private static ?array $versions = null;

	private function __construct() {}

	public static function isMultiProtocol(): bool {
		return defined(ProtocolInfo::class . '::ACCEPTED_PROTOCOL')
			&& method_exists(TypeConverter::class, 'addCreationListener')
			&& method_exists(TypeConverter::class, 'getAll');
	}

	/** @return list<int> Accepted client protocols, oldest first. */
	public static function getAcceptedProtocols(): array {
		/** @var list<int> $protocols */
		$protocols = self::isMultiProtocol() ? array_values(array_unique(constant(ProtocolInfo::class . '::ACCEPTED_PROTOCOL'))) : [ProtocolInfo::CURRENT_PROTOCOL];
		sort($protocols);
		return $protocols;
	}

	/**
	 * The Minecraft network version of a protocol, e.g. 2225 => "1.26.60". Multi-protocol hosts name each protocol
	 * as a ProtocolInfo::PROTOCOL_1_<minor>_<patch> constant; single-protocol hosts only know the current one.
	 */
	public static function getMinecraftVersion(int $protocol): string {
		return self::versionTable()[$protocol] ?? throw new InvalidArgumentException("Protocol $protocol is not known to this host");
	}

	/** @return array<int, string> */
	public static function getVersionTable(): array {
		$table = [];
		foreach(self::getAcceptedProtocols() as $protocol){
			$table[$protocol] = self::getMinecraftVersion($protocol);
		}
		return $table;
	}

	/** The protocol a session encodes packets with. */
	public static function getSessionProtocol(NetworkSession $session): int {
		return method_exists($session, 'getProtocolId') ? $session->getProtocolId() : ProtocolInfo::CURRENT_PROTOCOL;
	}

	public static function getTypeConverter(int $protocol): TypeConverter {
		return self::isMultiProtocol() ? TypeConverter::getInstance($protocol) : TypeConverter::getInstance();
	}

	/**
	 * Calls $apply for every TypeConverter that exists now and, on multi-protocol hosts, for every one created later
	 * in this thread. Converters of protocols that share data files share their translators and dictionaries, so
	 * callers must make their changes idempotent per dictionary.
	 * @param Closure(TypeConverter): void $apply
	 */
	public static function onEveryTypeConverter(Closure $apply): void {
		if(!self::isMultiProtocol()){
			$apply(TypeConverter::getInstance());
			return;
		}
		TypeConverter::getInstance(); // the current protocol's converter always exists
		foreach(TypeConverter::getAll() as $converter){
			$apply($converter);
		}
		TypeConverter::addCreationListener($apply);
	}

	/** @return array<int, string> */
	private static function versionTable(): array {
		if(self::$versions === null){
			$versions = [];
			foreach((new ReflectionClass(ProtocolInfo::class))->getConstants() as $name => $value){
				if(is_int($value) && preg_match('/^PROTOCOL_1_(\d+)_(\d+)$/', $name, $matches) === 1){
					$versions[$value] ??= '1.' . $matches[1] . '.' . $matches[2];
				}
			}
			$versions[ProtocolInfo::CURRENT_PROTOCOL] = ProtocolInfo::MINECRAFT_VERSION_NETWORK;
			self::$versions = $versions;
		}
		return self::$versions;
	}
}
