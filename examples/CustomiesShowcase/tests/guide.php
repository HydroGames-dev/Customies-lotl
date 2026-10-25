<?php
declare(strict_types=1);
require __DIR__.'/verify.php';
$guide = file_get_contents(dirname(__DIR__,3).'/docs/customies-guide.md');
preg_match_all('/```php\R(.*?)```/s',$guide,$snippets);
check(count($snippets[1])===4,'Guide needs both tutorial classes and registrations');
foreach($snippets[1] as $snippet){ eval(preg_replace('/^<\?php\s*/','',$snippet)); }
$lamp = customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->get('tutorial:lamp');
$world = new ExpansionWorld();$at = new pocketmine\math\Vector3(0,70,0);
$world->setBlock($at,$lamp);
check($world->getBlock($at)->getLightLevel()===0,'Tutorial lamp starts off');
$world->getBlock($at)->onInteract(pocketmine\item\VanillaItems::AIR(),1,pocketmine\math\Vector3::zero());
check($world->getBlock($at)->getLightLevel()===15,'Tutorial lamp toggles server light');
$wand = customiesdevs\customies\item\CustomiesItemFactory::getInstance()->get('tutorial:wand');
check($wand->getMaxStackSize()===1 && $wand->getCooldownTicks()===40,'Tutorial wand matches server limits');
$player = new ShowcasePlayer();$returned=[];
$wand->onClickAir($player,new pocketmine\math\Vector3(1,0,0),$returned);
check(count($player->effects->recorded)===1 && $player->effects->recorded[0]->getDuration()===100,'Tutorial wand applies a real five-second effect');
foreach(['blocks','items'] as $method){
	foreach(customiesdevs\showcase\ComponentCatalog::$method() as $key=>$_){ check(str_contains($guide,'| `'.$key.'` |'),'Guide component coverage: '.$key); }
}
echo "PASS: all guide PHP examples load/register and execute; all 75 components documented.\n";
