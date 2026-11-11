<?php
declare(strict_types=1);
// Multi-version support: legacy wire rules on any host; on multi-protocol hosts (NetherGames) every accepted
// protocol's registries, palette order, item round trips and join packets.
require __DIR__ . '/run.php';
require_once __DIR__ . '/packets.php';

use customiesdevs\customies\block\BlockPalette;
use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\properties\Box;
use customiesdevs\customies\CustomiesListener;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use customiesdevs\customies\network\BedrockVersion;
use customiesdevs\customies\network\BlockPaletteBuilder;
use customiesdevs\customies\network\ProtocolAdapter;
use customiesdevs\customies\network\ProtocolRegistry;
use customiesdevs\customies\util\NBT;
use pocketmine\block\{BlockBreakInfo, BlockIdentifier, BlockTypeIds, BlockTypeInfo};
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\{Item, ItemIdentifier, ItemTypeIds};
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\convert\{BlockStateDictionary, BlockTranslator, TypeConverter};
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\{BiomeDefinitionListPacket, ItemRegistryPacket, ProtocolInfo, StartGamePacket};
use pocketmine\network\mcpe\protocol\types\{CacheableNbt, ItemTypeEntry};
use pocketmine\utils\Filesystem;

/** @return list<float> */
function floats(CompoundTag $tag, string $name): array { return array_map(static fn($t) => $t->getValue(), $tag->getListTag($name)->getValue()); }
function collisionNbt(Box ...$boxes): CompoundTag { return NBT::getTagType((new B\CollisionBoxComponent())->addBoxes($boxes)->getValue()); }

// ---- Legacy wire rules (host independent) ----
$legacy = ProtocolAdapter::adaptCollisionBox(collisionNbt(Box::defaultBox()), '1.21.120');
check($legacy->getByte('enabled') === 1 && floats($legacy, 'origin') === [-8.0, 0.0, -8.0] && floats($legacy, 'size') === [16.0, 16.0, 16.0] && $legacy->getTag('boxes') === null, 'pre-1.21.130 clients get one origin/size box');
$asymmetric = ProtocolAdapter::adaptCollisionBox(collisionNbt(Box::fromMinMax(new Vector3(4, 0, 0), new Vector3(16, 24, 8))), '1.21.100');
check(floats($asymmetric, 'origin') === [-8.0, 0.0, -8.0] && floats($asymmetric, 'size') === [12.0, 16.0, 8.0], 'legacy boxes use geometry origin/size with mirrored X and the 16-pixel height limit');
$enclosed = ProtocolAdapter::adaptCollisionBox(collisionNbt(Box::fromMinMax(new Vector3(0, 0, 0), new Vector3(8, 4, 8)), Box::fromMinMax(new Vector3(8, 4, 8), new Vector3(16, 12, 16))), '1.20.80');
check(floats($enclosed, 'origin') === [-8.0, 0.0, -8.0] && floats($enclosed, 'size') === [16.0, 12.0, 16.0], 'several boxes become their enclosing box for single-box clients');
$high = ProtocolAdapter::adaptCollisionBox(collisionNbt(Box::fromMinMax(new Vector3(0, 18, 0), new Vector3(16, 24, 16))), '1.21.0');
check(floats($high, 'origin')[1] === 15.0 && floats($high, 'size')[1] === 1.0, 'collision above the legacy height keeps a one-pixel top box');
$disabled = ProtocolAdapter::adaptCollisionBox(NBT::getTagType((new B\CollisionBoxComponent(false))->getValue()), '1.21.0');
check($disabled->getByte('enabled') === 0 && floats($disabled, 'size') === [16.0, 16.0, 16.0], 'disabled legacy collision keeps the full-block placeholder');
$modern = collisionNbt(Box::defaultBox(), Box::defaultBox());
check(ProtocolAdapter::adaptCollisionBox($modern, '1.21.130') === $modern, '1.21.130 and later receive boxes unchanged');
$twenty = CompoundTag::create()->setByte('enabled', 1)->setTag('boxes', new pocketmine\nbt\tag\ListTag(array_map(static fn(int $i) => NBT::getTagType(Box::fromMinMax(new Vector3(0, $i * 0.5, 0), new Vector3(16, $i * 0.5 + 1, 16))->toNbtArray()), range(0, 19))));
$sixteen = ProtocolAdapter::adaptCollisionBox($twenty, '1.26.60');
$last = $sixteen->getListTag('boxes')->get(15);
check(count($sixteen->getListTag('boxes')) === 16 && $last->getFloat('minY') === 7.5 && $last->getFloat('maxY') === 10.5, 'clients below a box limit keep the first boxes and enclose the rest');
check(ProtocolAdapter::adaptCollisionBox($twenty, '1.26.70') === $twenty, '1.26.70 clients accept twenty boxes');
$plain = new ItemTypeEntry('multiversion:plain', 30000, false, 2, new CacheableNbt(CompoundTag::create()));
$component = new ItemTypeEntry('multiversion:component', 30001, true, 1, new CacheableNbt(CompoundTag::create()));
check(ProtocolAdapter::legacyItemComponentEntries([$plain, $component]) === [$component], 'the legacy component packet only lists component-based items');
check(!BedrockVersion::supports('item_registry_packet', '1.21.50') && BedrockVersion::supports('item_registry_packet', '1.21.60'), 'item components move to the item registry at 1.21.60');
check(!BedrockVersion::supports('collision_box_list', '1.21.124') && BedrockVersion::supports('collision_box_list', '1.21.130'), 'collision box lists start at 1.21.130');

