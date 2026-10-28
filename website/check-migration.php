<?php
declare(strict_types=1);
ob_start();
try { require dirname(__DIR__).'/examples/CustomiesShowcase/tests/verify.php'; }
finally { ob_end_clean(); }

class MigrationTestPlayer extends ShowcasePlayer {
	public array $messages=[];
	public function sendMessage(\pocketmine\lang\Translatable|string $message): void { $this->messages[]=(string)$message; }
}
class MigrationTestPlugin extends \pocketmine\plugin\PluginBase {}
$fixtures=json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
foreach($fixtures as $fixture){
	$ns='migrated'.strtolower($fixture['pluginName']);
	foreach($fixture['files'] as $path=>$source){ if(str_ends_with($path,'.php')){ eval(preg_replace('/^<\?php\s*/','',$source)); } }
	foreach($fixture['report']['definitions'] as $i=>$definition){
		$class=$ns.'\\'.$definition['className'];
		if($definition['kind']==='item'){
			$id=\pocketmine\item\ItemTypeIds::newId();\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->registerItem(static fn()=>new $class($id),$definition['identifier']);
		}else{
			$id=\pocketmine\block\BlockTypeIds::newId();\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->registerBlock(static fn()=>new $class($id),$definition['identifier']);
		}
	}
	$plugin=(new ReflectionClass(MigrationTestPlugin::class))->newInstanceWithoutConstructor();
	$class=$ns.'\\MigrationHooks';$listener=new $class($plugin);$player=new MigrationTestPlayer();
	if($fixture['pluginName']==='MigrationTest'){
		$item=\customiesdevs\customies\item\CustomiesItemFactory::getInstance()->get('migration:wand');
		$use=new \pocketmine\event\player\PlayerItemUseEvent($player,$item,\pocketmine\math\Vector3::zero());
		$consume=new \pocketmine\event\player\PlayerItemConsumeEvent($player,$item);
		foreach((new ReflectionClass($listener))->getMethods(ReflectionMethod::IS_PUBLIC) as $method){
			if(!str_starts_with($method->getName(),'handle')){ continue; }
			$type=$method->getParameters()[0]->getType()->getName();$listener->{$method->getName()}($type===\pocketmine\event\player\PlayerItemConsumeEvent::class?$consume:$use);
		}
		check(count($player->effects->recorded)===2,'Translated subscriptions and bound item callbacks execute real effects');
		check(in_array('Wand activated',$player->messages,true)&&in_array('Consumed',$player->messages,true),'Translated messages execute');
		$wrong=new \pocketmine\event\player\PlayerItemUseEvent($player,\pocketmine\block\VanillaBlocks::DIRT()->asItem(),\pocketmine\math\Vector3::zero());
		foreach((new ReflectionClass($listener))->getMethods(ReflectionMethod::IS_PUBLIC) as $method){ if(str_starts_with($method->getName(),'handle')&&$method->getParameters()[0]->getType()->getName()===\pocketmine\event\player\PlayerItemUseEvent::class){$listener->{$method->getName()}($wrong);} }
		check(count($player->effects->recorded)===2,'Unrelated items do not receive imported abilities');
	}elseif($fixture['pluginName']==='VariantMigration'){
		foreach([\pocketmine\block\utils\DyeColor::RED,\pocketmine\block\utils\DyeColor::BLUE] as $color){
			$wool=\pocketmine\block\VanillaBlocks::WOOL()->setColor($color)->asItem();$listener->handle0(new \pocketmine\event\player\PlayerItemUseEvent($player,$wool,\pocketmine\math\Vector3::zero()));
		}
		check($player->messages===['Red'],'Shared PocketMine type IDs do not conflate colored Bedrock identifiers');
		foreach([\pocketmine\item\PotionType::WATER,\pocketmine\item\PotionType::HEALING] as $type){$potion=\pocketmine\item\VanillaItems::POTION()->setType($type);$listener->handle0(new \pocketmine\event\player\PlayerItemUseEvent($player,$potion,\pocketmine\math\Vector3::zero()));}
		check($player->messages===['Red','Potion','Potion'],'Potion state variants retain one Bedrock item identifier');
	}elseif($fixture['pluginName']==='BlockMigration'){
		$block=\customiesdevs\customies\block\CustomiesBlockFactory::getInstance()->get('migration:altar');
		$event=new \pocketmine\event\player\PlayerInteractEvent($player,\pocketmine\item\VanillaItems::STICK(),$block,null,\pocketmine\math\Facing::UP);
		$listener->handle0($event);check(count($player->effects->recorded)===1,'Bound block interaction executes');
		$left=new \pocketmine\event\player\PlayerInteractEvent($player,\pocketmine\item\VanillaItems::STICK(),$block,null,\pocketmine\math\Facing::UP,\pocketmine\event\player\PlayerInteractEvent::LEFT_CLICK_BLOCK);
		$listener->handle0($left);check(count($player->effects->recorded)===1,'Left click cannot activate a right-click component');
	}else{
		$event=new \pocketmine\event\player\PlayerItemUseEvent($player,\pocketmine\item\VanillaItems::STICK(),\pocketmine\math\Vector3::zero());$listener->handle0($event);
		check($player->messages===["'); phpinfo(); //"],'Executable-looking text stays literal in generated PHP');
	}
}
echo "PASS: generated migration classes, registration, live PocketMine events, custom binding filters, effects, messages and PHP escaping.\n";
