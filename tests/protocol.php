<?php
declare(strict_types=1);
require __DIR__ . '/run.php';

use customiesdevs\customies\CustomiesListener;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\network\BlockPaletteBuilder;
use customiesdevs\customies\network\RuntimeCompatibility;
use customiesdevs\customies\util\Diagnostics;
use customiesdevs\customies\util\RegistrationGuard;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\ItemRegistryPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types as T;

function roundTrip(DataPacket $packet): DataPacket {
	$out = new ByteBufferWriter(); $packet->encode($out);
	$copy = new ($packet::class)(); $copy->decode(new ByteBufferReader($out->getData()));
	$reencoded = new ByteBufferWriter(); $copy->encode($reencoded);
	check($out->getData() === $reencoded->getData(), $packet->getName() . ' wire round trip must be byte-identical');
	return $copy;
}

RuntimeCompatibility::assertCompatible();
check(RuntimeCompatibility::getProfile(0) === 'unverified host protocol', 'unknown protocols must not claim validation');
$itemFactory = CustomiesItemFactory::getInstance();
$itemId = pocketmine\item\ItemTypeIds::newId();
$itemFactory->registerItem(static function() use($itemId): pocketmine\item\Item {
	$item = new class(new pocketmine\item\ItemIdentifier($itemId), 'Network item') extends pocketmine\item\Item implements customiesdevs\customies\item\ItemComponents {
		use customiesdevs\customies\item\ItemComponentsTrait;
	};
	customiesdevs\customies\item\preset\ItemPresets::basic($item, 'test_texture', 'Network item');
	return $item;
}, 'test:network_item');
$itemEntries = $itemFactory->getItemTableEntries();
$items = roundTrip(ItemRegistryPacket::create($itemEntries));
check(count($items->getEntries()) === count($itemEntries), 'all item and block-item entries transmitted');
foreach($items->getEntries() as $i => $entry){
	check($entry->getNumericId() === $itemEntries[$i]->getNumericId(), 'signed registry IDs round trip');
	check($entry->getComponentNbt()->getEncodedNbt() === $itemEntries[$i]->getComponentNbt()->getEncodedNbt(), 'registry component NBT round trip');
}

$settings = new T\LevelSettings();
$settings->seed = 42; $settings->spawnSettings = new T\SpawnSettings(0, '', 0);
$settings->worldGamemode = T\GameMode::SURVIVAL; $settings->difficulty = 1;
$settings->spawnPosition = new T\BlockPosition(0, 64, 0);
$settings->rainLevel = 0.0; $settings->lightningLevel = 0.0; $settings->commandsEnabled = true;
$settings->experiments = new T\Experiments(['third_party_feature' => true], true);
$start = StartGamePacket::create(
	1, 1, T\GameMode::SURVIVAL, new pocketmine\math\Vector3(0, 64, 0), 0.0, 0.0,
	new T\CacheableNbt(CompoundTag::create()), $settings, 'test', 'Compatibility test', '', false,
	new T\PlayerMovementSettings(20, true), 0, 0, '', false, 'Customies test', Ramsey\Uuid\Uuid::fromString(Ramsey\Uuid\Uuid::NIL),
	false, false, new T\NetworkPermissions(false), null, new T\ServerTelemetryData('', '', '', ''), [], 0
);
$external = new T\BlockPaletteEntry('other:external', new T\CacheableNbt(CompoundTag::create()));
$start->blockPalette = [$external];
$listener = new CustomiesListener();
$listener->onDataPacketSend(new DataPacketSendEvent([], [$start]));
check(in_array($external, $start->blockPalette, true), 'must retain other plugins incoming palette entries');
check(count($start->blockPalette) === count($factory->getBlockPaletteEntries()) + 1, 'all custom blocks merged once');
check($start->levelSettings->experiments->getExperiments()['third_party_feature'], 'must retain unrelated experiments');
$decodedStart = roundTrip($start);
check($decodedStart->levelSettings->experiments->getExperiments()['data_driven_items'], 'join experiments survive codec');
check($decodedStart->blockPalette[0]->getName() === $start->blockPalette[0]->getName(), 'join palette survives codec');
$names = array_map(static fn($e) => $e->getName(), $start->blockPalette);
check($names === BlockPaletteBuilder::sortNames($names), 'join and runtime palettes use the same ordering');
$listener->onDataPacketSend(new DataPacketSendEvent([], [$start]));
check(count($start->blockPalette) === count($names), 'repeat listener must not duplicate entries');
$changed = clone $start; $changed->levelSettings = clone $settings;
$different = new T\BlockPaletteEntry('other:replacement', new T\CacheableNbt(CompoundTag::create()));
$changed->blockPalette = [$different];
$listener->onDataPacketSend(new DataPacketSendEvent([], [$changed]));
check(in_array($different, $changed->blockPalette, true) && !in_array($external, $changed->blockPalette, true), 'same-size incoming palette changes must invalidate join cache');
$stack = ResourcePackStackPacket::create([], false, ProtocolInfo::MINECRAFT_VERSION_NETWORK, new T\Experiments(['third_party_feature' => true], true), false);
$listener->onDataPacketSend(new DataPacketSendEvent([], [$stack]));
$decodedStack = roundTrip($stack);
check($decodedStack->experiments->getExperiments() === $decodedStart->levelSettings->experiments->getExperiments(), 'resource stack and join experiments agree');

