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
	$directions=isset($definition['direction']) && in_array($definition['direction'],['north','south','east','west'],true) ? ['north','east','south','west'] : [null];
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
			$obstruction=$at->addVector($trait->getOffsetForPart($definition['parts']-1,$direction));
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
