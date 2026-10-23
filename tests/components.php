<?php
declare(strict_types=1);

// Run with PHP 8.1+ and the Axolotl-MP vendor autoloader as the first argument.
$autoload = $argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php';
if(!is_file($autoload)){
	fwrite(STDERR, "Usage: php tests/components.php /path/to/axolotl/vendor/autoload.php\n");
	exit(1);
}
require $autoload;
spl_autoload_register(static function(string $class): void {
	$prefix = 'customiesdevs\\customies\\';
	if(str_starts_with($class, $prefix)){
		require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	}
});

use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\permutations\BlockPermutation;
use customiesdevs\customies\block\properties\BlockDescriptor;
use customiesdevs\customies\item\component as I;
use customiesdevs\customies\item\ItemComponentsTrait;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\item\properties\EffectType;
use customiesdevs\customies\item\properties\ParticleType;
use customiesdevs\customies\item\properties\SoundEvent;
use customiesdevs\customies\util\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;

set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function check(bool $condition, string $message): void {
	global $checks;
	++$checks;
	if(!$condition){
		throw new RuntimeException($message);
	}
}
function rejects(Closure $operation): void {
	try {
		$operation();
	}catch(InvalidArgumentException){
		check(true, 'Invalid input rejected');
		return;
	}
	throw new RuntimeException('Expected InvalidArgumentException');
}

// The pinned upstream catalogs make missing wrappers visible without network access.
$catalog = json_decode(file_get_contents(__DIR__ . '/component-catalog.json'), true, flags: JSON_THROW_ON_ERROR);
foreach(['item', 'block'] as $kind){
	$identifiers = [];
	foreach(glob(dirname(__DIR__) . '/src/' . $kind . '/component/*Component.php') as $file){
		if(preg_match("/return ['\"](minecraft:[a-z_]+)['\"];/", file_get_contents($file), $match) === 1){
			$identifiers[] = $match[1];
		}
	}
	foreach($catalog[$kind] as $identifier){
		check(in_array($identifier, $identifiers, true), 'Missing ' . $kind . ' wrapper: ' . $identifier);
	}
}

$item = new class {
	use ItemComponentsTrait;
	public function initialize(): void { $this->initComponent('test_icon', 'Test'); }
};
$block = new class {
	use B\BlockComponentsTrait;
	public function initialize(): void { $this->initComponents('test_texture', 'Test'); }
};
foreach([$item, $block] as $holder){
	check($holder->getComponents() === [], 'Fresh component collections are empty');
	check(!$holder->hasComponent('missing') && $holder->getComponent('missing') === null, 'Missing components are safe');
	$holder->initialize();
	$components = $holder->getComponents();
	$holder->initialize();
	check($components === $holder->getComponents(), 'Initialization is idempotent');
}

$weapon = (new I\KineticWeaponComponent())->getValue();
check(!isset($weapon['minecraft:kinetic_weapon']), 'Weapon payload is not nested twice');
check($weapon['reach'] === ['min' => 0.0, 'max' => 3.0], 'Weapon reach matches current schema');
check($weapon['creative_reach'] === $weapon['reach'], 'Creative reach inherits reach');
check(!isset($weapon['damage_conditions'], $weapon['dismount_conditions'], $weapon['knockback_conditions']), 'Unconfigured weapon effects are absent');
check($weapon['damage_multiplier'] === 1.0 && $weapon['delay']->getValue() === 0, 'Weapon defaults match schema');
$customWeapon = new I\KineticWeaponComponent(damageConditions: [], delay: 15, reach: ['min' => 2, 'max' => 5]);
$payload = $customWeapon->getValue();
check($payload['damage_conditions']['max_duration'] instanceof ShortTag, 'Effect duration retains short NBT type');
check($payload['damage_conditions']['max_duration']->getValue() === -1, 'Empty conditions use schema defaults');
check($payload['creative_reach'] === $payload['reach'], 'Custom reach is inherited');
$piercing = (new I\PiercingWeaponComponent(reach: ['min' => 1, 'max' => 6]))->getValue();
check($piercing['reach'] === $piercing['creative_reach'], 'Piercing creative reach inherits reach');