$before = count($itemFactory->getItemTableEntries());
foreach(['minecraft:reserved', 'MissingNamespace', 'test:Uppercase'] as $invalid){
	try { $itemFactory->registerItem(static fn() => throw new RuntimeException('validation must run before the closure'), $invalid); throw new RuntimeException('accepted invalid identifier'); }
	catch(InvalidArgumentException){}
}
try { $itemFactory->registerItem(static fn() => new pocketmine\item\Item(new pocketmine\item\ItemIdentifier($itemId), 'Duplicate'), 'test:conflict'); throw new RuntimeException('accepted duplicate ID'); } catch(InvalidArgumentException){}
try { $itemFactory->assertCanRegister('test:overflow', 32768); throw new RuntimeException('accepted overflowing registry ID'); } catch(InvalidArgumentException){}
check(count($itemFactory->getItemTableEntries()) === $before, 'invalid registration must not change registry entries');
check(pocketmine\item\StringToItemParser::getInstance()->parse('test:conflict') === null, 'conflicts must not leave a parser alias');
$badId = pocketmine\item\ItemTypeIds::newId();
try {
	$itemFactory->registerItem(static function() use($badId): pocketmine\item\Item {
		$item = new class(new pocketmine\item\ItemIdentifier($badId), 'Bad') extends pocketmine\item\Item implements customiesdevs\customies\item\ItemComponents { use customiesdevs\customies\item\ItemComponentsTrait; };
		$item->addComponent(new class implements customiesdevs\customies\item\component\ItemComponent {
			public function getName(): string { return 'test:invalid'; }
			public function getValue(): mixed { return null; }
			public function getPropertyMapping(): ?array { return null; }
		});
		return $item;
	}, 'test:bad_nbt');
	throw new RuntimeException('accepted invalid component NBT');
} catch(InvalidArgumentException){}
check(pocketmine\item\StringToItemParser::getInstance()->parse('test:bad_nbt') === null, 'bad NBT must not leave a parser alias');
check(count($itemFactory->getItemTableEntries()) === $before, 'bad NBT must not change item registry');
$beforeBlocks = count($factory->getBlockPaletteEntries());
try {
	$factory->registerBlock(static function(): pocketmine\block\Block {
		$block = new class(new pocketmine\block\BlockIdentifier(pocketmine\block\BlockTypeIds::newId()), 'Bad block', new pocketmine\block\BlockTypeInfo(pocketmine\block\BlockBreakInfo::instant())) extends customiesdevs\customies\block\TraitBlock {};
		$block->addComponent(new class implements customiesdevs\customies\block\component\BlockComponent {
			public function getName(): string { return 'test:invalid'; }
			public function getValue(): mixed { return null; }
		});
		return $block;
	}, 'test:bad_block_nbt');
	throw new LogicException('accepted invalid block NBT');
} catch(RuntimeException $e){ check(str_contains($e->getMessage(), 'tag type'), 'invalid block NBT should explain the component error'); }
check(count($factory->getBlockPaletteEntries()) === $beforeBlocks && count($itemFactory->getItemTableEntries()) === $before, 'bad block NBT must not publish block or item entries');
check(pocketmine\item\StringToItemParser::getInstance()->parse('test:bad_block_nbt') === null, 'bad block NBT must not leave a parser alias');
class NetworkEntity extends pocketmine\entity\object\ItemEntity {
	public static function getNetworkTypeId(): string { return 'test:actor'; }
}
$actorPacket = pocketmine\network\mcpe\cache\StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
$oldActorRoot = $actorPacket->identifiers->getRoot();
$oldActorCount = count($oldActorRoot->getListTag('idlist'));
customiesdevs\customies\entity\CustomiesEntityFactory::getInstance()->registerEntity(NetworkEntity::class, 'test:actor');
check(count($oldActorRoot->getListTag('idlist')) === $oldActorCount, 'entity registration must not mutate an already cached NBT root');
$decodedActors = roundTrip($actorPacket);
check(count($decodedActors->identifiers->getRoot()->getListTag('idlist')) === $oldActorCount + 1, 'new actor must be encoded in the registry packet');
try { customiesdevs\customies\entity\CustomiesEntityFactory::getInstance()->registerEntity(NetworkEntity::class, 'test:actor'); throw new RuntimeException('accepted duplicate entity'); } catch(InvalidArgumentException){}
check(Diagnostics::inspect('test:connections')['states'] !== [], 'inspect reports actual block state definitions');
check(Diagnostics::inspect('test:network_item')['kind'] === 'item', 'inspect standalone item');
check(Diagnostics::report()['contract_errors'] === [], 'support report contracts');
RegistrationGuard::freeze();
foreach([
	static fn() => $factory->registerBlock(static fn() => new TestBlock(), 'test:late_block'),
	static fn() => $itemFactory->registerItem(static fn() => throw new RuntimeException('late closure called'), 'test:late_item'),
	static fn() => customiesdevs\customies\entity\CustomiesEntityFactory::getInstance()->registerEntity('unused', 'test:late_entity')
] as $late){
	try { $late(); throw new RuntimeException('accepted registration after worker snapshot'); }
	catch(LogicException $e){ check(str_contains($e->getMessage(), 'restart'), 'late registration must explain recovery'); }
}
check(Diagnostics::report()['registry_frozen'], 'support report lifecycle');
echo 'Protocol integration passed: ', ProtocolInfo::CURRENT_PROTOCOL, ' / ', ProtocolInfo::MINECRAFT_VERSION_NETWORK, PHP_EOL;
