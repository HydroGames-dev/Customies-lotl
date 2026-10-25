<?php
declare(strict_types=1);
use customiesdevs\showcase\ExpansionBlocks;
use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\math\Vector3;
use pocketmine\world\BlockTransaction;
use pocketmine\item\VanillaItems;

class ExpansionWorld extends ShowcaseBreakWorld {
	public function setBlockAt(int $x,int $y,int $z,pocketmine\block\Block $block,bool $update=true): void { $this->setBlock(new Vector3($x,$y,$z),$block,$update); }
	public function isInWorld(int $x,int $y,int $z): bool { return $y>=-64 && $y<320; }
}
$factory=CustomiesBlockFactory::getInstance();
foreach(ExpansionBlocks::definitions() as $name=>$definition){
	$directions=isset($definition['layout']) || (isset($definition['direction']) && in_array($definition['direction'],['north','south','east','west'],true)) ? ['north','east','south','west'] : [null];
	foreach($directions as $direction){
		$world=new ExpansionWorld();$at=new Vector3(100,70,-100);
		$block=$factory->get('showcase:'.$name);
		if($direction!==null){ $block->getState('minecraft:cardinal_direction')->setCurrentValue($direction); }
		$replace=$world->getBlock($at);$tx=new BlockTransaction($world);
		check($block->place($tx,$block->asItem(),$replace,$replace,1,Vector3::zero()),'Expansion placement: '.$name);
		check(count(iterator_to_array($tx->getBlocks()))===$definition['parts'],'Expansion transaction must contain every part');
		check($tx->apply(),'Expansion transaction must apply');
		$parts=$world->getBlock($at)->getAffectedBlocks();
		check(count($parts)===$definition['parts'],'All expansion parts must be grouped');
		if($definition['mode']==='powered'){
			$last=end($parts);$last->onInteract(VanillaItems::AIR(),1,Vector3::zero());
			foreach($parts as $part){
				$now=$world->getBlock($part->getPosition());
				check($now->getState('showcase:powered')->getCurrentValue()===true,'Every part must toggle together');
				check($now->getLightLevel()===$definition['active_light'],'Every powered part must emit expected light');
			}
		}
		foreach($parts as $part){
			foreach($world->getBlock($part->getPosition())->getCollisionBoxes() as $box){
				$p=$part->getPosition();
				check($box->minX >= $p->x && $box->maxX <= $p->x+1 && $box->minY >= $p->y && $box->maxY <= $p->y+1 && $box->minZ >= $p->z && $box->maxZ <= $p->z+1,'Collision must remain inside each occupied cell');
			}
		}
		// Break the complete structure from EACH possible target part, rebuilding in between.
		foreach(array_keys($parts) as $target){
			foreach($parts as $part){ $world->setBlock($part->getPosition(),$part); }
			$world->drops=[];$tool=VanillaItems::AIR();
			check($world->useBreakOn($parts[$target]->getPosition(),$tool),'Breaking expansion part must succeed');
			foreach($parts as $part){ check($world->getBlock($part->getPosition()) instanceof pocketmine\block\Air,'Breaking must remove all grouped cells'); }
			check(count($world->drops)===1 && $world->drops[0]->getCount()===1,'A structure must drop exactly one item');
		}
		if($definition['parts']>1){
			$world=new ExpansionWorld();$replace=$world->getBlock($at);$trait=$block->getTrait('minecraft:multi_block');
			$offset = isset($definition['layout']) ? customiesdevs\showcase\StructureLayout::offset($definition,$definition['parts']-1,$direction) : $trait->getOffsetForPart($definition['parts']-1,$direction);
			$obstruction=$at->addVector($offset);
			$world->setBlock($obstruction,pocketmine\block\VanillaBlocks::STONE());$tx=new BlockTransaction($world);
			check(!$block->place($tx,$block->asItem(),$replace,$replace,1,Vector3::zero()),'Obstructed multi-block placement must fail');
			check($world->getBlock($at) instanceof pocketmine\block\Air,'Failed placement must not write the origin');
			check($world->getBlock($obstruction) instanceof pocketmine\block\Opaque,'Failed placement must preserve the obstruction');
		}
	}
}
// World bounds are validated for the WHOLE transaction before any cell is written.
$world=new ExpansionWorld();$at=new Vector3(20,319,20);$tower=$factory->get('showcase:sky_obelisk');$replace=$world->getBlock($at);$tx=new BlockTransaction($world);
check($tower->place($tx,$tower->asItem(),$replace,$replace,1,Vector3::zero()) && !$tx->apply(),'Out-of-world tower transaction must be rejected');
check($world->getBlock($at) instanceof pocketmine\block\Air,'Out-of-world failure must be atomic');
$player=new ShowcasePlayer();$world=new ExpansionWorld();$at=new Vector3(10,64,10);
$world->setBlock($at,$factory->get('showcase:runic_pedestal'));
$berry=clone $items['showcase:prism_berry'];$berry->setCount(2);
$world->getBlock($at)->onInteract($berry,1,Vector3::zero(),$player);
check($berry->getCount()===1 && count($player->effects->recorded)===1,'Pedestal must consume one offering and grant a blessing');
$world->setBlock($at,$factory->get('showcase:mini_chess'));
foreach([1,2,0] as $color){ $world->getBlock($at)->onInteract(VanillaItems::AIR(),1,Vector3::zero()); check($world->getBlock($at)->getState('showcase:color')->getCurrentValue()===$color,'Mini chess must cycle colors'); }
$world->setBlock($at,$factory->get('showcase:prism_telescope'));$before=count($player->effects->recorded);
$world->getBlock($at)->onInteract(VanillaItems::AIR(),1,Vector3::zero(),$player);
check(count($player->effects->recorded)===$before+1,'Telescope must grant night vision');
foreach(['prism_chestplate'=>[6,384],'prism_leggings'=>[5,360],'prism_boots'=>[2,300]] as $id=>[$armor,$durability]){
	check($items['showcase:'.$id]->getDefensePoints()===$armor && $items['showcase:'.$id]->getMaxDurability()===$durability,'Armor profile must match server stats');
}
$before=count($player->effects->recorded);$items['showcase:prism_boots']->onTickWorn($player);
check(count($player->effects->recorded)===$before+1,'Worn boots must grant jump boost');
$returned=[];$before=count($player->effects->recorded);
check($items['showcase:clockwork_staff']->onClickAir($player,new Vector3(1,0,0),$returned)===pocketmine\item\ItemUseResult::SUCCESS && count($player->effects->recorded)===$before+1,'Clockwork staff must grant haste');
check($items['showcase:clockwork_staff']->getCooldownTicks()===160 && $items['showcase:wind_fan']->getCooldownTicks()===60,'New cooldowns must match metadata');
check($items['showcase:moon_mochi']->getFoodRestore()===4 && count($items['showcase:moon_mochi']->getAdditionalEffects())===2,'Moon mochi food and effects');
class FanPlayer extends ShowcasePlayer {
	public ?Vector3 $appliedMotion=null;
	public function setMotion(Vector3 $motion): bool { $this->appliedMotion=$motion; return true; }
}
$fanPlayer=new FanPlayer();$items['showcase:wind_fan']->onClickAir($fanPlayer,new Vector3(1,0,0),$returned);
check($fanPlayer->appliedMotion !== null && $fanPlayer->appliedMotion->x===0.9 && $fanPlayer->appliedMotion->y===0.2,'Fan must apply a forward dash with upward lift');
echo "PASS: expansion placement, every-part breaking, directions, bounds/obstructions, grouped toggles, miniatures, offerings, armor, staff, fan and food.\n";

