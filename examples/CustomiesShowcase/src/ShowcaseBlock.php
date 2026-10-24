<?php
declare(strict_types=1);
namespace customiesdevs\showcase;

use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\component as B;
use customiesdevs\customies\block\properties\{Material, Box, RenderMethod};
use customiesdevs\customies\block\states\BlockState;
use customiesdevs\customies\block\traits\{PlacementDirectionTrait, PlacementPositionTrait, MultiBlockTrait, ConnectionTrait};
use customiesdevs\customies\block\preset\BlockPresets;
use customiesdevs\customies\block\permutations\BlockPermutation;
use pocketmine\block\{Block, BlockIdentifier, BlockTypeInfo, BlockBreakInfo};
use pocketmine\item\Item;
use pocketmine\math\{Vector3, AxisAlignedBB};
use pocketmine\player\Player;

final class ShowcaseBlock extends TraitBlock {
	public const PLAYABLE = ['prism_lamp', 'machine', 'orienter', 'face_marker', 'slab', 'cable', 'corner_stairs', 'totem', 'bench', 'rotor', 'phase_block', 'ice_tile', 'glow_flower', 'signal_lamp', 'lumen_cable', 'phase_gate', 'spring_pad', 'prism_workbench', 'sky_obelisk', 'crystal_reactor', 'suspended_chandelier', 'holo_bench', 'prism_bridge', 'prism_telescope', 'tiny_lantern', 'mini_chess', 'pocket_planter', 'runic_pedestal'];

	public function __construct(int $typeId, private readonly string $specimen){
		$name = ucwords(str_replace('_', ' ', $specimen));
		$texture = 'showcase_' . $specimen;
		$geometry = 'geometry.showcase.cube';
		if(str_starts_with($specimen, 'lab_')){
			$texture = 'showcase_lab_block_' . substr($specimen,4);
		}else{
			$this->configure();
			$geometry = match($specimen){
				'slab' => 'geometry.showcase.slab', 'cable', 'lumen_cable' => 'geometry.showcase.cable',
				'corner_stairs' => 'geometry.showcase.stairs_none_bottom', 'totem' => 'geometry.showcase.totem',
				'bench' => 'geometry.showcase.bench', 'rotor' => 'geometry.showcase.rotor_0',
				'glow_flower' => 'geometry.showcase.flower', 'phase_gate' => 'geometry.showcase.gate_closed', 'spring_pad' => 'geometry.showcase.spring_pad', 'prism_workbench' => 'geometry.showcase.workbench', default => $geometry
			};
		}
		$this->addComponent(new B\DisplayNameComponent($name));
		$this->addComponent(new B\BlockTagsComponent(['showcase:specimen']));
		$this->addComponent(new B\GeometryComponent($geometry, $this->isCable() ? [
			'north' => "q.block_state('minecraft:connection_north')", 'south' => "q.block_state('minecraft:connection_south')",
			'west' => "q.block_state('minecraft:connection_west')", 'east' => "q.block_state('minecraft:connection_east')"
		] : []));
		$this->addComponent(in_array($specimen,['machine','orienter','face_marker'],true)
			? new B\MaterialInstancesComponent([new Material('*',$texture),new Material('north',$texture.'_front')])
			: self::material($texture, $specimen === 'glow_flower' ? RenderMethod::ALPHA_TEST : RenderMethod::OPAQUE));
		$this->addComponent(new B\MapColorComponent('#39d8ce'));
		$this->addComponent(new B\DestructibleByMiningComponent(0.6));
		$this->addComponent(new B\DestructibleByExplosionComponent(3.0));
		$this->addComponent(new B\CollisionBoxComponent($specimen !== 'glow_flower'));
		$this->addComponent(new B\LightEmissionComponent($specimen === 'glow_flower' ? 8 : 0));
		if($specimen === 'glow_flower'){
			$this->addComponent(new B\FlowerPottableComponent());
			$this->addComponent(new B\ReplaceableComponent());
			$this->addComponent(new B\SelectionBoxComponent(true, new Vector3(-4,0,-4), new Vector3(8,12,8)));
		}
		if($specimen === 'totem' || $specimen === 'rotor'){ $this->addComponent((new B\CollisionBoxComponent())->addBox(new Box(new Vector3(-4,0,-4),new Vector3(8,16,8)))); }
		if($specimen === 'phase_gate'){ $this->addComponent((new B\CollisionBoxComponent())->addBox(new Box(new Vector3(-8,0,-2),new Vector3(16,16,4)))); }
		if($specimen === 'spring_pad'){
			$this->addComponent((new B\CollisionBoxComponent())->addBox(new Box(new Vector3(-8,0,-8),new Vector3(16,4,16))));
			$this->addComponent(new B\SelectionBoxComponent(true,new Vector3(-8,0,-8),new Vector3(16,4,16)));
			$this->addComponent(new B\EntityFallOnComponent());
		}
		if($specimen === 'prism_workbench'){ $this->addComponent(new B\CraftingTableComponent(['crafting_table'],'Prism Workbench')); }
		if($specimen === 'ice_tile'){ $this->addComponent(new B\FrictionComponent(0.02)); }
		if($specimen === 'slab' || $specimen === 'bench'){
			$this->addComponent((new B\CollisionBoxComponent())->addBox(new Box(new Vector3(-8,0,-8), new Vector3(16,8,16))));
			$this->addComponent(new B\SelectionBoxComponent(true, new Vector3(-8,0,-8), new Vector3(16,8,16)));
		}
		if(str_starts_with($specimen, 'lab_')){
			$this->addComponent(ComponentCatalog::blocks()[substr($specimen, 4)]);
		}
		if(ExpansionBlocks::has($specimen)){ ExpansionBlocks::applyComponents($this); }
		parent::__construct(new BlockIdentifier($typeId), $name, new BlockTypeInfo(new BlockBreakInfo(0.6)));
	}

