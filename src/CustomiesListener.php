<?php
declare(strict_types=1);

namespace customiesdevs\customies;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\network\BlockPaletteBuilder;
use customiesdevs\customies\network\ItemRegistryBuilder;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BlockPaletteEntry;
use pocketmine\network\mcpe\protocol\types\Experiments;
use function array_merge;
use function count;

final class CustomiesListener implements Listener {

	/** @var BlockPaletteEntry[] */
	private array $cachedBlockPalette = [];
	private int $cachedPaletteSize = -1;
	/** @var list<BlockPaletteEntry> */
	private array $cachedSourcePalette = [];
	private Experiments $experiments;
	/** @var list<\pocketmine\network\mcpe\protocol\types\ItemTypeEntry> */
	private array $cachedItemSource = [];
	/** @var list<\pocketmine\network\mcpe\protocol\types\ItemTypeEntry> */
	private array $cachedCustomItems = [];
	/** @var list<\pocketmine\network\mcpe\protocol\types\ItemTypeEntry> */
	private array $cachedItems = [];

	public function __construct() {
		$this->experiments = new Experiments([
			// "data_driven_items" is required for custom blocks to render in-game.
			// With this disabled, custom blocks will appear as the UPDATE texture block.
			"data_driven_items" => true,
			"upcoming_creator_features" => true
		], true);
	}

	/** @priority HIGHEST */
	public function onDataPacketSend(DataPacketSendEvent $event): void {
		$packets = $event->getPackets();
		$itemsChanged = false;
		foreach($packets as $index => $packet){
			if($packet instanceof StartGamePacket){
				$customPalettes = CustomiesBlockFactory::getInstance()->getBlockPaletteEntries();
				if($this->cachedPaletteSize !== count($customPalettes) || $this->cachedSourcePalette !== $packet->blockPalette){
					// Wait for the data to be needed before it is actually cached. Allows for all blocks and items to be
					// registered before they are cached for the rest of the runtime.
					// 1.20.60 added a new "block_id" field which depends on the order of the block palette entries. Every time we
					// insert a new block, we need to re-sort the block palette entries to keep in sync with the client.
					$this->cachedSourcePalette = $packet->blockPalette;
					$this->cachedBlockPalette = BlockPaletteBuilder::build($packet->blockPalette, $customPalettes);
					$this->cachedPaletteSize = count($customPalettes);
				}
				$packet->levelSettings->experiments = new Experiments(array_merge($packet->levelSettings->experiments->getExperiments(), $this->experiments->getExperiments()), true);
				$packet->blockPalette = $this->cachedBlockPalette;
			}elseif($packet instanceof ItemRegistryPacket){
				$source = $packet->getEntries();
				$custom = CustomiesItemFactory::getInstance()->getItemTableEntries();
				if($source !== $this->cachedItemSource || $custom !== $this->cachedCustomItems){
					$this->cachedItems = ItemRegistryBuilder::build($source, $custom);
					$this->cachedItemSource = $source;
					$this->cachedCustomItems = $custom;
				}
				if($source !== $this->cachedItems){
					// Replace the event entry, not a potentially shared/cached packet object.
					$replacement = ItemRegistryPacket::create($this->cachedItems);
					$replacement->senderSubId = $packet->senderSubId;
					$replacement->recipientSubId = $packet->recipientSubId;
					$packets[$index] = $replacement;
					$itemsChanged = true;
				}
			}elseif($packet instanceof ResourcePackStackPacket) {
				$packet->experiments = new Experiments(array_merge($packet->experiments->getExperiments(), $this->experiments->getExperiments()), true);
			}
		}
		if($itemsChanged){ $event->setPackets($packets); }
	}
}
