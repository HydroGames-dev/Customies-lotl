<?php
declare(strict_types=1);
$refs = ['geometry'=>[],'block_textures'=>[],'item_textures'=>[]];
foreach(customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->getBlockPaletteEntries() as $entry){
	$root = $entry->getStates()->getRoot();
	$sets = [$root->getCompoundTag('components')];
	foreach($root->getListTag('permutations') ?? [] as $p){ $sets[] = $p->getCompoundTag('components'); }
	foreach($sets as $components){
		$id = $components?->getCompoundTag('minecraft:geometry')?->getString('identifier','');
		if($id !== null && $id !== ''){ $refs['geometry'][$id] = true; }
		foreach($components?->getCompoundTag('minecraft:material_instances')?->getCompoundTag('materials') ?? [] as $material){ $refs['block_textures'][$material->getString('texture')] = true; }
	}
}
foreach($items as $item){
	foreach($item->getComponent('minecraft:icon')->getValue()['textures'] as $texture){ $refs['item_textures'][$texture] = true; }
}
foreach($refs as $key=>$values){ $refs[$key] = array_keys($values); sort($refs[$key]); }
file_put_contents(dirname(__DIR__).'/asset-references.json',json_encode($refs,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
