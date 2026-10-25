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
	public const BANK = 'showcase:structure_bank';
	public static function part(ShowcaseBlock $block): int {
		return ($block->getState(self::PART)?->getCurrentValue() ?? 0) + 16 * ($block->getState(self::BANK)?->getCurrentValue() ?? 0);
	}
	public static function setPart(ShowcaseBlock $block, int $part): void {
		$bank = $block->getState(self::BANK);
		$block->getState(self::PART)->setCurrentValue($bank === null ? $part : $part % 16);
		$bank?->setCurrentValue(intdiv($part,16));
	}
	public static function validate(array $definition): void {
		$layout = $definition['layout'];
		if(count($layout) < 2 || count($layout) > (($definition['banked'] ?? false) ? 128 : 32) || count($layout) !== count($definition['segments']) || ($definition['parts'] ?? count($layout)) !== count($layout) || $layout[0] !== [0,0,0]){
			throw new \InvalidArgumentException('Structure layouts need matching parts/segments and an origin; legacy limit 32, banked limit 128');
		}
		if(($definition['banked'] ?? false) && count($layout) % 16 !== 0){ throw new \InvalidArgumentException('Banked layouts must contain a multiple of 16 cells to avoid unused runtime states'); }
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
		self::setPart($block,0);
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
			self::setPart($part,$i);
			$tx->addBlock($origin->addVector(self::offset($d,$i,$direction)),$part);
		}
		return true;
	}
	/** Only matching cells belong to this structure; unrelated blocks are never removed. */
	public static function affected(ShowcaseBlock $block, array $d): array {
		$direction = $block->getState('minecraft:cardinal_direction')->getCurrentValue();
		$origin = $block->getPosition()->subtractVector(self::offset($d,self::part($block),$direction));
		$result = [];
		foreach($d['layout'] as $i=>$_){
			$other = $block->getPosition()->getWorld()->getBlock($origin->addVector(self::offset($d,$i,$direction)));
			if($other instanceof ShowcaseBlock && $other->getTypeId() === $block->getTypeId() && self::part($other) === $i && $other->getState('minecraft:cardinal_direction')?->getCurrentValue() === $direction){ $result[]=$other; }
		}
		return $result;
	}
}
