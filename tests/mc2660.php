<?php
declare(strict_types=1);
require __DIR__ . '/run.php';

use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\properties\Box;
use customiesdevs\customies\item\component as I;
use customiesdevs\customies\item\properties\SoundEvent;
use customiesdevs\customies\util\NBT;
use pocketmine\math\{Vector3, AxisAlignedBB};
use pocketmine\block\{BlockIdentifier, BlockTypeInfo, BlockBreakInfo};
use pocketmine\nbt\tag\{CompoundTag, ListTag, ByteTag, FloatTag, StringTag};

$manifest = yaml_parse_file(dirname(__DIR__) . '/plugin.yml');
check(!isset($manifest['commands']) && !isset($manifest['permissions']), 'library must not register commands or permissions');
check((new ReflectionMethod(customiesdevs\customies\Customies::class, 'onCommand'))->getDeclaringClass()->getName() === pocketmine\plugin\PluginBase::class, 'library command override must be removed');
check($manifest['api'] === '5.51.0', '26.60 library requires Axolotl API 5.51.0');
check(pocketmine\network\mcpe\protocol\ProtocolInfo::CURRENT_PROTOCOL === 2225, '26.60 fixture must use the actual 2225 codec');

function rejects2660(Closure $action, string $message): void {
	try { $action(); } catch(InvalidArgumentException){ return; }
	throw new RuntimeException($message);
}

// Mojang's 26.60 asymmetric example: origin/size pixels have mirrored X in voxel coordinates.
$box = new Box(new Vector3(-8, 0, -8), new Vector3(12, 24, 8));
$expected = ['minX'=>4.0, 'minY'=>0.0, 'minZ'=>0.0, 'maxX'=>16.0, 'maxY'=>24.0, 'maxZ'=>8.0];
check($box->toNbtArray() === $expected, '26.60 geometry-to-voxel conversion must mirror X');
check(Box::fromMinMax(new Vector3(4,0,0), new Vector3(16,24,8))->toNbtArray() === $expected, 'min/max input must preserve voxel bounds');
$local = new AxisAlignedBB(0.125, 0.0, 0.25, 0.15625, 1.5, 0.75);
$restored = Box::fromBlockAABB($local)->toBlockAABB();
foreach(['minX','minY','minZ','maxX','maxY','maxZ'] as $coordinate){ check($local->$coordinate === $restored->$coordinate, 'fractional server boxes must round-trip without clamping to whole pixels'); }
foreach([
	static fn() => Box::fromMinMax(new Vector3(8,0,0), new Vector3(4,16,16)),
	static fn() => Box::fromMinMax(new Vector3(-1,0,0), new Vector3(16,16,16)),
	static fn() => Box::fromMinMax(new Vector3(0,0,0), new Vector3(16,25,16)),
	static fn() => new Box(new Vector3(NAN,0,0), new Vector3(1,1,1)),
	static fn() => new Box(new Vector3(0,0,0), new Vector3(INF,1,1)),
	static fn() => new Box(new Vector3(8,0,0), new Vector3(1,1,1)),
	static fn() => new Box(new Vector3(0,0,0), new Vector3(0,1,1))
] as $invalid){ rejects2660($invalid, 'accepted invalid collision coordinates'); }

$collision = (new B\CollisionBoxComponent())->addBox($box)->addBox(Box::defaultBox());
check(count($collision->getValue()['boxes']) === 2, 'adding a second box must preserve the first');
$collision->addBoxes([$box]);
check(count($collision->getValue()['boxes']) === 3, 'bulk append must preserve earlier boxes');
rejects2660(static fn() => $collision->addBoxes([$box, 'invalid']), 'accepted invalid box list');
check(count($collision->getValue()['boxes']) === 3, 'invalid bulk append must be atomic');
$limit = (new B\CollisionBoxComponent())->addBoxes(array_fill(0, 16, $box));
rejects2660(static fn() => $limit->addBox($box), 'accepted more than sixteen boxes');
check(count($limit->getValue()['boxes']) === 16, 'overflow must preserve the valid box list');
$disabled = NBT::getTagType((new B\CollisionBoxComponent(false))->addBox($box)->getValue());
check($disabled->getListTag('boxes') instanceof ListTag && count($disabled->getListTag('boxes')) === 0 && $disabled->getByte('enabled') === 0, 'disabled collisions need an empty NBT list, not an empty box placeholder');
$collisionTag = NBT::getTagType($collision->getValue());
foreach($collisionTag->getListTag('boxes') as $entry){
	foreach(array_keys($expected) as $key){ check($entry->getTag($key) instanceof FloatTag, 'collision coordinates must remain NBT floats'); }
}
// Compare wrapper field/tag types with the host's actual 26.60 data-driven definitions.
$canonicalChecked = false;
foreach(pocketmine\network\mcpe\cache\StaticPacketCache::getInstance()->getBlockPaletteEntries() as $entry){
	$native = $entry->getStates()->getRoot()->getCompoundTag('components')?->getCompoundTag('minecraft:collision_box');
	if($native === null || $native->getByte('enabled') === 0){ continue; }
	check($native->getTag('enabled') instanceof ByteTag && $native->getListTag('boxes') !== null, 'host collision envelope must match wrapper');
	foreach($native->getListTag('boxes') as $nativeBox){ foreach(array_keys($expected) as $key){ check($nativeBox->getTag($key) instanceof FloatTag, 'host min/max must use the same float fields'); } }
	$canonicalChecked = true; break;
}
check($canonicalChecked, '26.60 fixture must contain a real collision definition');