// Large structure abilities consume exactly one cell, only when powered and complete.
foreach(ExpansionBlocks::definitions() as $name=>$d){
    if(!isset($d['layout'])){ continue; }
    check(count($d['layout'])>4,'Structure-sized examples must exceed native chain length');
    $world=new ExpansionWorld();$at=new Vector3(60,70,60);$block=$factory->get('showcase:'.$name);
    $replace=$world->getBlock($at);$tx=new BlockTransaction($world);
    check($block->place($tx,$block->asItem(),$replace,$replace,1,Vector3::zero()) && $tx->apply(),'Ability structure placement');
    $cell=clone $items['showcase:resonance_cell'];$cell->setCount(3);$before=count($player->effects->recorded);
    $block=$world->getBlock($at);$block->onInteract($cell,1,Vector3::zero(),$player);
    check($cell->getCount()===3 && count($player->effects->recorded)===$before,'Unpowered structures must preserve cells');
    $lens=clone $items['showcase:chromatic_lens'];$returned=[];
    check($lens->onInteractBlock($player,$block,$block,1,Vector3::zero(),$returned)===pocketmine\item\ItemUseResult::SUCCESS,'Lens must alter all structure materials');
    $block=$world->getBlock($at);$cell->onInteractBlock($player,$block,$block,1,Vector3::zero(),$returned);
    check($cell->getCount()===2 && count($player->effects->recorded)===$before+1,'Powered ability consumes one cell and grants effect');
    $effect=end($player->effects->recorded);
    check($effect->getDuration()===$d['duration'] && $effect->getAmplifier()===$d['amplifier'],'Structure effect duration and strength');
    $parts=$block->getAffectedBlocks();$last=end($parts);$world->setBlock($last->getPosition(),pocketmine\block\VanillaBlocks::STONE());
    $world->getBlock($at)->onInteract($cell,1,Vector3::zero(),$player);
    check($cell->getCount()===2,'Incomplete structures must preserve cells');
    $world->getBlock($at)->getAffectedBlocks();
    $tool=VanillaItems::AIR();$world->useBreakOn($at,$tool);
    check($world->getBlock($last->getPosition())->getTypeId()===pocketmine\block\VanillaBlocks::STONE()->getTypeId(),'Breaking must preserve unrelated replacement blocks');
    $world=new ExpansionWorld();$at=new Vector3(5,319,5);$replace=$world->getBlock($at);$tx=new BlockTransaction($world);
    $fits=max(array_column($d['layout'],1))===0;
    check($factory->get('showcase:'.$name)->place($tx,$block->asItem(),$replace,$replace,1,Vector3::zero())===$fits,'Large footprint must respect world height');
}
foreach([
    ['layout'=>[[0,0,0],[0,0,0]],'segments'=>[[],[]]],
    ['layout'=>[[1,0,0],[0,0,0]],'segments'=>[[],[]]],
    ['layout'=>[[0,0,0],[0.5,0,0]],'segments'=>[[],[]]]
] as $invalid){
    try{ customiesdevs\showcase\StructureLayout::validate($invalid); check(false,'Invalid layouts must fail'); }
    catch(InvalidArgumentException){}
}
$world=new ExpansionWorld();$at=new Vector3(0,70,0);$world->setBlock($at,$factory->get('showcase:thermal_forge'));
$lines=customiesdevs\showcase\DebugMessages::block($world->getBlock($at));
check(!str_contains(implode(' ',$lines),'Components:'),'Compact diagnostics must omit verbose component data');
check(strlen(customiesdevs\showcase\DebugMessages::bounded(str_repeat('x',10000)))<1100,'NBT diagnostics must be bounded');
echo "PASS: arbitrary footprints in four directions, every-part breaks, powered abilities, cell consumption, unrelated-block protection and bounded diagnostics.\n";

