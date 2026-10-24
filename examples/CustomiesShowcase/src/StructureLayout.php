<?php
declare(strict_types=1);
namespace customiesdevs\showcase;

use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Server-managed arbitrary footprints; deliberately does not emit minecraft:multi_block. */
final class StructureLayout {
	public const PART = 'showcase:structure_part';
	public static function validate(array $definition): void {
		$layout = $definition['layout'];
		if(count($layout) < 2 || count($layout) > 32 || count($layout) !== count($definition['segments']) || ($definition['parts'] ?? count($layout)) !== count($layout) || $layout[0] !== [0,0,0]){
			throw new \InvalidArgumentException('Structure layouts require 2-32 segments with part zero at the origin');
		}
		$seen = [];
		foreach($layout as $cell){
			if(count($cell) !== 3){ throw new \InvalidArgumentException('Layout cells need three integer coordinates'); }
			foreach($cell as $value){ if(!is_int($value) || abs($value) > 8){ throw new \InvalidArgumentException('Layout offsets must be integers between -8 and 8'); } }
			$key = implode(',', $cell);
			if(isset($seen[$key])){ throw new \InvalidArgumentException('Duplicate structure cell '.$key); }
			$seen[$key] = true;
		}
	}
	public static function offset(array $definition, int $part, string $direction): Vector3 {
		[$x,$y,$z] = $definition['layout'][$part] ?? throw new \InvalidArgumentException('Invalid structure part');
		for($i=0,$turns=['north'=>0,'west'=>1,'south'=>2,'east'=>3][$direction] ?? throw new \InvalidArgumentException('Invalid structure direction');$i<$turns;++$i){ [$x,$z]=[$z,-$x]; }
		return new Vector3($x,$y,$z);
	}
	public static function place(ShowcaseBlock $block, array $d, BlockTransaction $tx, Block $replace, ?Player $player): bool {
		$block->getState(self::PART)->setCurrentValue(0);
		$direction = $block->getState('minecraft:cardinal_direction')->getCurrentValue();
		$origin = $replace->getPosition();
		// Check every cell before adding any structure parts to the transaction.
		foreach($d['layout'] as $i=>$_){
			$at = $origin->addVector(self::offset($d,$i,$direction));
			if(!$origin->getWorld()->isInWorld($at->getFloorX(),$at->getFloorY(),$at->getFloorZ()) || !$tx->fetchBlock($at)->canBeReplaced()){
				$player?->sendActionBarMessage('Cannot place '.$block->getName().': blocked or outside world at '.DebugMessages::position($at));
				return false;
			}
		}
		foreach($d['layout'] as $i=>$_){
			$part = clone $block;
			$part->getState(self::PART)->setCurrentValue($i);
			$tx->addBlock($origin->addVector(self::offset($d,$i,$direction)),$part);
		}
		return true;
	}
	/** Only matching cells belong to this structure; unrelated blocks are never removed. */
	public static function affected(ShowcaseBlock $block, array $d): array {
		$direction = $block->getState('minecraft:cardinal_direction')->getCurrentValue();
		$origin = $block->getPosition()->subtractVector(self::offset($d,$block->getState(self::PART)->getCurrentValue(),$direction));
		$result = [];
		foreach($d['layout'] as $i=>$_){
			$other = $block->getPosition()->getWorld()->getBlock($origin->addVector(self::offset($d,$i,$direction)));
			if($other instanceof ShowcaseBlock && $other->getTypeId() === $block->getTypeId() && $other->getState(self::PART)?->getCurrentValue() === $i && $other->getState('minecraft:cardinal_direction')?->getCurrentValue() === $direction){ $result[]=$other; }
		}
		return $result;
	}
}