$metadata = NBT::getTagType((new B\BlockEntityComponent(true, 9, true, '箱子'))->getValue());
check($metadata->getTag('entity_storage') instanceof ByteTag && $metadata->getByte('entity_storage') === 1, 'entity storage flag must be a byte');
check($metadata->getCompoundTag('container')->getTag('title') instanceof StringTag && $metadata->getCompoundTag('container')->getString('title') === '箱子', 'container title must preserve UTF-8');
check(!array_key_exists('entity_storage', (new B\BlockEntityComponent())->getValue()), 'default block entity metadata remains minimal');
new B\BlockEntityComponent(false, 54, false, str_repeat('箱', 256));
foreach([
	static fn() => new B\BlockEntityComponent(false, null, false, 'title'),
	static fn() => new B\BlockEntityComponent(false, 9, false, ''),
	static fn() => new B\BlockEntityComponent(false, 9, false, str_repeat('箱',257)),
	static fn() => new B\BlockEntityComponent(false, 9, false, "\xFF")
] as $invalid){ rejects2660($invalid, 'accepted invalid container title'); }
$geometry = NBT::getTagType((new B\GeometryComponent(boneVisibility: ['visible'=>true, 'hidden'=>false, 'dynamic'=>"q.block_state('test:visible')"]))->getValue());
check($geometry->getCompoundTag('bone_visibility')->getTag('hidden') instanceof ByteTag && $geometry->getCompoundTag('bone_visibility')->getTag('dynamic') instanceof StringTag, 'boolean and Molang bone visibility must retain their tag types');

$durability = new I\DurabilitySensorComponent([['durability'=>10, 'sound_event'=>'showcase.chime']]);
check($durability->getValue()['durability_thresholds'][0]['sound_event'] === 'showcase.chime', 'durability sensor must accept custom sound names');
$durability->addDurabilityThreshold(0, soundEvent: SoundEvent::BREAK);
check($durability->getValue()['durability_thresholds'][1]['sound_event'] === SoundEvent::BREAK->value, 'durability sensor retains enum support');
$swing = new I\SwingSoundsComponent('showcase.chime', SoundEvent::ATTACK_STRONG, 'showcase.chime');
check($swing->getValue()['attack_critical_hit'] === 'showcase.chime' && $swing->getValue()['attack_hit'] === SoundEvent::ATTACK_STRONG->value, 'swing sounds accept custom names and enums');
$use = new I\UseModifiersComponent(startSound: 'showcase.chime');
check($use->getValue()['start_sound'] === 'showcase.chime', 'use modifiers preserve custom sound names');

$updateType = pocketmine\block\BlockTypeIds::newId();
$factory->registerBlock(static function() use($updateType, $collision): TraitBlock {
	$b = new TraitBlock(new BlockIdentifier($updateType), '26.60 fixture', new BlockTypeInfo(BlockBreakInfo::instant()));
	$b->addComponent($collision);
	$b->addComponent(new B\BlockEntityComponent(true, 9, true, 'container.test.title'));
	$b->addPermutation(new customiesdevs\customies\block\permutations\BlockPermutation('true', new B\CollisionBoxComponent(false)));
	return $b;
}, 'test:update2660');
$published = array_values(array_filter($factory->getBlockPaletteEntries(), static fn($entry) => $entry->getName() === 'test:update2660'))[0]->getStates()->getRoot();
check(count($published->getCompoundTag('components')->getCompoundTag('minecraft:collision_box')->getListTag('boxes')) === 3, 'factory must publish every collision box');
check(count($published->getListTag('permutations')->getValue()[0]->getCompoundTag('components')->getCompoundTag('minecraft:collision_box')->getListTag('boxes')) === 0, 'disabled permutation must keep an empty list');
echo 'PASS: 26.60 commands removed, canonical collision NBT, min/max mirroring, fractional bounds, block-entity fields, bone visibility and custom item sounds.', PHP_EOL;