$use = (new I\UseModifiersComponent())->getValue();
check($use['start_using'] === 'if_first' && $use['emit_vibrations'] === true, 'Use modifiers match current defaults');
check((new I\DyeableComponent())->getValue()['default_color'] === [255, 255, 255], 'Default dye color is white');
$soundOnly = (new I\DurabilitySensorComponent())->addDurabilityThreshold(5, soundEvent: SoundEvent::BREAK);
$particleOnly = new I\DurabilitySensorComponent([['durability' => 10, 'particle_type' => ParticleType::SMOKE]]);
check(!isset($soundOnly->getValue()['durability_thresholds'][0]['particle_type']), 'Sound-only sensor omits particle');
check(!isset($particleOnly->getValue()['durability_thresholds'][0]['sound_event']), 'Particle-only sensor omits sound');
check((new B\InstrumentSoundComponent(null, 'note.bass'))->getValue() === ['down' => 'note.bass'], 'Unset instrument face is omitted');
check((new B\LiquidDetectionComponent(stopsLiquidFlowingFromDirection: ['down', 'east', 'down']))->getValue()['detectionRules'][0]['stopsLiquidFromDirection']->getValue() === 33, 'Liquid faces retain protocol mask');

$placer = new I\BlockPlacerComponent('test:block', [new BlockDescriptor('minecraft:stone')], true, true);
check($placer->getValue()['use_on'] === [['name' => 'minecraft:stone']], 'Block descriptors serialize');
check($placer->getValue()['aligned_placement'] === true, 'Aligned placement is supported');
$entityPlacer = new I\EntityPlacerComponent('test:entity', ['minecraft:stone'], ['minecraft:dirt']);
check($entityPlacer->getValue()['dispense_on'] === ['minecraft:dirt'], 'Entity placement supports dispenser filters');
$producer = new B\RedstoneProducerComponent(15, 'up', ['north', 'south']);
check($producer->getValue()['connected_faces'] === ['north', 'south'], 'Producer faces serialize');
check((new B\RedstoneProducerComponent(0, connectedFaces: []))->getValue()['connected_faces'] instanceof ListTag, 'Empty face list stays a list');
check((new B\CraftingTableComponent([]))->getValue()['crafting_tags'] instanceof ListTag, 'Empty recipe tags stay a list');

foreach([
	fn() => new I\UseModifiersComponent(-0.1),
	fn() => new I\UseModifiersComponent(1.1),
	fn() => new I\UseModifiersComponent(useDuration: NAN),
	fn() => new I\UseModifiersComponent(useDuration: -1),
	fn() => new I\UseModifiersComponent(startUsing: 'invalid'),
	fn() => new I\KineticWeaponComponent(delay: 32768),
	fn() => new I\KineticWeaponComponent(damageConditions: ['max_duration' => -32769]),
	fn() => new I\KineticWeaponComponent(damageConditions: ['min_speed' => -1]),
	fn() => new I\KineticWeaponComponent(damageConditions: ['min_speed' => 'fast']),
	fn() => new I\PiercingWeaponComponent(reach: ['min' => 4, 'max' => 3]),
	fn() => new I\PiercingWeaponComponent(reach: ['min' => 0, 'max' => INF]),
	fn() => new I\PiercingWeaponComponent(reach: ['min' => 0]),
	fn() => new I\StorageItemComponent(maxSlots: 65),
	fn() => new I\StorageItemComponent(maxSlots: -1),
	fn() => new I\StorageWeightLimitComponent(65),
	fn() => new I\StorageWeightModifierComponent(-1),
	fn() => new I\WearableComponent(I\WearableComponent::SLOT_ARMOR_HEAD, -1),
	fn() => new I\DyeableComponent('#zzzzzz'),
	fn() => new I\DyeableComponent('##ffffff'),
	fn() => new I\DurabilitySensorComponent([[]]),
	fn() => (new I\DurabilitySensorComponent())->getValue(),
	fn() => (new I\DamageAbsorptionComponent())->getValue(),
	fn() => (new I\FoodComponent())->addEffect(EffectType::SPEED, 5, chance: 1.1),
	fn() => new B\InstrumentSoundComponent(null, null),
	fn() => new B\LiquidDetectionComponent(liquidType: 'lava'),
	fn() => new B\LiquidDetectionComponent(stopsLiquidFlowingFromDirection: ['sideways']),
	fn() => new I\BlockPlacerComponent(''),
	fn() => new I\BlockPlacerComponent('test:block', [123]),
	fn() => new I\BlockPlacerComponent('test:block', array_fill(0, 257, 'minecraft:stone')),
	fn() => new I\EntityPlacerComponent('test:entity', dispenseOn: [false]),
	fn() => new B\MovableComponent('invalid'),
	fn() => new B\MovableComponent('push', 'same'),
	fn() => new B\PrecipitationInteractionsComponent('invalid'),
	fn() => new B\RedstoneProducerComponent(16),
	fn() => new B\RedstoneProducerComponent(1, 'sideways'),
	fn() => new B\RedstoneConsumerComponent(-1),
	fn() => new BlockPermutation('1', new B\RedstoneConsumerComponent()),
	fn() => new B\TickComponent([2, 1]),
	fn() => new B\TickComponent([0, 4294967296]),
	fn() => new B\TickComponent([1.0, 2]),
	fn() => new B\TickComponent([1]),
	fn() => new B\CraftingTableComponent([str_repeat('a', 65)]),
	fn() => new B\CraftingTableComponent(array_fill(0, 65, 'tag')),
	fn() => new B\LootComponent(str_repeat('a', 257)),
	fn() => new B\EntityFallOnComponent(INF)
] as $invalid){
	rejects($invalid);
}

