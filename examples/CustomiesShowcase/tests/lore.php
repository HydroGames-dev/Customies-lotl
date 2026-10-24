<?php
declare(strict_types=1);
$specimens = $items;
foreach(customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->getBlockPaletteEntries() as $entry){
	$specimens[$entry->getName()] = customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->get($entry->getName())->asItem();
}
$loreByType = [];
foreach($specimens as $identifier => $item){
	$lore = $item->getLore();
	check(count($lore) >= 3,'Every specimen needs descriptive lore: '.$identifier);
	foreach($lore as $line){ check(trim(pocketmine\utils\TextFormat::clean($line)) !== '','Lore must not contain empty lines: '.$identifier); }
	$loaded = pocketmine\item\Item::nbtDeserialize($item->nbtSerialize());
	check($loaded->getLore() === $lore,'Lore must survive inventory serialization: '.$identifier);
	$loreByType[$item->getTypeId()] = $lore;
}
$found = [];
foreach(pocketmine\inventory\CreativeInventory::getInstance()->getAll() as $item){
	if(isset($loreByType[$item->getTypeId()])){
		check($item->getLore() === $loreByType[$item->getTypeId()],'Creative specimens must include lore');
		$found[$item->getTypeId()] = true;
	}
}
check(count($found) === count($specimens),'Every specimen must have a creative item with lore');
// Exercise the command's per-stack metadata path without starting a server.
$pluginClass = new ReflectionClass(customiesdevs\showcase\ShowcasePlugin::class);
$plugin = $pluginClass->newInstanceWithoutConstructor();
$pluginClass->getProperty('entries')->setValue($plugin,['prism'=>'item']);
$prism = $pluginClass->getMethod('specimen')->invoke($plugin,'prism');
$baseLore = $items['showcase:prism']->getLore();
check(array_slice($prism->getLore(),0,count($baseLore)) === $baseLore,'Prism data must append to its description');
check($prism->getNamedTag()->getInt('showcase:quality') === 3,'Prism per-stack data must be retained');
echo 'PASS: all '.count($specimens)." specimens have descriptive lore in creative inventory and saved stacks; Prism data appends correctly.\n";
