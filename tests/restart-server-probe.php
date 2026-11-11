<?php
declare(strict_types=1);
namespace customiesdevs\restarttest;

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\traits\PlacementDirectionTrait;
use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\block\{BlockIdentifier, BlockTypeIds, BlockTypeInfo, BlockBreakInfo, VanillaBlocks};
use pocketmine\item\{Item, ItemIdentifier, ItemTypeIds};
use pocketmine\nbt\tag\{CompoundTag, ListTag};

final class ProbeBlock extends TraitBlock {
	public function __construct(int $id){
		$this->addTrait(PlacementDirectionTrait::cardinal());
		parent::__construct(new BlockIdentifier($id), 'Restart probe', new BlockTypeInfo(BlockBreakInfo::instant()));
	}
}

final class RestartProbe extends \pocketmine\plugin\PluginBase {
	private int $cycle;
	private \pocketmine\network\mcpe\protocol\ItemRegistryPacket $earlyRegistry;
	public function onLoad(): void {
		$file = $this->getServer()->getDataPath() . '/restart-cycle.json';
		$this->cycle = is_file($file) ? json_decode(file_get_contents($file), flags: JSON_THROW_ON_ERROR) : 0;
		$this->earlyRegistry = \pocketmine\network\mcpe\protocol\ItemRegistryPacket::create(\pocketmine\network\mcpe\convert\TypeConverter::getInstance()->getItemTypeDictionary()->getEntries());
		for($i = 0; $i < $this->cycle * 3; ++$i){ ItemTypeIds::newId(); BlockTypeIds::newId(); }
		$order = ['restart:wand', 'restart:token', 'restart:block'];
		if($this->cycle % 2){ $order = array_reverse($order); }
		foreach($order as $name){
			if($name === 'restart:block'){
				$id = BlockTypeIds::newId();
				CustomiesBlockFactory::getInstance()->registerBlock(static fn() => new ProbeBlock($id), $name);
			}else{
				$id = ItemTypeIds::newId();
				CustomiesItemFactory::getInstance()->registerItem(static fn() => new Item(new ItemIdentifier($id), $name), $name);
			}
		}
	}
	private function verify(bool $ok, string $message): void { if(!$ok){ throw new \RuntimeException($message); } }
	protected function onEnable(): void {
		// Load immediately: content must exist before inventories are read, not a later tick.
		$world = $this->getServer()->getWorldManager()->getDefaultWorld();
		$this->verify($world !== null, 'Default test world must load');
		$provider = new \pocketmine\player\DatFilePlayerDataProvider($this->getServer()->getDataPath() . '/players');
		if($this->cycle === 0){
			if($world->loadChunk(0, 0) === null){ $world->setChunk(0, 0, new \pocketmine\world\format\Chunk([], true)); }
			$block = CustomiesBlockFactory::getInstance()->get('restart:block');
			$block->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->setCurrentValue('west');
			$world->setBlockAt(1, 65, 1, $block);
			$world->setBlockAt(2, 65, 1, VanillaBlocks::CHEST());
			$contents = [0 => CustomiesItemFactory::getInstance()->get('restart:wand', 3)->setCustomName('Persistent wand')->setLore(['Restart test']), 4 => $block->asItem()->setCount(12), 8 => CustomiesItemFactory::getInstance()->get('restart:token', 32)];
			$contents[0]->getNamedTag()->setInt('restart:charges', 17);
			$chest = $world->getTileAt(2, 65, 1);
			$this->verify($chest instanceof \pocketmine\block\tile\Chest, 'Real chest tile must exist');
			$chest->getInventory()->setContents($contents);
			$player = CompoundTag::create()->setTag('Inventory', new ListTag(array_map(static fn(int $slot) => $contents[$slot]->nbtSerialize($slot), array_keys($contents))));
		}else{
			$player = $provider->loadData('customies_restart_test');
			$this->verify($player !== null, 'Player data must survive server restart');
		}
		$items = [];
		foreach($player->getListTag('Inventory', CompoundTag::class) as $tag){ $items[$tag->getByte('Slot')] = Item::nbtDeserialize($tag); }
		$this->verify(array_keys($items) === [0, 4, 8], 'Player saved slots survive');
		$this->verify($items[0]->getCount() === 3 && $items[4]->getCount() === 12 && $items[8]->getCount() === 32, 'Player stack counts survive');
		$this->verify($items[0]->getLore() === ['Restart test'] && $items[0]->getNamedTag()->getInt('restart:charges') === 17, 'Player custom data survives');
		$chest = $world->getTileAt(2, 65, 1);
		$this->verify($chest instanceof \pocketmine\block\tile\Chest, 'Chest tile reloads from world storage');
		foreach($items as $slot => $item){
			$this->verify($chest->getInventory()->getItem($slot)->equalsExact($item), 'Chest and player items agree after restart');
			$this->verify(\pocketmine\item\StringToItemParser::getInstance()->parse($item->nbtSerialize()->getString('Name')) !== null, 'Saved item remains available to the server parser');
		}
		$block = $world->getBlockAt(1, 65, 1);
		$this->verify($block instanceof ProbeBlock && $block->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->getCurrentValue() === 'west', 'Placed custom state reloads from native world storage');
		$player->setTag('Inventory', new ListTag(array_map(static fn(int $slot) => $items[$slot]->nbtSerialize($slot), array_keys($items))));
		$provider->saveData('customies_restart_test', $player);
		$world->save(true);
		$this->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(): void {
			$event = new \pocketmine\event\server\DataPacketSendEvent([], [$this->earlyRegistry]);
			$event->call();
			$table = [];
			foreach($event->getPackets()[0]->getEntries() as $entry){ $table[$entry->getStringId()] = $entry->getNumericId(); }
			foreach(CustomiesItemFactory::getInstance()->getItemTableEntries() as $entry){ $this->verify(($table[$entry->getStringId()] ?? null) === $entry->getNumericId(), 'Real packet event repairs early registry'); }
			$report = \customiesdevs\customies\util\Diagnostics::report();
			$this->verify($report['registry_frozen'] && $report['custom_item_entries'] === 143 && $report['custom_blocks'] === 75, 'Full Showcase and probe definitions exist');
			$fingerprintFile = $this->getServer()->getDataPath() . '/restart-fingerprint.txt';
			if($this->cycle > 0){ $this->verify(file_get_contents($fingerprintFile) === $report['identifier_fingerprint'], 'Identifier fingerprint ignores allocation/order changes'); }
			file_put_contents($fingerprintFile, $report['identifier_fingerprint']);
			file_put_contents($this->getServer()->getDataPath() . '/restart-cycle.json', json_encode($this->cycle + 1, JSON_THROW_ON_ERROR));
			$this->getLogger()->info('RESTART PASS ' . $this->cycle . ': native chest/world, saved player NBT, registry and all Showcase definitions');
			$this->getServer()->shutdown();
		}), 5);
	}
}