// Hollow house cells are not reserved; furnishing survives grouped removal.
$world=new ExpansionWorld();$at=new Vector3(60,70,60);$inside=$at->add(2,1,-2);
$world->setBlock($inside,pocketmine\block\VanillaBlocks::STONE());
$house=$factory->get('showcase:prism_cottage');$replace=$world->getBlock($at);$tx=new BlockTransaction($world);
check($house->place($tx,$house->asItem(),$replace,$replace,1,Vector3::zero()) && $tx->apply(),'Furniture in hollow volume must not obstruct house placement');
foreach([[2,1,0],[2,2,0],[2,2,-2]] as [$x,$y,$z]){ check($world->getBlock($at->add($x,$y,$z)) instanceof pocketmine\block\Air,'House entry and interior must be open'); }
$tool=VanillaItems::AIR();$world->useBreakOn($at,$tool);
check($world->getBlock($inside)->getTypeId()===pocketmine\block\VanillaBlocks::STONE()->getTypeId(),'Independent furniture must survive house removal');
foreach(['prism_cottage'=>96,'market_hall'=>96,'grand_gateway'=>64] as $name=>$parts){
    $block=$factory->get('showcase:'.$name);
    check(count($block->getState(customiesdevs\showcase\StructureLayout::PART)->getValues())===16,'Banked low state stays at 16 values');
    customiesdevs\showcase\StructureLayout::setPart($block,$parts-1);
    check(customiesdevs\showcase\StructureLayout::part($block)===$parts-1,'High-bank indices must decode correctly');
}
echo "PASS: hollow house entrance, preserved furniture and banked high-part indices.\n";