// ---- Fixtures ----
final class MultiVersionItem extends Item implements ItemComponents {
	use ItemComponentsTrait;
	public function __construct(int $id){
		parent::__construct(new ItemIdentifier($id), 'Multi-version item');
		$this->initComponent('multiversion_item', 'Multi-version item');
	}
}
$blockId = BlockTypeIds::newId();
$factory->registerBlock(static function() use($blockId): TraitBlock {
	$block = new TraitBlock(new BlockIdentifier($blockId), 'Multi-version block', new BlockTypeInfo(BlockBreakInfo::instant()));
	$block->addComponent((new B\CollisionBoxComponent())->addBoxes([Box::fromMinMax(new Vector3(0, 0, 0), new Vector3(16, 8, 16)), Box::fromMinMax(new Vector3(0, 8, 0), new Vector3(8, 20, 16))]));
	return $block;
}, 'multiversion:block');
$itemId = ItemTypeIds::newId();
$items = CustomiesItemFactory::getInstance();
$items->registerItem(static fn() => new MultiVersionItem($itemId), 'multiversion:item');
$customItem = $items->get('multiversion:item');
$customEntries = $items->getItemTableEntries();
$customStates = BlockPalette::getInstance()->getCustomStates();

$protocols = ProtocolRegistry::getAcceptedProtocols();
if(!ProtocolRegistry::isMultiProtocol()){
	check($protocols === [ProtocolInfo::CURRENT_PROTOCOL] && ProtocolRegistry::getMinecraftVersion(ProtocolInfo::CURRENT_PROTOCOL) === ProtocolInfo::MINECRAFT_VERSION_NETWORK, 'single-protocol hosts expose only their current protocol');
	check(BlockPalette::getInstance()->getDictionaryCount() === 1 && $items->getDictionaryCount() === 1, 'single-protocol hosts patch one block and one item dictionary');
	echo 'PASS: legacy collision/item wire rules; single-protocol host ', ProtocolInfo::MINECRAFT_VERSION_NETWORK, ' needs no per-protocol registries.', PHP_EOL;
	return;
}

