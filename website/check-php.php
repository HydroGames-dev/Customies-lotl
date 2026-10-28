<?php
declare(strict_types=1);
// Usage: php website/check-php.php /path/to/axolotl .compat/authoring.json
ob_start();
try{ require dirname(__DIR__).'/examples/CustomiesShowcase/tests/verify.php'; }
finally{ ob_end_clean(); }
$fixtures=json_decode(file_get_contents($argv[2] ?? ''),true,512,JSON_THROW_ON_ERROR);
foreach($fixtures as $fixture){
	$kind=$fixture['kind'];$class=$fixture['report']['class'];
	foreach(['src/'.$fixture['className'].'.php','src/ContentPlugin.php'] as $path){ eval(preg_replace('/^<\?php\s*/','',$fixture['files'][$path])); }
	if($kind==='item'){
		$id=\pocketmine\item\ItemTypeIds::newId();
		\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->registerItem(static fn()=>new $class($id),$fixture['identifier']);
		$item=\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->get($fixture['identifier']);
		check($item->getMaxStackSize()===(in_array($fixture['preset'],['durable','armor'],true)?1:16),'Generated item stack limit');
		if($fixture['preset']==='food'){ check($item instanceof \pocketmine\item\Food && $item->getFoodRestore()===6 && abs($item->getSaturationRestore()-7.2)<.001,'Generated food has server nutrition'); }
		if($fixture['preset']==='durable'){ check($item->getMaxDurability()===256 && $item->getAttackPoints()===5,'Generated durable has actual durability and damage'); }
		if($fixture['preset']==='armor'){ check($item instanceof \pocketmine\item\Armor && $item->getDefensePoints()===2 && $item->getMaxDurability()===256,'Generated armor slot/defense/durability'); }
	}else{
		$id=\pocketmine\block\BlockTypeIds::newId();
		\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->registerBlock(static fn()=>new $class($id),$fixture['identifier']);
		$block=\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->get($fixture['identifier']);
		check($block->getComponent('minecraft:geometry')!==null,'Generated block geometry registration');
		if($fixture['preset']==='converted'){
			check($block->getLightLevel()===0 && $block->getCollisionBoxes()[0]->maxY===.5,'Converted block base physics');
			$block->getState('documentation:lit')->setCurrentValue(true);
			$world=new ExpansionWorld();$at=new \pocketmine\math\Vector3(0,64,0);$world->setBlock($at,$block);$block=$world->getBlock($at);
			check($block->getLightLevel()===15 && $block->getCollisionBoxes()===[],'Converted block permutation applies server light and collision');
			check($block->calculateIntercept(new \pocketmine\math\Vector3(-1,.25,.25),new \pocketmine\math\Vector3(2,.25,.25))===null,'Converted false selection is not targetable');
		}
		if($fixture['preset']==='slab'){
			$world=new ExpansionWorld();$at=new \pocketmine\math\Vector3(0,64,0);$world->setBlock($at,$block);
			$second=clone $block;$tx=new \pocketmine\world\BlockTransaction($world);
			check($second->canBePlacedAt($world->getBlock($at),new \pocketmine\math\Vector3(.5,.5,.5),\pocketmine\math\Facing::UP,true),'Generated slab can merge');
			check($second->place($tx,$block->asItem(),$world->getBlock($at),$world->getBlock($at),\pocketmine\math\Facing::UP,new \pocketmine\math\Vector3(.5,.5,.5)) && $tx->apply(),'Generated slab transaction applies');
			$double=$world->getBlock($at);check(\customiesdevs\customies\block\SlabPlacement::isDouble($double) && $double->getDropsForCompatibleTool(\pocketmine\item\VanillaItems::AIR())[0]->getCount()===2,'Generated slab merges and drops two items');
		}
	}
}
$source=file_get_contents(__DIR__.'/articles/tutorial-data.md');
preg_match_all('/```php\R(.*?)```/s',$source,$snippets);
check(count($snippets[1])===2,'Charged-item tutorial snippet count');
$constructor='public function __construct(int $id){ parent::__construct(new \pocketmine\item\ItemIdentifier($id),"Charged Item");'.$snippets[1][0].'}';
eval('final class DocumentationChargedItem extends \pocketmine\item\Item {'.$constructor.$snippets[1][1].'}');
$id=\pocketmine\item\ItemTypeIds::newId();
\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->registerItem(static fn()=>new DocumentationChargedItem($id),'documentation:charged');
$item=\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->get('documentation:charged');
$player=new ShowcasePlayer();$returned=[];
check($item->getNamedTag()->getInt('mycontent:charges')===5,'Initial saved charges');
check($item->onClickAir($player,new \pocketmine\math\Vector3(1,0,0),$returned)===\pocketmine\item\ItemUseResult::SUCCESS,'Charged use succeeds');
check($item->getNamedTag()->getInt('mycontent:charges')===4 && count($player->effects->recorded)===1,'One charge and one effect');
$restored=\pocketmine\item\Item::nbtDeserialize($item->nbtSerialize());
check($restored->getNamedTag()->getInt('mycontent:charges')===4,'Charge NBT round trip');
foreach([0,-1,101] as $bad){
	$copy=clone $item;$copy->getNamedTag()->setInt('mycontent:charges',$bad);
	check($copy->onClickAir($player,\pocketmine\math\Vector3::zero(),$returned)===\pocketmine\item\ItemUseResult::FAIL,'Invalid charge rejected');
}
$copy=clone $item;$copy->getNamedTag()->setString('mycontent:charges','5');
check($copy->onClickAir($player,\pocketmine\math\Vector3::zero(),$returned)===\pocketmine\item\ItemUseResult::FAIL,'Wrong tag type rejected');
$copy=clone $item;$copy->getNamedTag()->setInt('mycontent:schema',2);
check($copy->onClickAir($player,\pocketmine\math\Vector3::zero(),$returned)===\pocketmine\item\ItemUseResult::FAIL,'Future schema rejected');
check($item->getNamedTag()->getInt('mycontent:schema')===1 && count($player->effects->recorded)===1,'Clones stay independent and failed uses grant nothing');
echo 'PASS: '.count($fixtures)." generated content classes, registration, authoritative item/block behavior and merging slabs; charged-item tutorial and saved NBT.\n";
