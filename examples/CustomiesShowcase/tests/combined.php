<?php
declare(strict_types=1);
use customiesdevs\customies\block\CustomiesBlockFactory as BF;
use customiesdevs\showcase\StairShapes;
use pocketmine\math\{Vector3,Facing};
use pocketmine\item\{Item,VanillaItems,ItemUseResult};

$dirs = ['north'=>Facing::NORTH,'east'=>Facing::EAST,'south'=>Facing::SOUTH,'west'=>Facing::WEST];
$names = array_keys($dirs); $origin = new Vector3(30,64,30);
$stair = static function(string $direction,string $half){
	$b = BF::getInstance()->get('showcase:corner_stairs');
	$b->getState('minecraft:cardinal_direction')->setCurrentValue($direction);
	$b->getState('minecraft:vertical_half')->setCurrentValue($half);
	return $b;
};
foreach(['bottom','top'] as $half){
	foreach($names as $index=>$direction){
		foreach(StairShapes::SHAPES as $shape){
			$world = new ShowcaseBreakWorld();
			$world->setBlock($origin,$stair($direction,$half));
			if($shape !== 'none'){
				$neighborAt = $origin->getSide(str_starts_with($shape,'outer') ? $dirs[$direction] : Facing::opposite($dirs[$direction]));
				$turn = str_ends_with($shape,'left') ? 3 : 1;
				$world->setBlock($neighborAt,$stair($names[($index+$turn)%4],$half));
			}
			$world->getBlock($origin)->onNearbyBlockChange();
			$placed = $world->getBlock($origin);
			check($placed->getState('minecraft:corner')->getCurrentValue() === $shape,'Corner shape: '.$half.' '.$direction.' '.$shape);
			$volume = 0;
			foreach($placed->getCollisionBoxes() as $b){ $volume += ($b->maxX-$b->minX)*($b->maxY-$b->minY)*($b->maxZ-$b->minZ); }
			$expected = str_starts_with($shape,'inner') ? 0.875 : (str_starts_with($shape,'outer') ? 0.625 : 0.75);
			check(abs($volume-$expected)<0.00001,'Corner collision volume');
			if($shape !== 'none'){
				$world->setBlock($neighborAt,$stair($names[($index+$turn)%4],$half === 'top' ? 'bottom' : 'top'));
				$world->getBlock($origin)->onNearbyBlockChange();
				check($world->getBlock($origin)->getState('minecraft:corner')->getCurrentValue() === 'none','Opposite vertical halves must not join');
				$world->setBlock($neighborAt,pocketmine\block\VanillaBlocks::AIR());
				$world->getBlock($origin)->onNearbyBlockChange();
				check($world->getBlock($origin)->getState('minecraft:corner')->getCurrentValue() === 'none','Removing a neighbor must straighten stairs');
			}
		}
	}
}
$legacy = pocketmine\data\bedrock\block\BlockStateData::current('showcase:corner_stairs',[
	'minecraft:cardinal_direction'=>new pocketmine\nbt\tag\StringTag('east'),
	'minecraft:corner'=>new pocketmine\nbt\tag\StringTag('inner_right')
]);
$legacyId = pocketmine\world\format\io\GlobalBlockStateHandlers::getDeserializer()->deserialize($legacy);
$legacyBlock = pocketmine\block\RuntimeBlockStateRegistry::getInstance()->fromStateId($legacyId);
check($legacyBlock->getState('minecraft:vertical_half')->getCurrentValue() === 'bottom','Old saved stairs default to bottom');
$enabled = $stair('north','bottom')->getTrait('minecraft:placement_direction')->toNBT()->getCompoundTag('enabled_states');
check($enabled->getByte('minecraft:cardinal_direction') === 0 && $enabled->getByte('minecraft:corner_and_cardinal_direction') === 1,'Corner selector must not enable a conflicting cardinal selector');

