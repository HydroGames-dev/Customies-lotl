<?php
declare(strict_types=1);
require __DIR__ . '/verify.php';

use customiesdevs\customies\block\SlabPlacement;
use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\showcase\StairShapes;
use pocketmine\math\{Vector3, AxisAlignedBB, Facing};
use pocketmine\block\VanillaBlocks;
use pocketmine\block\utils\StairShape;

class ShapeWorld extends ExpansionWorld {
	public function getCollidingEntities(AxisAlignedBB $bb, ?pocketmine\entity\Entity $entity = null): array { return []; }
	public function getTileAt(int $x,int $y,int $z): ?pocketmine\block\tile\Tile { return null; }
}
$factory = CustomiesBlockFactory::getInstance();
foreach(['bottom','top'] as $first){
	$world = new ShapeWorld(); $at = new Vector3(0,64,0);
	$foundation = $at->add(0,$first === 'bottom' ? -1 : 1,0);
	$world->setBlock($foundation, VanillaBlocks::STONE());
	$item = $factory->get('showcase:slab')->asItem()->setCount(5);
	$face = $first === 'bottom' ? Facing::UP : Facing::DOWN;
	$click = new Vector3(.5,$first === 'bottom' ? 1 : 0,.5);
	check($world->useItemOn($foundation,$item,$face,$click,playSound:false), 'Place first slab through actual World item-placement path');
	check($world->getBlock($at)->getState('minecraft:vertical_half')->getCurrentValue() === $first, 'First slab half follows clicked face');
	check($world->useItemOn($at,$item,$face,new Vector3(.5,.5,.5),playSound:false), 'Second slab must combine in either order');
	$combined = $world->getBlock($at);
	check(SlabPlacement::isDouble($combined) && $item->getCount() === 3, 'Combining consumes exactly two slab items');
	check(count($combined->getCollisionBoxes()) === 1 && $combined->isSolid(), 'Double slab must be a solid full cube');
	$handlers=pocketmine\world\format\io\GlobalBlockStateHandlers::class;
	$saved=$handlers::getSerializer()->serialize($combined->getStateId());
	$restored=pocketmine\block\RuntimeBlockStateRegistry::getInstance()->fromStateId($handlers::getDeserializer()->deserialize($saved));
	check(SlabPlacement::isDouble($restored), 'Double slab survives saved block-state round trip');
	check($world->getBlock($at->getSide($face)) instanceof pocketmine\block\Air, 'Second half must not occupy a separate block');
	$tool = pocketmine\item\VanillaItems::AIR();
	check($world->useBreakOn($at,$tool), 'Double slab must break through World');
	check(count($world->drops) === 1 && $world->drops[0]->getCount() === 2, 'Double slab returns two single slab items');
}
// Match the actual host's vanilla slab acceptance for every face, half and click height.
foreach(['bottom','top'] as $half){ foreach([.25,.5,.75] as $height){ foreach([false,true] as $clicked){ foreach(Facing::ALL as $face){
	$custom=$factory->get('showcase:slab');$custom->getState('minecraft:vertical_half')->setCurrentValue($half);
	$vanilla=VanillaBlocks::OAK_SLAB()->setSlabType($half==='top' ? pocketmine\block\utils\SlabType::TOP : pocketmine\block\utils\SlabType::BOTTOM);
	$click=new Vector3(.5,$height,.5);
	check($custom->canBePlacedAt($custom,$click,$face,$clicked)===$vanilla->canBePlacedAt($vanilla,$click,$face,$clicked),'Slab merge acceptance matches vanilla');
	check($custom->getSupportType($face)===$vanilla->getSupportType($face),'Slab face support matches vanilla');
} } } }
$world=new ShapeWorld();$at=new Vector3(0,64,0);$slab=$factory->get('showcase:slab');$slab->getState(SlabPlacement::DOUBLE)->setCurrentValue(true);$world->setBlock($at,$slab);
$item=$factory->get('showcase:slab')->asItem()->setCount(2);
check($world->useItemOn($at,$item,Facing::UP,new Vector3(.5,1,.5),playSound:false),'A third slab places above a double slab');
check(SlabPlacement::isDouble($world->getBlock($at)) && !SlabPlacement::isDouble($world->getBlock($at->up())) && $item->getCount()===1,'Third slab does not overwrite or over-consume the double slab');
check(!SlabPlacement::canMerge($slab,VanillaBlocks::OAK_SLAB(),new Vector3(.5,.75,.5),Facing::UP,true),'Different slab types do not merge');
foreach([Facing::NORTH,Facing::EAST,Facing::SOUTH,Facing::WEST] as $face){
	$world = new ShapeWorld(); $at = new Vector3(0,64,0);
	$world->setBlock($at,$factory->get('showcase:slab'));
	$item = $factory->get('showcase:slab')->asItem()->setCount(2);
	check($world->useItemOn($at,$item,$face,new Vector3(.5,.75,.5),playSound:false) && SlabPlacement::isDouble($world->getBlock($at)), 'Side-click empty half must merge');
}
$legacy = pocketmine\data\bedrock\block\BlockStateData::current('showcase:slab', ['minecraft:vertical_half'=>new pocketmine\nbt\tag\StringTag('top')]);
$legacyRuntime = pocketmine\world\format\io\GlobalBlockStateHandlers::getDeserializer()->deserialize($legacy);
check(!SlabPlacement::isDouble(pocketmine\block\RuntimeBlockStateRegistry::getInstance()->fromStateId($legacyRuntime)), 'Older saved slabs remain single slabs');

