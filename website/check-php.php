<?php
declare(strict_types=1);
// Usage: php website/check-php.php /path/to/axolotl website/preview/tool-starters.json
ob_start();
try{ require dirname(__DIR__).'/examples/CustomiesShowcase/tests/verify.php'; }
finally{ ob_end_clean(); }
$fixtures=json_decode(file_get_contents($argv[2] ?? ''),true,512,JSON_THROW_ON_ERROR);
foreach($fixtures as $fixture){
	$kind=$fixture['kind'];$class=$kind==='item' ? 'StarterItem' : 'StarterBlock';
	foreach(['src/'.$class.'.php','src/Main.php'] as $path){ eval(preg_replace('/^<\?php\s*/','',$fixture['files'][$path])); }
	if($kind==='item'){
		$id=\pocketmine\item\ItemTypeIds::newId();
		\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->registerItem(static fn()=>new \docsitem\StarterItem($id),'documentation:item');
		$item=\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->get('documentation:item');
		check($item->getMaxStackSize()===16,'Generated item stack limit');
	}else{
		$id=\pocketmine\block\BlockTypeIds::newId();
		\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->registerBlock(static fn()=>new \docsblock\StarterBlock($id),'documentation:block');
		$block=\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->get('documentation:block');
		check($block->getComponent('minecraft:geometry')!==null,'Generated block geometry registration');
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
echo "PASS: both generated plugin classes/register paths and charged-item tutorial, invalid data, clone ownership and saved NBT.\n";