class ShowcaseEffects extends pocketmine\entity\effect\EffectManager {
	public array $recorded = [];
	public function __construct(){}
	public function add(pocketmine\entity\effect\EffectInstance $effect): bool { $this->recorded[] = $effect; return true; }
}
class ShowcasePlayer extends pocketmine\player\Player {
	public ShowcaseEffects $effects;
	private pocketmine\inventory\PlayerOffHandInventory $offhand;
	public ?pocketmine\inventory\Inventory $opened = null;
	public bool $fallReset = false;
	public bool $sneak = false;
	public function __construct(){ $this->effects = new ShowcaseEffects(); $this->offhand = new pocketmine\inventory\PlayerOffHandInventory($this); }
	public function __destruct(){}
	public function getEffects(): pocketmine\entity\effect\EffectManager { return $this->effects; }
	public function getOffHandInventory(): pocketmine\inventory\PlayerOffHandInventory { return $this->offhand; }
	public function setCurrentWindow(pocketmine\inventory\Inventory $inventory): bool { $this->opened = $inventory; return true; }
	public function sendActionBarMessage(string $message): void {}
	public function getMotion(): Vector3 { return new Vector3(0,-1.25,0); }
	public function resetFallDistance(): void { $this->fallReset = true; }
	public function isSneaking(): bool { return $this->sneak; }
}
$player = new ShowcasePlayer(); $returned = [];
$world = new ShowcaseBreakWorld(); $air = VanillaItems::AIR();
$world->setBlock($origin,BF::getInstance()->get('showcase:signal_lamp'));
foreach([5,10,15,0] as $light){
	$world->getBlock($origin)->onInteract($air,1,Vector3::zero());
	check($world->getBlock($origin)->getLightLevel() === $light,'Dimmable lamp must cycle 0/5/10/15');
}
$world->setBlock($origin,BF::getInstance()->get('showcase:phase_gate'));
check($world->getBlock($origin)->getCollisionBoxes() !== [],'Closed gate must collide');
$world->getBlock($origin)->onInteract($air,1,Vector3::zero());
check($world->getBlock($origin)->getCollisionBoxes() === [] && $world->getBlock($origin)->getLightLevel() === 8,'Open gate must remove collision and light up');
$world->getBlock($origin)->onInteract($air,1,Vector3::zero());
check($world->getBlock($origin)->getCollisionBoxes() !== [],'Gate must close again');
$world->setBlock($origin,BF::getInstance()->get('showcase:spring_pad'));
check($world->getBlock($origin)->onEntityLand($player) === null,'Unarmed spring must not bounce');
$world->getBlock($origin)->onInteract($air,1,Vector3::zero());
check($world->getBlock($origin)->onEntityLand($player) === 1.25 && $player->fallReset,'Armed spring must bounce and reset fall distance');
$player->sneak = true;
check($world->getBlock($origin)->onEntityLand($player) === null,'Sneaking must suppress bounce');
$world->setBlock($origin,BF::getInstance()->get('showcase:prism_workbench'));
$world->getBlock($origin)->onInteract($air,1,Vector3::zero(),$player);
check($player->opened instanceof pocketmine\block\inventory\CraftingTableInventory && $player->opened->getSize() === 9,'Workbench must open a real 3x3 crafting grid');
$world->setBlock($origin,BF::getInstance()->get('showcase:lumen_cable'));
$world->setBlock($origin->getSide(Facing::NORTH),BF::getInstance()->get('showcase:cable'));
$world->getBlock($origin)->onNearbyBlockChange();
check($world->getBlock($origin)->getState('minecraft:connection_north')->getCurrentValue() === true,'Combined cable must connect to original cable');
$world->getBlock($origin)->onInteract($air,1,Vector3::zero());
check($world->getBlock($origin)->getLightLevel() === 15,'Combined cable must toggle light');
$scout = clone $items['showcase:scout_wand'];
check($scout->onClickAir($player,new Vector3(0,0,1),$returned) === ItemUseResult::SUCCESS && count($player->effects->recorded) === 3,'Scout wand must apply three effects');
check($scout->getCooldownTicks() === 100,'Scout cooldown must match metadata');
$pick = clone $items['showcase:prism_pickaxe']; $pick->setDamage(100);
$player->getOffHandInventory()->setItem(0,$pick);
$kit = clone $items['showcase:repair_kit']; $kit->setCount(2);
check($kit->onClickAir($player,new Vector3(0,0,1),$returned) === ItemUseResult::SUCCESS,'Repair kit use');
check($kit->getCount() === 1 && $player->getOffHandInventory()->getItem(0)->getDamage() === 36,'Repair kit consumes one and repairs 64');
$target = $player->getOffHandInventory()->getItem(0);
check($kit->repairTarget($target) && $target->getDamage() === 0,'Repairs must clamp to zero');
check(!$kit->repairTarget($target),'Undamaged targets must not consume a kit');
$blade = clone $items['showcase:frost_blade']; $before = count($player->effects->recorded);
check($blade->onAttackEntity($player,$returned) && $blade->getDamage() === 1 && count($player->effects->recorded) === $before+1,'Frost Blade must apply wear and a slowing effect');
check(!$items['showcase:prism_elixir']->requiresHunger() && $items['showcase:prism_elixir']->getResidue()->getTypeId() === VanillaItems::GLASS_BOTTLE()->getTypeId(),'Elixir must work at full hunger and return a bottle');
check($items['showcase:energized_ration']->getFoodRestore() === 10 && count($items['showcase:energized_ration']->getAdditionalEffects()) === 1,'Ration must restore food and grant speed');
// Export the server's exact north-facing collision shapes for independent asset comparison.
$shapes = [];
foreach(['bottom','top'] as $half){ foreach(StairShapes::SHAPES as $shape){ $shapes[$shape.'_'.$half] = array_map(static fn($b) => [$b->minX,$b->minY,$b->minZ,$b->maxX,$b->maxY,$b->maxZ],StairShapes::boxes($shape,$half)); } }
file_put_contents(dirname(__DIR__).'/stair-shapes.json',json_encode($shapes,JSON_PRETTY_PRINT)."\n");
// Compare processed client collision with PHP shapes, independent of the geometry X mirror.
$wireShapes = 0;
foreach(BF::getInstance()->getBlockPaletteEntries() as $entry){
	if($entry->getName() !== 'showcase:corner_stairs'){ continue; }
	foreach($entry->getStates()->getRoot()->getListTag('permutations') as $permutation){
		$collision = $permutation->getCompoundTag('components')?->getCompoundTag('minecraft:collision_box');
		if($collision === null){ continue; }
		preg_match("/minecraft:corner'\\) == '([^']+)'.*minecraft:vertical_half'\\) == '([^']+)'/", $permutation->getString('condition'), $matched);
		check(count($matched) === 3, 'Stair wire fixture must identify its corner and half');
		$serverBoxes = StairShapes::boxes($matched[1], $matched[2]);
		$clientBoxes = $collision->getListTag('boxes')->getValue();
		check(count($clientBoxes) === count($serverBoxes), 'Client must retain every stair collision box');
		foreach($serverBoxes as $i=>$serverBox){
			foreach(['minX','minY','minZ','maxX','maxY','maxZ'] as $coordinate){
				check(abs($clientBoxes[$i]->getFloat($coordinate) / 16 - $serverBox->$coordinate) < 0.000001, 'Stair client/server collision mismatch after voxel conversion');
			}
		}
		++$wireShapes;
	}
}
check($wireShapes === 10, 'All stair corner/half wire definitions must be verified');
echo "PASS: 40 corner/half/direction cases, legacy stairs, ten client/server wire shapes, interactions, crafting, repair, effects, blade and food.\n";
