<?php
declare(strict_types=1);

namespace customiesdevs\customies\network;

use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use function count;
use function in_array;
use function sprintf;

/** Checks the host contracts Customies actually mutates; does not negotiate protocols. */
final class RuntimeCompatibility {

	/** API/codec regression targets; Minecraft rendering is a separate check. */
	public static function getProfile(int $protocol): string {
		$profile = match($protocol){
			2225 => '26.60 (stable API target)',
			default => 'unverified host protocol'
		};
		if(ProtocolRegistry::isMultiProtocol() && $protocol === ProtocolInfo::CURRENT_PROTOCOL){
			$accepted = ProtocolRegistry::getAcceptedProtocols();
			$profile .= sprintf('; multi-version host accepting %d protocols from %s', count($accepted), ProtocolRegistry::getMinecraftVersion($accepted[0]));
		}
		return $profile;
	}

	/** @return list<string> */
	public static function getContractErrors(): array {
		$errors = [];
		$properties = [
			\pocketmine\network\mcpe\convert\BlockStateDictionary::class => ['states', 'stateDataToStateIdLookup', 'idMetaToStateIdLookupCache'],
			\pocketmine\network\mcpe\convert\BlockTranslator::class => ['fallbackStateId', 'networkIdCache'],
			\pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary::class => ['intToStringIdMap', 'stringToIntMap', 'itemTypes'],
			\pocketmine\data\bedrock\item\BlockItemIdMap::class => ['itemToBlockId', 'blockToItemId'],
			\pocketmine\network\mcpe\protocol\StartGamePacket::class => ['blockPalette', 'levelSettings'],
			\pocketmine\network\mcpe\protocol\ResourcePackStackPacket::class => ['experiments']
		];
		foreach($properties as $class => $names){
			if(!class_exists($class)){ $errors[] = "Missing host class $class"; continue; }
			foreach($names as $name){
				if(!(new \ReflectionClass($class))->hasProperty($name)){ $errors[] = "Missing host field $class::\$$name"; }
			}
		}
		$constructor = class_exists(ItemTypeEntry::class) ? (new \ReflectionClass(ItemTypeEntry::class))->getConstructor() : null;
		if($constructor === null || $constructor->getNumberOfRequiredParameters() !== 5 || $constructor->getNumberOfParameters() !== 5){
			$errors[] = 'ItemTypeEntry requires an unsupported registry adapter';
		}
		// Single-protocol hosts take (name, states, meta); multi-protocol hosts add the original state of upgraded entries.
		$entry = new \ReflectionMethod(\pocketmine\network\mcpe\convert\BlockStateDictionaryEntry::class, '__construct');
		if(!in_array($entry->getNumberOfParameters(), [3, 4], true)){
			$errors[] = 'BlockStateDictionaryEntry has an unsupported constructor';
		}
		return $errors;
	}

	public static function assertCompatible(): void {
		$errors = self::getContractErrors();
		if($errors !== []){
			throw new \RuntimeException('Customies cannot use this host registry API: ' . implode('; ', $errors) . '. Update Axolotl and Customies together.');
		}
	}

	/** @return array<string, mixed> */
	public static function describe(): array {
		$packages = [];
		if(class_exists(\Composer\InstalledVersions::class)){
			foreach(['axolotl-pm/bedrock-protocol', 'axolotl-pm/bedrock-data', 'axolotl-pm/bedrock-block-upgrade-schema', 'axolotl-pm/bedrock-item-upgrade-schema'] as $package){
				if(\Composer\InstalledVersions::isInstalled($package)){
					$packages[$package] = ['version' => \Composer\InstalledVersions::getPrettyVersion($package), 'reference' => \Composer\InstalledVersions::getReference($package)];
				}
			}
		}
		return [
			'php' => PHP_VERSION,
			'protocol' => ProtocolInfo::CURRENT_PROTOCOL,
			'minecraft' => ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			'profile' => self::getProfile(ProtocolInfo::CURRENT_PROTOCOL),
			'bedrock_features' => BedrockVersion::describe(),
			'multi_protocol' => ProtocolRegistry::isMultiProtocol(),
			'accepted_protocols' => ProtocolRegistry::getVersionTable(),
			'contract_errors' => self::getContractErrors(),
			'packages' => $packages,
			'client_validation' => 'In-game rendering and prediction must be tested separately; Customies uses the host protocol.'
		];
	}
}
