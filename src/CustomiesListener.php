<?php
declare(strict_types=1);

namespace customiesdevs\customies;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\network\BedrockVersion;
use customiesdevs\customies\network\BlockPaletteBuilder;
use customiesdevs\customies\network\ItemRegistryBuilder;
use customiesdevs\customies\network\ProtocolAdapter;
use customiesdevs\customies\network\ProtocolRegistry;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\BiomeDefinitionListPacket;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use WeakMap;
use function array_key_first;
use function array_merge;
use function count;
use function property_exists;

/**
 * Adds Customies definitions to the join packets of every client.
 *
 * Packet events target sessions of one protocol (multi-protocol hosts broadcast per protocol), so each event is
 * adapted for that protocol's Minecraft version and cached per protocol.
 */
final class CustomiesListener implements Listener {

	/** @var array<int, array{source: list<BlockPaletteEntry>, size: int, palette: list<BlockPaletteEntry>}> */
	private array $paletteCache = [];
	/** @var array<int, array{source: list<ItemTypeEntry>, custom: list<ItemTypeEntry>, items: list<ItemTypeEntry>}> */
	private array $itemCache = [];
	private Experiments $experiments;
	/** @var WeakMap<NetworkSession, true> sessions that received the legacy item component packet */
	private WeakMap $legacyComponentsSent;

	public function __construct() {
		$this->experiments = new Experiments([
			// "data_driven_items" is required for custom blocks to render in-game.
			// With this disabled, custom blocks will appear as the UPDATE texture block.
			"data_driven_items" => true,
			"upcoming_creator_features" => true
		], true);
		$this->legacyComponentsSent = new WeakMap();
	}

	/** @priority HIGHEST */
	public function onDataPacketSend(DataPacketSendEvent $event): void {
		$targets = $event->getTargets();
		$protocol = $targets === [] ? ProtocolInfo::CURRENT_PROTOCOL : ProtocolRegistry::getSessionProtocol($targets[array_key_first($targets)]);
		$version = self::versionOf($protocol);
		$legacyItems = !BedrockVersion::supports('item_registry_packet', $version);
		$packets = [];
		$changed = false;
		foreach($event->getPackets() as $packet){
			if($packet instanceof StartGamePacket){
				$packet->blockPalette = $this->palette($protocol, $version, $packet->blockPalette);
				$packet->levelSettings->experiments = new Experiments(array_merge($packet->levelSettings->experiments->getExperiments(), $this->experiments->getExperiments()), true);
				if($legacyItems && property_exists($packet, 'itemTable')){
					// Clients before 1.21.60 read the item table from StartGame.
					$items = $this->items($event, $protocol, $packet->itemTable);
					if($items === null){ return; }
					$packet->itemTable = $items;
				}
			}elseif($packet instanceof ItemRegistryPacket && !$legacyItems){
				$items = $this->items($event, $protocol, $packet->getEntries());
				if($items === null){ return; }
				if($items !== $packet->getEntries()){
					// Replace the event entry, not a potentially shared/cached packet object.
					$replacement = ItemRegistryPacket::create($items);
					$replacement->senderSubId = $packet->senderSubId;
					$replacement->recipientSubId = $packet->recipientSubId;
					$packets[] = $replacement;
					$changed = true;
					continue;
				}
			}elseif($packet instanceof ResourcePackStackPacket){
				$packet->experiments = new Experiments(array_merge($packet->experiments->getExperiments(), $this->experiments->getExperiments()), true);
			}
			$packets[] = $packet;
			if($packet instanceof BiomeDefinitionListPacket && $legacyItems && ($components = $this->legacyComponents($targets)) !== null){
				// Clients before 1.21.60 take item components from the legacy component packet, sent after the
				// biome definitions. The host's codec writes that format for these protocols.
				$packets[] = $components;
				$changed = true;
			}
		}
		if($changed){ $event->setPackets($packets); }
	}

	/**
	 * @param list<BlockPaletteEntry> $source
	 * @return list<BlockPaletteEntry>
	 */
	private function palette(int $protocol, string $version, array $source): array {
		$custom = CustomiesBlockFactory::getInstance()->getBlockPaletteEntries();
		$cache = $this->paletteCache[$protocol] ?? null;
		if($cache === null || $cache['size'] !== count($custom) || $cache['source'] !== $source){
			// Built when first needed, so every block is registered first. 1.20.60 added a "block_id" field that
			// depends on palette order, so inserting a block re-sorts the whole list.
			$cache = $this->paletteCache[$protocol] = [
				'source' => $source,
				'size' => count($custom),
				'palette' => BlockPaletteBuilder::build($source, ProtocolAdapter::adaptBlockPalette($custom, $version))
			];
		}
		return $cache['palette'];
	}

	/**
	 * Merges the custom item table into a client's item list. Returns null, after cancelling the event and
	 * disconnecting its targets, when another plugin's snapshot conflicts with Customies.
	 * @param list<ItemTypeEntry> $source
	 * @return list<ItemTypeEntry>|null
	 */
	private function items(DataPacketSendEvent $event, int $protocol, array $source): ?array {
		$custom = CustomiesItemFactory::getInstance()->getItemTableEntries();
		$cache = $this->itemCache[$protocol] ?? null;
		if($cache === null || $cache['source'] !== $source || $cache['custom'] !== $custom){
			try{
				$cache = $this->itemCache[$protocol] = ['source' => $source, 'custom' => $custom, 'items' => ItemRegistryBuilder::build($source, $custom)];
			}catch(\UnexpectedValueException $e){
				// A malformed plugin snapshot must not crash the server's event loop.
				$event->cancel();
				foreach($event->getTargets() as $target){
					$target->disconnect($e->getMessage(), 'Custom item registry conflict. Please contact the server administrator.');
				}
				return null;
			}
		}
		return $cache['items'];
	}

	/** @param NetworkSession[] $targets */
	private function legacyComponents(array $targets): ?ItemRegistryPacket {
		$entries = ProtocolAdapter::legacyItemComponentEntries(CustomiesItemFactory::getInstance()->getItemTableEntries());
		$pending = false;
		foreach($targets as $target){
			if(!isset($this->legacyComponentsSent[$target])){
				$this->legacyComponentsSent[$target] = true;
				$pending = true;
			}
		}
		return $pending && $entries !== [] ? ItemRegistryPacket::create($entries) : null;
	}

	private static function versionOf(int $protocol): string {
		try{
			return ProtocolRegistry::getMinecraftVersion($protocol);
		}catch(\InvalidArgumentException){
			return BedrockVersion::host();
		}
	}
}