	private static function material(string $texture, RenderMethod $render = RenderMethod::OPAQUE): B\MaterialInstancesComponent {
		return new B\MaterialInstancesComponent([new Material('*', $texture, $render)]);
	}

	private function configure(): void {
		if(ExpansionBlocks::has($this->specimen)){ ExpansionBlocks::configure($this); return; }
		switch($this->specimen){
			case 'prism_lamp':
				$this->addState(BlockState::bool('showcase:powered'));
				$this->addPermutation(new BlockPermutation("q.block_state('showcase:powered')", new B\LightEmissionComponent(15), [self::material('showcase_lamp_on')]));
				break;
			case 'signal_lamp':
				BlockPresets::cardinal($this,180);
				$this->addState(BlockState::range('showcase:brightness',0,3));
				for($level=1;$level<=3;++$level){ $this->addPermutation(new BlockPermutation("q.block_state('showcase:brightness') == $level",new B\LightEmissionComponent($level*5),[self::material('showcase_signal_lamp_'.$level)])); }
				break;
			case 'phase_gate':
				BlockPresets::cardinal($this);
				$this->addState(BlockState::bool('showcase:open'));
				$this->addPermutation(new BlockPermutation("q.block_state('showcase:open')",new B\GeometryComponent('geometry.showcase.gate_open'),[new B\CollisionBoxComponent(false),new B\LightEmissionComponent(8)]));
				break;
			case 'spring_pad':
				$this->addState(BlockState::bool('showcase:powered'));
				$this->addPermutation(new BlockPermutation("q.block_state('showcase:powered')",self::material('showcase_spring_pad_on'),[new B\LightEmissionComponent(5)]));
				break;
			case 'prism_workbench': BlockPresets::cardinal($this,180); break;
			case 'machine': BlockPresets::cardinal($this, 180); break;
			case 'orienter': BlockPresets::facing($this); break;
			case 'face_marker':
				$this->addTrait(PlacementPositionTrait::blockFace());
				foreach(['north'=>[0,0], 'south'=>[0,180], 'west'=>[0,90], 'east'=>[0,270], 'up'=>[90,0], 'down'=>[270,0]] as $face => [$x,$y]){
					$this->addPermutation(new BlockPermutation("q.block_state('minecraft:block_face') == '$face'", new B\TransformationComponent(new Vector3($x,$y,0))));
				}
				break;
			case 'slab':
				$this->addTrait(PlacementPositionTrait::verticalHalf());
				$this->addPermutation(new BlockPermutation("q.block_state('minecraft:vertical_half') == 'top'", new B\GeometryComponent('geometry.showcase.slab_top'), [
					(new B\CollisionBoxComponent())->addBox(new Box(new Vector3(-8,8,-8), new Vector3(16,8,16))),
					new B\SelectionBoxComponent(true, new Vector3(-8,8,-8), new Vector3(16,8,16))
				]));
				break;
			case 'cable':
			case 'lumen_cable':
				if($this->specimen === 'lumen_cable'){
					$this->addState(BlockState::bool('showcase:powered'));
					$this->addPermutation(new BlockPermutation("q.block_state('showcase:powered')",new B\LightEmissionComponent(15),[self::material('showcase_lumen_cable_on')]));
				}
				BlockPresets::connections($this, static fn(Block $self, Block $other, int $face): bool => ($other instanceof self && $other->isCable()) || $other->isSolid());
				$arms = ['north'=>[-2,6,-8,4,4,8], 'south'=>[-2,6,0,4,4,8], 'west'=>[-8,6,-2,8,4,4], 'east'=>[0,6,-2,8,4,4]];
				for($bits=0; $bits<16; ++$bits){
					$conditions = []; $boxes = [new Box(new Vector3(-2,0,-2),new Vector3(4,16,4))];
					foreach(array_keys($arms) as $i=>$direction){
						$connected = ($bits & (1 << $i)) !== 0;
						$conditions[] = ($connected ? '' : '!')."q.block_state('minecraft:connection_$direction')";
						if($connected){ [$x,$y,$z,$w,$h,$d] = $arms[$direction]; $boxes[] = new Box(new Vector3($x,$y,$z),new Vector3($w,$h,$d)); }
					}
					$this->addPermutation(new BlockPermutation(implode(' && ',$conditions),(new B\CollisionBoxComponent())->addBoxes($boxes)));
				}
				break;
			case 'corner_stairs':
				// Set the half before corner calculation so top/bottom neighbors never join.
				$this->addTrait(PlacementPositionTrait::verticalHalf());
				$this->addTrait(PlacementDirectionTrait::cornerAndCardinal());
				foreach(['north'=>0,'west'=>90,'south'=>180,'east'=>270] as $face=>$angle){
					$this->addPermutation(new BlockPermutation("q.block_state('minecraft:cardinal_direction') == '$face'",new B\TransformationComponent(new Vector3(0,$angle,0))));
				}
				foreach(['bottom','top'] as $half){
					foreach(StairShapes::SHAPES as $shape){
						$boxes = array_map(static fn(AxisAlignedBB $b) => new Box(new Vector3($b->minX*16-8,$b->minY*16,$b->minZ*16-8),new Vector3(($b->maxX-$b->minX)*16,($b->maxY-$b->minY)*16,($b->maxZ-$b->minZ)*16)),StairShapes::boxes($shape,$half));
						$condition = "q.block_state('minecraft:corner') == '$shape' && q.block_state('minecraft:vertical_half') == '$half'";
						$this->addPermutation(new BlockPermutation($condition,new B\GeometryComponent("geometry.showcase.stairs_{$shape}_{$half}"),[(new B\CollisionBoxComponent())->addBoxes($boxes)]));
					}
				}
				break;
			case 'totem':
				BlockPresets::multiBlock($this, 'up', 3);
				foreach([1=>'showcase_totem_mid', 2=>'showcase_totem_top'] as $part => $texture){
					$this->addPermutation(new BlockPermutation("q.block_state('minecraft:multi_block_part') == $part", self::material($texture)));
				}
				break;
			case 'bench': BlockPresets::cardinal($this); BlockPresets::multiBlock($this, 'north', 2); break;
			case 'rotor':
				$this->addTrait(PlacementDirectionTrait::sixteenWay());
				for($i=0; $i<16; ++$i){
					$this->addPermutation(new BlockPermutation("q.block_state('minecraft:sixteen_way_rotation') == $i", new B\GeometryComponent('geometry.showcase.rotor_' . $i)));
				}
				break;
			case 'phase_block':
				$this->addState(BlockState::range('showcase:phase', 0, 2));
				foreach([1=>'showcase_phase_blue', 2=>'showcase_phase_ghost'] as $value => $texture){
					$this->addPermutation(new BlockPermutation("q.block_state('showcase:phase') == $value", self::material($texture), $value === 2 ? [new B\CollisionBoxComponent(false)] : []));
				}
				break;
		}
	}

