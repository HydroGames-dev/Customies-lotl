<?php
declare(strict_types=1);

require $argv[1] . '/vendor/autoload.php';
require_once __DIR__ . '/packets.php';
spl_autoload_register(static function(string $class): void {
	$prefix = 'customiesdevs\\customies\\';
	if(str_starts_with($class, $prefix)){ require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
});

use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\traits\PlacementDirectionTrait;
use customiesdevs\customies\CustomiesListener;
use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\block\{BlockIdentifier, BlockTypeIds, BlockTypeInfo, BlockBreakInfo};
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\{Item, ItemIdentifier, ItemTypeIds, StringToItemParser};
use pocketmine\nbt\{BigEndianNbtSerializer, TreeRoot};
use pocketmine\nbt\tag\{CompoundTag, ListTag};
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\types\{CacheableNbt, ItemTypeEntry};
use pocketmine\world\format\io\{GlobalBlockStateHandlers, GlobalItemDataHandlers};

function verify(bool $condition, string $message): void { if(!$condition){ throw new RuntimeException($message); } }

final class RestartBlock extends TraitBlock {
	public function __construct(int $id){
		$this->addTrait(PlacementDirectionTrait::cardinal());
		parent::__construct(new BlockIdentifier($id), 'Restart block', new BlockTypeInfo(BlockBreakInfo::instant()));
	}
}

$directory = $argv[2]; $cycle = (int) $argv[3];
$converter = TypeConverter::getInstance();
$earlyPacket = ItemRegistryPacket::create($converter->getItemTypeDictionary()->getEntries());
$earlyPacket->senderSubId = 1; $earlyPacket->recipientSubId = 2;
// Change both allocation and registration order between processes deliberately.
for($i = 0; $i < $cycle * 3; ++$i){ ItemTypeIds::newId(); BlockTypeIds::newId(); }
$registrations = ['restart:wand', 'restart:token', 'restart:block'];
if($cycle % 2 === 1){ $registrations = array_reverse($registrations); }
$factory = CustomiesItemFactory::getInstance();
$ids = [];
foreach($registrations as $name){
	if($name === 'restart:block'){
		$id = BlockTypeIds::newId();
		CustomiesBlockFactory::getInstance()->registerBlock(static fn() => new RestartBlock($id), $name);
	}else{
		$id = ItemTypeIds::newId();
		$factory->registerItem(static fn() => new Item(new ItemIdentifier($id), $name), $name);
	}
	$ids[$name] = $id;
}
ksort($ids);
if($cycle > 0){ verify(json_decode(file_get_contents($directory . '/ids.json'), true, flags: JSON_THROW_ON_ERROR) !== $ids, 'Fixture must change runtime IDs between restarts'); }
file_put_contents($directory . '/ids.json', json_encode($ids, JSON_THROW_ON_ERROR));

$nbt = new BigEndianNbtSerializer();
if($cycle === 0){
	$wand = $factory->get('restart:wand', 3)->setCustomName('Saved wand')->setLore(['First line', 'Second line']);
	$wand->getNamedTag()->setInt('restart:charges', 17)->setTag('restart:data', CompoundTag::create()->setString('owner', 'persistent-test'));
	$block = CustomiesBlockFactory::getInstance()->get('restart:block');
	$block->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->setCurrentValue('west');
	$placedState = GlobalBlockStateHandlers::getSerializer()->serialize($block->getStateId());
	$contents = [0 => $wand, 4 => $block->asItem()->setCount(12), 8 => $factory->get('restart:token', 32)];
}else{
	$saved = $nbt->read(file_get_contents($directory . '/inventory.nbt'))->mustGetCompoundTag();
	$contents = [];
	foreach($saved->getListTag('Inventory', CompoundTag::class) as $tag){ $contents[$tag->getByte('Slot')] = Item::nbtDeserialize($tag); }
	$placedState = \pocketmine\data\bedrock\block\BlockStateData::fromNbt($saved->getCompoundTag('PlacedBlock'));
}
verify(array_keys($contents) === [0, 4, 8], 'Inventory slots must survive');
verify($contents[0]->getCount() === 3 && $contents[4]->getCount() === 12 && $contents[8]->getCount() === 32, 'Stack counts survive');
verify($contents[0]->getCustomName() === 'Saved wand' && $contents[0]->getLore() === ['First line', 'Second line'], 'Name and lore survive');
verify($contents[0]->getNamedTag()->getInt('restart:charges') === 17 && $contents[0]->getNamedTag()->getCompoundTag('restart:data')->getString('owner') === 'persistent-test', 'Typed nested plugin NBT survives');
verify($contents[4]->getBlock()->getTypeId() === $ids['restart:block'], 'Block item resolves to the current block type');
$restoredBlock = \pocketmine\block\RuntimeBlockStateRegistry::getInstance()->fromStateId(GlobalBlockStateHandlers::getDeserializer()->deserialize($placedState));
verify($restoredBlock->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->getCurrentValue() === 'west', 'Placed block orientation survives state NBT');
foreach([0 => 'restart:wand', 4 => 'restart:block', 8 => 'restart:token'] as $slot => $name){
	verify(GlobalItemDataHandlers::getSerializer()->serializeType($contents[$slot])->getName() === $name, 'Saved identifier must remain canonical');
	$network = $converter->coreItemStackToNet($contents[$slot]);
	$restored = $converter->netItemStackToCore($network);
	verify($restored->equalsExact($contents[$slot]), 'Network item conversion preserves the saved stack');
}
$wrappers = [];
for($slot = 0; $slot < 9; ++$slot){
	$stack = $converter->coreItemStackToNet($contents[$slot] ?? \pocketmine\item\VanillaItems::AIR());
	$wrappers[] = \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper::legacy($stack);
}
$inventoryPacket = testInventoryContent(0, $wrappers, new \pocketmine\network\mcpe\protocol\types\inventory\FullContainerName(0), new \pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper(0, \pocketmine\network\mcpe\protocol\types\inventory\ItemStack::null()));
$decodedInventory = packetRead(\pocketmine\network\mcpe\protocol\InventoryContentPacket::class, packetWrite($inventoryPacket));
foreach($decodedInventory->items as $slot => $wrapper){
	verify($converter->netItemStackToCore($wrapper->getItemStack())->equalsExact($contents[$slot] ?? \pocketmine\item\VanillaItems::AIR()), '26.60 inventory packet preserves occupied and empty slots');
}
file_put_contents($directory . '/inventory.nbt', $nbt->write(new TreeRoot(CompoundTag::create()->setTag('Inventory', new ListTag(array_map(static fn(int $slot) => $contents[$slot]->nbtSerialize($slot), array_keys($contents))))->setTag('PlacedBlock', $placedState->toNbt()))));

// This entry deliberately lacks the Block tag. Native full NBT is tested above.
$fallback = GlobalItemDataHandlers::getDeserializer()->deserializeType(new SavedItemData('restart:block'));
verify($fallback->getBlock()->getTypeId() === $ids['restart:block'], 'Name-only block item resolves to its registered prototype');
verify(\pocketmine\data\bedrock\item\BlockItemIdMap::getInstance()->lookupItemId('restart:block') === 'restart:block', 'Block/item mapping is bidirectional');

// A GUI or plugin may have taken its packet snapshot before registering items.
$listener = new CustomiesListener();
$event = new DataPacketSendEvent([], [$earlyPacket]);
$listener->onDataPacketSend($event);
$packet = $event->getPackets()[0];
verify($packet->senderSubId === 1 && $packet->recipientSubId === 2, 'Registry repair preserves subclient routing');
$table = [];
foreach($packet->getEntries() as $entry){ $table[$entry->getStringId()] = $entry->getNumericId(); }
foreach($factory->getItemTableEntries() as $entry){ verify(($table[$entry->getStringId()] ?? null) === $entry->getNumericId(), 'Login snapshot must contain every current custom item'); }
verify(count($earlyPacket->getEntries()) + 3 === count($packet->getEntries()), 'Early cached packet stays unchanged and native entries survive');
$listener->onDataPacketSend($event);
verify(count($event->getPackets()[0]->getEntries()) === count($table), 'Repeat handling must not duplicate entries');
$decoded = packetRead(ItemRegistryPacket::class, packetWrite($packet));
verify(count($decoded->getEntries()) === count($table), 'Repaired login registry survives the 26.60 wire codec');

// Same-length snapshots must invalidate the cache; Customies must replace stale own definitions.
$foreign = new ItemTypeEntry('foreign:kept', -30000, false, 0, new CacheableNbt(CompoundTag::create()));
$stale = new ItemTypeEntry('restart:wand', 30000, false, 0, new CacheableNbt(CompoundTag::create()));
$replacement = new DataPacketSendEvent([], [ItemRegistryPacket::create([$foreign, $stale])]);
$listener->onDataPacketSend($replacement);
verify(in_array($foreign, $replacement->getPackets()[0]->getEntries(), true), 'Preserve foreign registry entries');
verify(!in_array($stale, $replacement->getPackets()[0]->getEntries(), true), 'Replace stale custom entries');
$other = new ItemTypeEntry('foreign:changed', -30001, false, 0, new CacheableNbt(CompoundTag::create()));
$replacement->setPackets([ItemRegistryPacket::create([$other, $stale])]);
$listener->onDataPacketSend($replacement);
verify(in_array($other, $replacement->getPackets()[0]->getEntries(), true) && !in_array($foreign, $replacement->getPackets()[0]->getEntries(), true), 'Same-size item snapshot changes invalidate cache');
try{
	\customiesdevs\customies\network\ItemRegistryBuilder::build([new ItemTypeEntry('foreign:conflict', $ids['restart:wand'], false, 0, new CacheableNbt(CompoundTag::create()))], $factory->getItemTableEntries());
	throw new LogicException('Accepted conflicting numeric IDs');
}catch(UnexpectedValueException){}
$conflictPacket = ItemRegistryPacket::create([new ItemTypeEntry('foreign:conflict', $ids['restart:wand'], false, 0, new CacheableNbt(CompoundTag::create()))]);
$conflictEvent = new DataPacketSendEvent([], [$conflictPacket]);
$listener->onDataPacketSend($conflictEvent);
verify($conflictEvent->isCancelled(), 'Conflicting plugin snapshot cancels login without throwing into the server event loop');
verify($conflictEvent->getPackets()[0] === $conflictPacket, 'Rejected snapshot stays untouched');

// Alias failure must not publish a deserializer which survives a caught exception.
StringToItemParser::getInstance()->register('restart:alias_conflict', static fn() => \pocketmine\item\VanillaItems::STICK());
try{
	$factory->registerItem(static fn() => new Item(new ItemIdentifier(ItemTypeIds::newId()), 'Conflict'), 'restart:alias_conflict');
	throw new LogicException('Accepted a duplicate parser alias');
}catch(InvalidArgumentException){}
verify(GlobalItemDataHandlers::getDeserializer()->getDeserializerForId('restart:alias_conflict') === null, 'Failed alias registration leaves no partial saved-item mapping');
echo "PASS $cycle\n";