function shapeBounds(array $boxes): array { return array_map(static fn($b) => [$b->minX,$b->minY,$b->minZ,$b->maxX,$b->maxY,$b->maxZ],$boxes); }
function occupancy(array $bounds): array {
	$result=[];
	foreach([.25,.75] as $x){ foreach([.25,.75] as $y){ foreach([.25,.75] as $z){
		$inside=false; foreach($bounds as [$a,$b,$c,$d,$e,$f]){ if($x>$a && $x<$d && $y>$b && $y<$e && $z>$c && $z<$f){ $inside=true; break; } }
		$result[]=$inside;
	} } }
	return $result;
}
$fixtures=[]; $models=dirname(__DIR__).'/resource_pack/models/blocks/';
$shapeEnums=['none'=>StairShape::STRAIGHT,'outer_left'=>StairShape::OUTER_LEFT,'outer_right'=>StairShape::OUTER_RIGHT,'inner_left'=>StairShape::INNER_LEFT,'inner_right'=>StairShape::INNER_RIGHT];
foreach(['bottom','top'] as $half){ foreach(['north'=>Facing::NORTH,'west'=>Facing::WEST,'south'=>Facing::SOUTH,'east'=>Facing::EAST] as $turns=>$face){
	foreach(StairShapes::SHAPES as $shape){
		$server=shapeBounds(StairShapes::boxes($shape,$half,$turns));
		$vanilla=VanillaBlocks::OAK_STAIRS()->setFacing($face)->setUpsideDown($half==='top')->setShape($shapeEnums[$shape]);
		$native=shapeBounds($vanilla->getCollisionBoxes());
		check(occupancy($server)===occupancy($native), "Custom stairs must match vanilla: $shape/$half/$turns");
		$path=$models.'stairs_'.$shape.'_'.$half.'.geo.json';
		$data=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR); $client=[];
		foreach($data['minecraft:geometry'][0]['bones'] as $bone){ foreach($bone['cubes'] ?? [] as $cube){
			[$x,$y,$z]=$cube['origin']; [$w,$h,$d]=$cube['size'];
			[$x,$y,$z,$xx,$yy,$zz]=[(8-$x-$w)/16,$y/16,($z+8)/16,(8-$x)/16,($y+$h)/16,($z+$d+8)/16];
			for($i=0;$i<array_search($turns,['north','west','south','east'],true);++$i){ [$x,$z,$xx,$zz]=[$z,1-$xx,$zz,1-$x]; }
			$client[]=[$x,$y,$z,$xx,$yy,$zz];
		} }
		check(occupancy($client)===occupancy($server), "Actual geometry/rotation must match server: $shape/$half/$turns");
		$fixtures[]=['shape'=>$shape,'half'=>$half,'direction'=>$turns,'client'=>$client,'server'=>$server,'vanilla'=>$native];
	}
} }
file_put_contents(dirname(__DIR__,3).'/website/shape-fixtures.json',json_encode($fixtures,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo "PASS: slab merges in both orders and four side directions, consumption, full collision, two drops, legacy saves; 40 actual stair meshes/rotations match vanilla.\n";