	private function isCable(): bool { return in_array($this->specimen,['cable','lumen_cable'],true); }

	public function deserializeState(\pocketmine\data\bedrock\block\convert\BlockStateReader $in): void {
		if($this->specimen !== 'corner_stairs'){ parent::deserializeState($in); return; }
		$this->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->setCurrentValue($in->readString(PlacementDirectionTrait::CARDINAL_DIRECTION));
		$this->getState('minecraft:corner')->setCurrentValue($in->readString('minecraft:corner'));
		try{ $half = $in->readString(PlacementPositionTrait::VERTICAL_HALF); }
		catch(\pocketmine\data\bedrock\block\BlockStateDeserializeException $e){
			if(!str_contains($e->getMessage(),'is missing')){ throw $e; }
			$half = 'bottom'; // Preserve stairs saved before vertical-half support.
		}
		$this->getState(PlacementPositionTrait::VERTICAL_HALF)->setCurrentValue($half);
	}

	public function getSpecimen(): string { return $this->specimen; }
	public function getLightLevel(): int {
		if(ExpansionBlocks::has($this->specimen)){ return ExpansionBlocks::light($this); }
		if($this->specimen === 'signal_lamp'){ return $this->getState('showcase:brightness')->getCurrentValue()*5; }
		if($this->specimen === 'phase_gate'){ return $this->getState('showcase:open')->getCurrentValue() ? 8 : 0; }
		if(in_array($this->specimen,['lumen_cable','spring_pad'],true)){ return $this->getState('showcase:powered')->getCurrentValue() ? ($this->specimen === 'spring_pad' ? 5 : 15) : 0; }
		if($this->specimen === 'prism_lamp'){ return $this->getState('showcase:powered')->getCurrentValue() ? 15 : 0; }
		if($this->specimen === 'glow_flower' || $this->specimen === 'lab_light_emission'){ return $this->specimen === 'glow_flower' ? 8 : 15; }
		return parent::getLightLevel();
	}
	public function getLightFilter(): int { return $this->specimen === 'lab_light_dampening' || $this->specimen === 'glow_flower' ? 0 : parent::getLightFilter(); }
	public function getFrictionFactor(): float { return in_array($this->specimen, ['ice_tile', 'lab_friction'], true) ? 0.98 : parent::getFrictionFactor(); }
	public function isSolid(): bool { return !ExpansionBlocks::has($this->specimen) && !in_array($this->specimen, ['glow_flower', 'cable', 'lumen_cable', 'spring_pad', 'slab', 'bench', 'totem', 'rotor', 'corner_stairs'], true) && !$this->isGhost(); }
	public function canBeReplaced(): bool { return in_array($this->specimen, ['glow_flower', 'lab_replaceable'], true); }
	private function isGhost(): bool { return ($this->specimen === 'phase_block' && $this->getState('showcase:phase')->getCurrentValue() === 2) || ($this->specimen === 'phase_gate' && $this->getState('showcase:open')->getCurrentValue()); }

