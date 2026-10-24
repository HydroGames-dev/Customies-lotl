<?php
declare(strict_types=1);
$axolotl = $argv[1] ?? getenv('AXOLOTL_ROOT');
if(!$axolotl || !is_file($axolotl.'/vendor/autoload.php')){ fwrite(STDERR,"Usage: php tests/verify.php /path/to/axolotl-checkout\n"); exit(1); }
require $axolotl.'/vendor/autoload.php';
$repository = dirname(__DIR__,3);
spl_autoload_register(static function(string $class) use($repository): void {
	foreach(['customiesdevs\\customies\\'=>$repository.'/src/', 'customiesdevs\\showcase\\'=>dirname(__DIR__).'/src/'] as $prefix=>$base){
		if(str_starts_with($class,$prefix)){ require $base.str_replace('\\','/',substr($class,strlen($prefix))).'.php'; return; }
	}
});
function check(bool $condition, string $message): void { if(!$condition){ throw new RuntimeException($message); } }
use customiesdevs\showcase\{ComponentCatalog, ShowcaseBlock, ShowcaseItem, PrismFood, PrismHelmet, PrismPebble, PrismPickaxe};
use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\ItemTypeIds;

check(class_exists(customiesdevs\showcase\ShowcasePlugin::class),'Plugin entry point must load');
$coverage = [];
foreach(['block'=>'blocks','item'=>'items'] as $kind=>$method){
	$components = ComponentCatalog::$method();
	$covered = array_map(static fn($c) => get_class($c),$components);
	foreach(glob($repository.'/src/'.$kind.'/component/*Component.php') as $path){
		$class = 'customiesdevs\\customies\\'.$kind.'\\component\\'.basename($path,'.php');
		if((new ReflectionClass($class))->isInterface()){ continue; }
		check(in_array($class,$covered,true),'Missing component specimen: '.$class);
	}
	foreach($components as $id=>$component){
		check(customiesdevs\customies\util\NBT::getTagType($component->getValue()) !== null,'Invalid NBT: '.$id);
		$coverage[$kind][$id] = $component->getName();
	}
}
foreach([...ShowcaseBlock::PLAYABLE, ...array_map(static fn($key) => 'lab_'.$key,array_keys(ComponentCatalog::blocks()))] as $specimen){
	$id = BlockTypeIds::newId();
	$identifier = 'showcase:'.(str_starts_with($specimen,'lab_') ? 'lab_block_'.substr($specimen,4) : $specimen);
	CustomiesBlockFactory::getInstance()->registerBlock(static fn() => new ShowcaseBlock($id,$specimen),$identifier);
	$block = CustomiesBlockFactory::getInstance()->get($identifier);
	$permutations = iterator_to_array($block->generateStatePermutations());
	$expected = array_product(array_map(static fn($state) => count($state->getValues()),$block->getStates()));
	check(count($permutations) === $expected,'Runtime state enumeration: '.$identifier);
	foreach($permutations as $permutation){
		$serialized = pocketmine\world\format\io\GlobalBlockStateHandlers::getSerializer()->serialize($permutation->getStateId());
		$decoded = pocketmine\world\format\io\GlobalBlockStateHandlers::getDeserializer()->deserialize($serialized);
		check($decoded === $permutation->getStateId(),'State persistence: '.$identifier);
	}
}

// Bedrock's selection-box vectors are FloatTag lists, even when supplied as whole pixels.
foreach(CustomiesBlockFactory::getInstance()->getBlockPaletteEntries() as $entry){
	$root = $entry->getStates()->getRoot();
	$components = [$root->getCompoundTag('components')];
	foreach($root->getListTag('permutations') ?? [] as $permutation){ $components[] = $permutation->getCompoundTag('components'); }
	foreach($components as $componentSet){
		$selection = $componentSet?->getCompoundTag('minecraft:selection_box');
		if($selection === null){ continue; }
		foreach(['origin','size'] as $field){
			$vector = $selection->getListTag($field);
			check($vector !== null && count($vector) === 3,'Selection vector dimensions: '.$entry->getName());
			foreach($vector as $coordinate){ check($coordinate instanceof pocketmine\nbt\tag\FloatTag,'Selection coordinates must be NBT floats: '.$entry->getName().' '.$field); }
		}
	}
}

$items = [];
foreach([...ShowcaseItem::PLAYABLE, ...array_map(static fn($key) => 'lab_'.$key,array_keys(ComponentCatalog::items()))] as $specimen){
	$id = ItemTypeIds::newId();
	$identifier = 'showcase:'.(str_starts_with($specimen,'lab_') ? 'lab_item_'.substr($specimen,4) : $specimen);
	CustomiesItemFactory::getInstance()->registerItem(static fn() => new ShowcaseItem($id,$specimen),$identifier);
	$items[$identifier] = CustomiesItemFactory::getInstance()->get($identifier);
}
foreach(['prism_berry'=>PrismFood::class,'prism_pickaxe'=>PrismPickaxe::class,'prism_helmet'=>PrismHelmet::class,'prism_pebble'=>PrismPebble::class] as $key=>$class){
	$id = ItemTypeIds::newId();
	CustomiesItemFactory::getInstance()->registerItem(static fn() => new $class($id),'showcase:'.$key);
	$items['showcase:'.$key] = CustomiesItemFactory::getInstance()->get('showcase:'.$key);
}
check($items['showcase:prism_berry']->getFoodRestore() === 6,'Food restoration');
check($items['showcase:prism_pickaxe']->getMaxStackSize() === 1,'Tool stack size');
check($items['showcase:prism_helmet']->getDefensePoints() === 2,'Armor defense');
check($items['showcase:ember_fuel']->getFuelTime() === 400,'Fuel ticks');
$lamp = CustomiesBlockFactory::getInstance()->get('showcase:prism_lamp');
$lamp->getState('showcase:powered')->setCurrentValue(true);
check($lamp->getLightLevel() === 15,'Powered lamp server light');
$ghost = CustomiesBlockFactory::getInstance()->get('showcase:phase_block');
$ghost->getState('showcase:phase')->setCurrentValue(2);
check($ghost->getCollisionBoxes() === [],'Ghost server collision');
require __DIR__.'/breaking.php';
file_put_contents(dirname(__DIR__).'/component-coverage.json',json_encode($coverage,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo 'PASS: '.count($coverage['block']).' block components, '.count($coverage['item']).' item components, '.count(CustomiesBlockFactory::getInstance()->getBlockPaletteEntries()).' blocks, '.count($items)." items; every block state round-trips.\n";