// Serialize using the actual server's network NBT implementation, without mocks.
$serializer = new NetworkNbtSerializer();
$components = [
	$customWeapon, new I\PiercingWeaponComponent(), new I\UseModifiersComponent(),
	$soundOnly, $particleOnly, $placer, $entityPlacer,
	new B\CraftingTableComponent(['crafting_table']), new B\CraftingTableComponent([]),
	new B\MovableComponent(), new B\PrecipitationInteractionsComponent('snowlogging'),
	new B\RedstoneConductivityComponent(), new B\RedstoneConsumerComponent(), $producer,
	new B\RedstoneProducerComponent(0, connectedFaces: []), new B\TickComponent([0, 20]),
	new B\TickComponent([2147483648, 4294967295]),
	new B\EntityFallOnComponent(), new B\LootComponent('loot_tables/blocks/test.json'),
	new B\ReplaceableComponent(), new B\InstrumentSoundComponent(null, 'note.bass')
];
foreach($components as $component){
	$tag = NBT::getTagType($component->getValue());
	$root = new TreeRoot(CompoundTag::create()->setTag($component->getName(), $tag));
	$encoded = $serializer->write($root);
	$decoded = $serializer->read($encoded);
	check($serializer->write($decoded) === $encoded, $component->getName() . ' network NBT round trip');
}
check(NBT::getTagType([]) instanceof CompoundTag, 'Empty marker component remains a compound');
check(NBT::getTagType([1, 2]) instanceof ListTag, 'Sequential arrays become lists');
check((new B\TickComponent([0, 4294967295]))->getValue()['interval_range'][1]->getValue() === -1, 'Unsigned tick intervals preserve all 32 bits');
rejects(fn() => NBT::getTagType(['bad' => null]));

// Exercise the factory boundary, including defaults, component placement and seconds-to-ticks conversion.
$factoryItem = new class(new ItemIdentifier(12345), 'Test') extends Item implements ItemComponents {
	use ItemComponentsTrait;
};
$factory = new CustomiesItemFactory();
$createNbt = new ReflectionMethod($factory, 'createItemNbt');
$creativeInfo = new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_ITEMS);
$factoryNbt = $createNbt->invoke($factory, $factoryItem, 'test:item', 12345, $creativeInfo);
check($factoryNbt->getCompoundTag('components')->getTag('item_tags') instanceof ListTag, 'Factory emits an empty item_tags list');
$factoryItem->addComponent(new I\UseModifiersComponent(useDuration: 1.5));
$factoryItem->addComponent(new I\IconComponent('test_icon'));
$factoryItem->addComponent(new I\ItemTagsComponent(['test:tag']));
$factoryItem->addComponent($customWeapon);
$factoryNbt = $createNbt->invoke($factory, $factoryItem, 'test:item', 12345, $creativeInfo);
$factoryComponents = $factoryNbt->getCompoundTag('components');
$properties = $factoryComponents->getCompoundTag('item_properties');
check($properties->getInt('use_duration') === 30, 'Factory converts seconds to ticks exactly once');
check($properties->getCompoundTag('minecraft:icon') !== null, 'Factory places icon in item_properties');
check($factoryComponents->getCompoundTag('minecraft:kinetic_weapon')->getShort('delay') === 15, 'Factory emits a flat weapon payload');
check($factoryComponents->getListTag('item_tags')->get(0)->getValue() === 'test:tag', 'Factory emits item tag names');
$encoded = $serializer->write(new TreeRoot($factoryNbt));
check($serializer->write($serializer->read($encoded)) === $encoded, 'Complete factory payload round trips');
echo "Passed $checks component checks\n";