// ---- Every accepted protocol ----
$paths = (new ReflectionClassConstant(BlockTranslator::class, 'PATHS'))->getValue();
$oldState = new ReflectionProperty(pocketmine\network\mcpe\convert\BlockStateDictionaryEntry::class, 'oldBlockStateData');
$networkName = static fn($entry): string => $oldState->getValue($entry)?->getName() ?? $entry->getStateName();
$listener = new CustomiesListener();
$sessionClass = new ReflectionClass(NetworkSession::class);
$sessionProtocol = new ReflectionProperty(NetworkSession::class, 'protocolId');
$fallback = new ReflectionProperty(BlockTranslator::class, 'fallbackStateId');
$customNames = array_flip(array_map(static fn($s) => $s->getStateName(), $customStates));
$attachTime = 0.0; $legacyClients = 0; $legacyCollision = 0;
foreach($protocols as $protocol){
	$version = ProtocolRegistry::getMinecraftVersion($protocol);
	$started = microtime(true);
	$converter = TypeConverter::getInstance($protocol);
	$attachTime += microtime(true) - $started;
	$translator = $converter->getBlockTranslator();
	$dictionary = $translator->getBlockStateDictionary();
	$states = $dictionary->getStates();

	// Vanilla states keep the client's order; custom states sit where the client's name sort puts them.
	$reference = BlockStateDictionary::loadFromString(
		Filesystem::fileGetContents(str_replace('.nbt', $paths[$protocol][BlockTranslator::CANONICAL_BLOCK_STATES_PATH] . '.nbt', pocketmine\data\bedrock\BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT)),
		Filesystem::fileGetContents(str_replace('.json', $paths[$protocol][BlockTranslator::BLOCK_STATE_META_MAP_PATH] . '.json', pocketmine\data\bedrock\BedrockDataFiles::BLOCK_STATE_META_MAP_JSON))
	);
	$vanilla = array_values(array_filter($states, static fn($s) => !isset($customNames[$s->getStateName()])));
	check(count($vanilla) === count($reference->getStates()) && count($states) === count($vanilla) + count($customStates), "$version: palette keeps every vanilla state and adds every custom state");
	foreach($reference->getStates() as $i => $expected){
		check($networkName($vanilla[$i]) === $networkName($expected) && $vanilla[$i]->getRawStateProperties() === $expected->getRawStateProperties(), "$version: vanilla state $i keeps its client order");
	}
	$groups = [];
	foreach($states as $state){ $name = $networkName($state); if(end($groups) !== $name){ $groups[] = $name; } }
	check(count($groups) === count(array_unique($groups)) && $groups === BlockPaletteBuilder::sortNames($groups), "$version: block names are contiguous and sorted the way the client sorts them");

	// Custom and vanilla states translate to the runtime IDs of this palette.
	foreach($factory->getBlockPaletteEntries() as $entry){
		$block = $factory->get($entry->getName());
		$runtime = $translator->internalIdToNetworkId($block->getStateId());
		check($runtime !== $fallback->getValue($translator) && $states[$runtime]->getStateName() === $entry->getName(), "$version: {$entry->getName()} has its own runtime ID");
	}
	$stone = pocketmine\block\VanillaBlocks::STONE()->getStateId();
	check($networkName($states[$translator->internalIdToNetworkId($stone)]) === $networkName($reference->getStates()[$reference->lookupStateIdFromData(pocketmine\world\format\io\GlobalBlockStateHandlers::getSerializer()->serialize($stone))]), "$version: vanilla blocks still resolve after insertion");

	// Items resolve and round-trip in this protocol's dictionary.
	foreach($customEntries as $entry){
		check($converter->getItemTypeDictionary()->fromStringId($entry->getStringId()) === $entry->getNumericId(), "$version: item {$entry->getStringId()} is registered");
	}
	$net = $converter->coreItemStackToNet($customItem);
	check($net->getId() === $customItem->getTypeId() && $converter->netItemStackToCore($net)->equalsExact($customItem), "$version: custom item stacks round-trip");

	// Join packets, as the host sends them to a client of this protocol.
	$session = $sessionClass->newInstanceWithoutConstructor();
	$sessionProtocol->setValue($session, $protocol);
	$start = testCreate(StartGamePacket::class, [
		'playerGamemode' => pocketmine\network\mcpe\protocol\types\GameMode::SURVIVAL, 'playerPosition' => new Vector3(0, 64, 0),
		'playerActorProperties' => new CacheableNbt(CompoundTag::create()), 'levelSettings' => (static function(){
			$s = new pocketmine\network\mcpe\protocol\types\LevelSettings(); $s->seed = 0; $s->spawnSettings = new pocketmine\network\mcpe\protocol\types\SpawnSettings(0, '', 0);
			$s->worldGamemode = pocketmine\network\mcpe\protocol\types\GameMode::SURVIVAL; $s->difficulty = 1; $s->spawnPosition = new pocketmine\network\mcpe\protocol\types\BlockPosition(0, 64, 0);
			$s->rainLevel = 0.0; $s->lightningLevel = 0.0; $s->commandsEnabled = true; $s->experiments = new pocketmine\network\mcpe\protocol\types\Experiments([], false); return $s;
		})(), 'playerMovementSettings' => testMovementSettings(), 'worldTemplateId' => Ramsey\Uuid\Uuid::fromString(Ramsey\Uuid\Uuid::NIL),
		'networkPermissions' => new pocketmine\network\mcpe\protocol\types\NetworkPermissions(false), 'serverTelemetryData' => new pocketmine\network\mcpe\protocol\types\ServerTelemetryData('', '', '', ''),
		'itemTable' => $converter->getItemTypeDictionary()->getEntries()
	]);
	$sent = [$start];
	if($protocol >= ProtocolInfo::PROTOCOL_1_21_60){ $sent[] = ItemRegistryPacket::create($converter->getItemTypeDictionary()->getEntries()); }
	$sent[] = StaticPacketCache::getInstance()->getBiomeDefs($protocol);
	$event = new DataPacketSendEvent([$session], $sent);
	$listener->onDataPacketSend($event);
	$out = $event->getPackets();
	check(!$event->isCancelled(), "$version: join packets are not cancelled");

	$decodedStart = packetRead(StartGamePacket::class, packetWrite($out[0], $protocol), $protocol);
	$palette = [];
	foreach($decodedStart->blockPalette as $entry){ $palette[$entry->getName()] = $entry->getStates()->getRoot(); }
	$collision = $palette['multiversion:block']?->getCompoundTag('components')?->getCompoundTag('minecraft:collision_box');
	if(BedrockVersion::supports('collision_box_list', $version)){
		check($collision !== null && count($collision->getListTag('boxes')) === 2, "$version: collision boxes stay a list");
	}else{
		check($collision !== null && $collision->getTag('boxes') === null && floats($collision, 'size') === [16.0, 16.0, 16.0], "$version: collision becomes one enclosing legacy box");
		++$legacyCollision;
	}
	if(BedrockVersion::supports('item_registry_packet', $version)){
		$registry = packetRead(ItemRegistryPacket::class, packetWrite($out[1], $protocol), $protocol);
		$ids = []; foreach($registry->getEntries() as $entry){ $ids[$entry->getStringId()] = $entry->getNumericId(); }
		foreach($customEntries as $entry){ check(($ids[$entry->getStringId()] ?? null) === $entry->getNumericId(), "$version: item registry lists {$entry->getStringId()}"); }
		check(!array_filter($out, static fn($p) => $p instanceof ItemRegistryPacket && $p !== $out[1]), "$version: no legacy component packet for current clients");
	}else{
		++$legacyClients;
		$table = []; foreach($decodedStart->itemTable as $entry){ $table[$entry->getStringId()] = $entry->getNumericId(); }
		foreach($customEntries as $entry){ check(($table[$entry->getStringId()] ?? null) === $entry->getNumericId(), "$version: StartGame item table lists {$entry->getStringId()}"); }
		check(count($out) === 3 && $out[1] instanceof BiomeDefinitionListPacket && $out[2] instanceof ItemRegistryPacket, "$version: legacy item components follow the biome definitions");
		$components = packetRead(ItemRegistryPacket::class, packetWrite($out[2], $protocol), $protocol)->getEntries();
		check(array_map(static fn($e) => $e->getStringId(), $components) === ['multiversion:item'], "$version: legacy component packet lists only component-based items");
		$root = $components[0]->getComponentNbt()->getRoot();
		check($root->getString('name') === 'multiversion:item' && $root->getInt('id') === $customItem->getTypeId() && $root->getCompoundTag('components') !== null, "$version: legacy component NBT keeps name, id and components");
		$again = new DataPacketSendEvent([$session], [StaticPacketCache::getInstance()->getBiomeDefs($protocol)]);
		$listener->onDataPacketSend($again);
		check(count($again->getPackets()) === 1, "$version: legacy components are sent once per session");
	}
}
check(BlockPalette::getInstance()->getDictionaryCount() < count($protocols), 'protocols sharing a palette share one patched dictionary');
check($legacyClients > 0 && $legacyCollision > 0, 'the host accepts clients that need the legacy forms');
printf("PASS: %d protocols (%s to %s): %d block / %d item dictionaries, client palette order, runtime IDs, item round trips and join packets; %d legacy item, %d legacy collision clients; lazy palettes %.1fs total.%s",
	count($protocols), ProtocolRegistry::getMinecraftVersion($protocols[0]), ProtocolRegistry::getMinecraftVersion(end($protocols)),
	BlockPalette::getInstance()->getDictionaryCount(), $items->getDictionaryCount(), $legacyClients, $legacyCollision, $attachTime, PHP_EOL);
