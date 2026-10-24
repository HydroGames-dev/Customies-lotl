<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\states\BlockState;
use customiesdevs\customies\block\preset\BlockPresets;
use customiesdevs\customies\block\permutations\BlockPermutation;
use customiesdevs\customies\block\properties\{Box,Material};
use pocketmine\math\{Vector3,AxisAlignedBB};
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\entity\effect\{EffectInstance,VanillaEffects};

final class ExpansionBlocks {
	public static function definitions(): array {
		static $data = null;
		return $data ??= json_decode(file_get_contents(__DIR__.'/../resources/expansion_blocks.json'),true,512,JSON_THROW_ON_ERROR);
	}
	public static function has(string $id): bool { return isset(self::definitions()[$id]); }
	private static function definition(ShowcaseBlock $block): array { return self::definitions()[$block->getSpecimen()]; }
	public static function configure(ShowcaseBlock $block): void {
		$d = self::definition($block); $id = $block->getSpecimen();
		if(isset($d['direction'])){
			if(in_array($d['direction'],['north','south','west','east'],true)){ BlockPresets::cardinal($block); }
			BlockPresets::multiBlock($block,$d['direction'],$d['parts']);
		}
		if($d['mode'] === 'powered'){ $block->addState(BlockState::bool('showcase:powered')); }
		if($d['mode'] === 'color'){ $block->addState(BlockState::range('showcase:color',0,2)); }
		foreach($d['segments'] as $i=>$segment){
			if($i > 0){ $block->addPermutation(new BlockPermutation("q.block_state('minecraft:multi_block_part') == $i",new B\GeometryComponent("geometry.showcase.{$id}_{$i}"),self::shapeComponents($segment))); }
		}
		if($d['mode'] === 'powered'){
			$texture = $id === 'crystal_reactor' ? 'showcase_reactor_flow' : 'showcase_'.$id.'_on';
			$block->addPermutation(new BlockPermutation("q.block_state('showcase:powered')",new B\MaterialInstancesComponent([new Material('*',$texture)]),[new B\LightEmissionComponent($d['active_light'])]));
		}
		if($d['mode'] === 'color'){
			foreach([1,2] as $color){ $block->addPermutation(new BlockPermutation("q.block_state('showcase:color') == $color",new B\MaterialInstancesComponent([new Material('*','showcase_'.$id.'_'.$color)]))); }
		}
	}
	private static function shapeComponents(array $segment): array {
		[$x,$y,$z,$xx,$yy,$zz] = $segment['bounds'];
		$origin = new Vector3($x*16-8,$y*16,$z*16-8); $size = new Vector3(($xx-$x)*16,($yy-$y)*16,($zz-$z)*16);
		$boxes = isset($segment['collision_boxes']) ? array_map(static function(array $b){
			[$x,$y,$z,$xx,$yy,$zz] = $b;
			return new Box(new Vector3($x*16-8,$y*16,$z*16-8),new Vector3(($xx-$x)*16,($yy-$y)*16,($zz-$z)*16));
		},$segment['collision_boxes']) : [new Box($origin,$size)];
		return [(new B\CollisionBoxComponent())->addBoxes($boxes),new B\SelectionBoxComponent(true,$origin,$size),new B\LightEmissionComponent($segment['light'])];
	}
	public static function applyComponents(ShowcaseBlock $block): void {
		$d = self::definition($block);
		$block->addComponent(new B\GeometryComponent('geometry.showcase.'.$block->getSpecimen().'_0'));
		foreach(self::shapeComponents($d['segments'][0]) as $component){ $block->addComponent($component); }
	}
	public static function light(ShowcaseBlock $block): int {
		$d = self::definition($block);
		if($d['mode'] === 'powered' && $block->getState('showcase:powered')->getCurrentValue()){ return $d['active_light']; }
		return $d['segments'][$block->getState('minecraft:multi_block_part')?->getCurrentValue() ?? 0]['light'];
	}
	public static function collision(ShowcaseBlock $block): array {
		$d = self::definition($block); $part = $block->getState('minecraft:multi_block_part')?->getCurrentValue() ?? 0;
		[$x,$y,$z,$xx,$yy,$zz] = $d['segments'][$part]['bounds'];
		$direction = $block->getState('minecraft:cardinal_direction')?->getCurrentValue();
		$turns = ['north'=>0,'west'=>1,'south'=>2,'east'=>3][$direction ?? 'north'];
		for($i=0;$i<$turns;++$i){ [$x,$z,$xx,$zz] = [$z,1-$xx,$zz,1-$x]; }
		if(isset($d['segments'][$part]['collision_boxes'])){
			return array_map(static function(array $b) use($turns){
				[$x,$y,$z,$xx,$yy,$zz] = $b;
				for($i=0;$i<$turns;++$i){ [$x,$z,$xx,$zz] = [$z,1-$xx,$zz,1-$x]; }
				return new AxisAlignedBB($x,$y,$z,$xx,$yy,$zz);
			},$d['segments'][$part]['collision_boxes']);
		}
		return [new AxisAlignedBB($x,$y,$z,$xx,$yy,$zz)];
	}
	public static function interact(ShowcaseBlock $block, Item $item, ?Player $player): bool {
		$d = self::definition($block);
		if($d['mode'] === 'powered' || $d['mode'] === 'color'){
			$name = $d['mode'] === 'powered' ? 'showcase:powered' : 'showcase:color';
			$property = $block->getState($name); $values = $property->getValues();
			$value = $values[(array_search($property->getCurrentValue(),$values,true)+1)%count($values)];
			foreach($block->getAffectedBlocks() as $part){
				$part->getState($name)->setCurrentValue($value);
				$part->getPosition()->getWorld()->setBlock($part->getPosition(),$part);
			}
			return true;
		}
		if($d['mode'] === 'telescope'){
			$player?->getEffects()->add(new EffectInstance(VanillaEffects::NIGHT_VISION(),400,0));
			$player?->sendActionBarMessage('Telescope: night vision for 20 seconds');
			return true;
		}
		if($d['mode'] === 'offering'){
			if($player !== null && $item instanceof PrismFood && $item->getSpecimen() === 'prism_berry' && $item->getCount() > 0){
				$item->pop();
				$player->getEffects()->add(new EffectInstance(VanillaEffects::REGENERATION(),100,1));
				$player->sendActionBarMessage('Offering accepted: Regeneration II for 5 seconds');
			}else{ $player?->sendActionBarMessage('Offer one Prism Berry to the pedestal'); }
			return true;
		}
		return false;
	}
}