	protected function recalculateCollisionBoxes(): array {
		if(ExpansionBlocks::has($this->specimen)){ return ExpansionBlocks::collision($this); }
		if($this->isGhost() || in_array($this->specimen, ['glow_flower', 'lab_collision_box'], true)){ return []; }
		if($this->specimen === 'slab'){
			$y = $this->getState(PlacementPositionTrait::VERTICAL_HALF)->getCurrentValue() === 'top' ? 0.5 : 0;
			return [new AxisAlignedBB(0,$y,0,1,$y+0.5,1)];
		}
		if($this->specimen === 'bench'){ return [new AxisAlignedBB(0,0,0,1,0.5,1)]; }
		if($this->isCable()){
			$boxes = [new AxisAlignedBB(0.375,0,0.375,0.625,1,0.625)];
			foreach([ConnectionTrait::NORTH=>[0.375,0.375,0,0.625,0.625,0.5], ConnectionTrait::SOUTH=>[0.375,0.375,0.5,0.625,0.625,1], ConnectionTrait::WEST=>[0,0.375,0.375,0.5,0.625,0.625], ConnectionTrait::EAST=>[0.5,0.375,0.375,1,0.625,0.625]] as $state=>$bounds){
				if($this->getState($state)->getCurrentValue()){ $boxes[] = new AxisAlignedBB(...$bounds); }
			}
			return $boxes;
		}
		if($this->specimen === 'rotor' || $this->specimen === 'totem'){ return [new AxisAlignedBB(0.25,0,0.25,0.75,1,0.75)]; }
		if($this->specimen === 'corner_stairs'){
			return StairShapes::boxes($this->getState('minecraft:corner')->getCurrentValue(),$this->getState(PlacementPositionTrait::VERTICAL_HALF)->getCurrentValue(),$this->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->getCurrentValue());
		}
		if($this->specimen === 'spring_pad'){ return [new AxisAlignedBB(0,0,0,1,0.25,1)]; }
		if($this->specimen === 'phase_gate'){ return in_array($this->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->getCurrentValue(),['east','west'],true) ? [new AxisAlignedBB(0.375,0,0,0.625,1,1)] : [new AxisAlignedBB(0,0,0.375,1,1,0.625)]; }

		return parent::recalculateCollisionBoxes();
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []): bool {
		if(ExpansionBlocks::has($this->specimen)){ return ExpansionBlocks::interact($this,$item,$player); }
		if($this->specimen === 'prism_workbench'){
			$player?->setCurrentWindow(new \pocketmine\block\inventory\CraftingTableInventory($this->getPosition()));
			return true;
		}
		$state = match($this->specimen){ 'prism_lamp','lumen_cable','spring_pad'=>'showcase:powered', 'phase_block'=>'showcase:phase', 'phase_gate'=>'showcase:open', 'signal_lamp'=>'showcase:brightness', default=>null };
		if($state === null){ return parent::onInteract($item,$face,$clickVector,$player,$returnedItems); }
		$property = $this->getState($state);
		$value = $property->getCurrentValue();
		$values = $property->getValues();
		$property->setCurrentValue($values[(array_search($value,$values,true)+1)%count($values)]);
		$this->getPosition()->getWorld()->setBlock($this->getPosition(), $this);
		$player?->sendActionBarMessage($this->getName() . ': ' . json_encode($this->getCurrentStates()));
		return true;
	}

	public function onEntityLand(\pocketmine\entity\Entity $entity): ?float {
		if($this->specimen !== 'spring_pad' || !$this->getState('showcase:powered')->getCurrentValue()){ return parent::onEntityLand($entity); }
		if($entity instanceof \pocketmine\entity\Living && $entity->isSneaking()){ return null; }
		$entity->resetFallDistance();
		return max(0.0,-$entity->getMotion()->y);
	}

	public function asItem(): Item {
		return parent::asItem()->setLore(SpecimenDescriptions::block($this->specimen));
	}

	public function getDrops(Item $item): array {
		if(($this->getState(MultiBlockTrait::PART)?->getCurrentValue() ?? 0) !== 0){ return []; }
		return parent::getDrops($item);
	}
}
