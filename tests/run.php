<?php
declare(strict_types=1);
$root = $argv[1] ?? getenv('AXOLOTL_ROOT');
if(!$root || !is_file($root . '/vendor/autoload.php')){ fwrite(STDERR, "Usage: php tests/run.php /path/to/axolotl-checkout-with-vendor\n"); exit(1); }
require $root . '/vendor/autoload.php';
spl_autoload_register(static function(string $class): void {
	$prefix = 'customiesdevs\\customies\\';
	if(str_starts_with($class, $prefix)){ require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php'; }
});
function check(bool $ok, string $message): void { if(!$ok){ throw new RuntimeException($message); } }
use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\traits\ConnectionTrait;
use customiesdevs\customies\block\traits\MultiBlockTrait;
use customiesdevs\customies\block\traits\PlacementDirectionTrait;
use customiesdevs\customies\block\permutations\Permutations;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockTypeInfo;
class TestBlock extends TraitBlock {
	private static function typeId(): int { static $id = null; return $id ??= pocketmine\block\BlockTypeIds::newId(); }
	public function __construct(){
		$this->addTrait(ConnectionTrait::cardinal());
		parent::__construct(new BlockIdentifier(self::typeId()), 'Test', new BlockTypeInfo(BlockBreakInfo::instant()));
	}
}
$block = new TestBlock();
$clone = clone $block;
$clone->getState(ConnectionTrait::NORTH)->setCurrentValue(true);
check($block->getState(ConnectionTrait::NORTH)->getCurrentValue() === false, 'clones must isolate mutable states');
check($clone->getTrait('minecraft:connection')->getStates()[0] === $clone->getState(ConnectionTrait::NORTH), 'trait and state storage must agree');
for($meta = 0; $meta < 16; ++$meta){
	$values = Permutations::fromMeta($block, $meta);
	foreach(array_values($block->getStates()) as $i => $state){ $state->setCurrentValue($values[$i]); }
	check(Permutations::toMeta($block) === $meta, 'meta round trip');
}
check(count(iterator_to_array($block->generateStatePermutations())) === 16, 'all connection states must be registered');
check(Permutations::getCartesianProduct([[true, false], [false, true]]) === [[true,false],[true,true],[false,false],[false,true]], 'false must not stop Cartesian iteration');
$writer = pocketmine\data\bedrock\block\convert\BlockStateWriter::create('test:block');
$clone->serializeState($writer);
check($writer->getBlockStateData()->getStates()[ConnectionTrait::NORTH]->getValue() === 1, 'bool state NBT');
$numericStateBlock = new class {
	use customiesdevs\customies\block\states\BlockStatesTrait;
};
$numericStateBlock->addState(new customiesdevs\customies\block\states\BlockState('0', [0, 1]));
$numericStateBlock->getState('0')->setCurrentValue(1);
$numericWriter = pocketmine\data\bedrock\block\convert\BlockStateWriter::create('test:numeric_key');
$numericStateBlock->serializeState($numericWriter);
check($numericWriter->getBlockStateData()->getStates()['0']->getValue() === 1, 'numeric array keys must serialize using string state names');
$numericStateBlock->getState('0')->setCurrentValue(0);
$numericStateBlock->deserializeState(new pocketmine\data\bedrock\block\convert\BlockStateReader($numericWriter->getBlockStateData()));
check($numericStateBlock->getState('0')->getCurrentValue() === 1, 'numeric array keys must deserialize using string state names');
check(MultiBlockTrait::vertical(4)->getOffsetForPart(3)->y === 3, 'vertical parts');
check(MultiBlockTrait::north()->getOffsetForPart(1, 'east')->x === 1, 'cardinal override');
try { new customiesdevs\customies\block\states\BlockState('bad', []); throw new RuntimeException('accepted empty values'); } catch(InvalidArgumentException){}
$item = new class implements customiesdevs\customies\item\ItemComponents { use customiesdevs\customies\item\ItemComponentsTrait; };
check($item->getComponents() === [], 'new item components must be empty');

class MemoryWorld implements pocketmine\world\ChunkManager {
	public array $blocks = [];
	public function getBlockAt(int $x, int $y, int $z): pocketmine\block\Block { return $this->blocks["$x:$y:$z"] ?? pocketmine\block\VanillaBlocks::AIR(); }
	public function setBlockAt(int $x, int $y, int $z, pocketmine\block\Block $block): void { $this->blocks["$x:$y:$z"] = $block; }
	public function getChunk(int $x, int $z): ?pocketmine\world\format\Chunk { return null; }
	public function setChunk(int $x, int $z, pocketmine\world\format\Chunk $chunk): void {}
	public function getMinY(): int { return -64; }
	public function getMaxY(): int { return 320; }
	public function isInWorld(int $x, int $y, int $z): bool { return $y >= -64 && $y < 320; }
}
class TallBlock extends TraitBlock {
	public function __construct(){
		$this->addTrait(MultiBlockTrait::vertical(3));
		parent::__construct(new BlockIdentifier(pocketmine\block\BlockTypeIds::newId()), 'Tall', new BlockTypeInfo(BlockBreakInfo::instant()));
	}
}
$world = new MemoryWorld();
$tx = new pocketmine\world\BlockTransaction($world);
$tall = new TallBlock();
$air = pocketmine\block\VanillaBlocks::AIR();
$pos = pocketmine\math\Vector3::zero();
check($tall->place($tx, $air->asItem(), $air, $air, 1, $pos), 'multi placement');
$parts = iterator_to_array($tx->getBlocks());
check(count($parts) === 3, 'all parts added to one transaction');
check($tx->fetchBlockAt(0, 2, 0)->getState(MultiBlockTrait::PART)->getCurrentValue() === 2, 'part index');
$world->blocks['0:1:0'] = pocketmine\block\VanillaBlocks::STONE();
check(!(new TallBlock())->place(new pocketmine\world\BlockTransaction($world), $air->asItem(), $air, $air, 1, $pos), 'blocked multi placement fails');
$world->blocks = ['0:0:-1' => pocketmine\block\VanillaBlocks::STONE()];
$connectionBlock = new TestBlock();
$connectionBlock->applyTraitPlacement(new pocketmine\world\BlockTransaction($world), $air->asItem(), $air, $air, 1, $pos, null);
check($connectionBlock->getState(ConnectionTrait::NORTH)->getCurrentValue() === true, 'placement connection refresh');
check($connectionBlock->getState(ConnectionTrait::SOUTH)->getCurrentValue() === false, 'air must not connect');
$position = customiesdevs\customies\block\traits\PlacementPositionTrait::both();
$position->applyPlacement(new customiesdevs\customies\block\traits\BlockTraitContext($tx, $air->asItem(), $air, $air, 1, new pocketmine\math\Vector3(0, 1, 0), null));
check($position->getStates()[1]->getCurrentValue() === 'bottom', 'top face places bottom half');
$factory = customiesdevs\customies\block\CustomiesBlockFactory::getInstance();
$translator = pocketmine\network\mcpe\convert\TypeConverter::getInstance()->getBlockTranslator();
$warmBlocks = pocketmine\block\VanillaBlocks::getAll();
$oldNetworkIds = [];
foreach($warmBlocks as $warmBlock){ $oldNetworkIds[$warmBlock->getStateId()] = $translator->internalIdToNetworkId($warmBlock->getStateId()); }
$factory->registerBlock(static fn() => new TestBlock(), 'test:connections');
$shifted = 0;
foreach($warmBlocks as $warmBlock){
	$expected = $translator->getBlockStateDictionary()->lookupStateIdFromData(pocketmine\world\format\io\GlobalBlockStateHandlers::getSerializer()->serialize($warmBlock->getStateId()));
	check($translator->internalIdToNetworkId($warmBlock->getStateId()) === $expected, 'palette insertion must invalidate cached vanilla runtime IDs');
	if($oldNetworkIds[$warmBlock->getStateId()] !== $expected){ ++$shifted; }
}
check($shifted > 0, 'cache regression fixture must move existing runtime IDs');
check(count($factory->getBlockPaletteEntries()) === 1, 'palette registration');
check(count(customiesdevs\customies\block\BlockPalette::getInstance()->getCustomStates()) === 16, 'batched palette states');
$offset = PlacementDirectionTrait::cardinal(90);
$rotate = new ReflectionMethod($offset, 'rotateCardinal');
check($rotate->invoke($offset, pocketmine\math\Facing::SOUTH) === 'east', 'offset must rotate counterclockwise');
$component = new customiesdevs\customies\block\component\TransformationComponent();
$permutation = new customiesdevs\customies\block\permutations\BlockPermutation('true', $component, [new customiesdevs\customies\block\component\MovableComponent()]);
check(count($permutation->toArray()['components']) === 2, 'multiple permutation components');
$factory->registerBlock(static fn() => new TallBlock(), 'test:tall');
check(count(customiesdevs\customies\block\BlockPalette::getInstance()->getCustomStates()) === 19, 'non-power-of-two part states');
try { $factory->registerBlock(static fn() => new TestBlock(), 'test:connections'); throw new RuntimeException('accepted duplicate'); } catch(InvalidArgumentException){}
$invalidContext = new customiesdevs\customies\block\traits\BlockTraitContext($tx, $air->asItem(), $air, $air, 1, $pos, null);
try {
	MultiBlockTrait::vertical()->applyPlacement($invalidContext);
	throw new RuntimeException('accepted a block without BlockStates');
} catch(LogicException $e){
	check(str_contains($e->getMessage(), 'BlockStates'), 'invalid context must explain the required interface');
}
check($tall->getState(MultiBlockTrait::PART)->getCurrentValue() === 0, 'placing extra parts must preserve origin index');
class CornerBlock extends TraitBlock {
	public function __construct(){
		static $id = null;
		$this->addTrait(PlacementDirectionTrait::cornerAndCardinal());
		parent::__construct(new BlockIdentifier($id ??= pocketmine\block\BlockTypeIds::newId()), 'Corner', new BlockTypeInfo(BlockBreakInfo::instant()));
	}
}
$cornerBlock = new CornerBlock();
$cornerBlock->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->setCurrentValue('north');
$neighbor = clone $cornerBlock;
$neighbor->getState(PlacementDirectionTrait::CARDINAL_DIRECTION)->setCurrentValue('west');
$world->blocks = ['0:0:-1' => $neighbor];
$cornerContext = new customiesdevs\customies\block\traits\BlockTraitContext(new pocketmine\world\BlockTransaction($world), $air->asItem(), $air, $air, 1, $pos, null, $cornerBlock);
$cornerBlock->getTrait('minecraft:placement_direction')->applyPlacement($cornerContext);
check($cornerBlock->getState('minecraft:corner')->getCurrentValue() === 'outer_left', 'corner update without a player');
$world->blocks = ['0:0:-1' => $air];
$cornerBlock->getTrait('minecraft:placement_direction')->update($cornerContext);
check($cornerBlock->getState('minecraft:corner')->getCurrentValue() === 'none', 'non-state neighbors must be ignored');
try {
	PlacementDirectionTrait::cornerAndCardinal()->update($invalidContext);
	throw new RuntimeException('accepted corner block without BlockStates');
} catch(LogicException $e){
	check(str_contains($e->getMessage(), 'BlockStates'), 'corner context must require the state interface');
}
echo "All regression checks passed\n";

