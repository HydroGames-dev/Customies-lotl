<?php
declare(strict_types=1);
// In-memory storage around the real World::useBreakOn() and Block::onBreak() implementations.
class ShowcaseBreakWorld extends pocketmine\world\World {
	private array $blocks = [];
	public array $drops = [];
	public function __construct(){}
	public function isLoaded(): bool { return true; }
	public function isChunkLoaded(int $x, int $z): bool { return true; }
	public function isChunkLocked(int $chunkX, int $chunkZ): bool { return false; }
	public function getBlock(pocketmine\math\Vector3 $pos, bool $cached = true, bool $addToCache = true): pocketmine\block\Block {
		return $this->getBlockAt($pos->getFloorX(),$pos->getFloorY(),$pos->getFloorZ(),$cached,$addToCache);
	}
	public function getBlockAt(int $x, int $y, int $z, bool $cached = true, bool $addToCache = true): pocketmine\block\Block {
		$block = clone ($this->blocks["$x:$y:$z"] ?? pocketmine\block\VanillaBlocks::AIR());
		$block->position($this,$x,$y,$z);
		return $block;
	}
	public function setBlock(pocketmine\math\Vector3 $pos, pocketmine\block\Block $block, bool $update = true): void {
		$this->blocks[$pos->getFloorX().':'.$pos->getFloorY().':'.$pos->getFloorZ()] = clone $block;
	}
	public function getTile(pocketmine\math\Vector3 $pos): ?pocketmine\block\tile\Tile { return null; }
	public function dropItem(pocketmine\math\Vector3 $source, pocketmine\item\Item $item, ?pocketmine\math\Vector3 $motion = null, int $delay = 10): ?pocketmine\entity\object\ItemEntity {
		$this->drops[] = clone $item;
		return null;
	}
}
$breakWorld = new ShowcaseBreakWorld();
$blockFactory = customiesdevs\customies\block\CustomiesBlockFactory::getInstance();
$at = new pocketmine\math\Vector3(20,64,-12);
foreach(['bottom','top'] as $half){
	$slab = $blockFactory->get('showcase:slab');
	$slab->getState('minecraft:vertical_half')->setCurrentValue($half);
	$breakWorld->setBlock($at,$slab);
	$placed = $breakWorld->getBlock($at);
	$ray = $placed->calculateIntercept($at->add(0.5,2,0.5),$at->add(0.5,-1,0.5));
	check($ray !== null,'Slab must be targetable: '.$half);
	check(abs($ray->hitVector->y - (64 + ($half === 'top' ? 1 : 0.5))) < 0.00001,'Slab ray must hit visible surface: '.$half);
	$tool = pocketmine\item\VanillaItems::AIR();
	check($breakWorld->useBreakOn($at,$tool),'Slab break must succeed: '.$half);
	check($breakWorld->getBlock($at) instanceof pocketmine\block\Air,'Slab must be removed: '.$half);
}
foreach(['north','south','west','east'] as $direction){
	foreach([0,1] as $targetPart){
		$breakWorld->drops = [];
		$bench = $blockFactory->get('showcase:bench');
		$bench->getState('minecraft:cardinal_direction')->setCurrentValue($direction);
		$offset = $bench->getTrait('minecraft:multi_block')->getOffsetForPart(1,$direction);
		$secondAt = $at->addVector($offset);
		$breakWorld->setBlock($at,$bench);
		$second = clone $bench;
		$second->getState('minecraft:multi_block_part')->setCurrentValue(1);
		$breakWorld->setBlock($secondAt,$second);
		$target = $targetPart === 0 ? $at : $secondAt;
		$placed = $breakWorld->getBlock($target);
		check($placed->calculateIntercept($target->add(0.5,2,0.5),$target->add(0.5,-1,0.5)) !== null,'Bench must be targetable');
		check(count($placed->getAffectedBlocks()) === 2,'Breaking either bench part must select both halves');
		$tool = pocketmine\item\VanillaItems::AIR();
		check($breakWorld->useBreakOn($target,$tool),'Bench break must succeed');
		check($breakWorld->getBlock($at) instanceof pocketmine\block\Air && $breakWorld->getBlock($secondAt) instanceof pocketmine\block\Air,'Both bench parts must be removed');
		check(count($breakWorld->drops) === 1 && $breakWorld->drops[0]->getCount() === 1,'Bench must drop exactly once');
	}
}
echo "PASS: both slab halves and either bench part in all four directions are targetable and break through World::useBreakOn().\n";
