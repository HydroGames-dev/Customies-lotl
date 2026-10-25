<?php
declare(strict_types=1);
namespace customiesdevs\showcase;

use customiesdevs\customies\block\states\BlockStates;
use customiesdevs\customies\block\component\BlockComponents;
use pocketmine\block\Block;
use pocketmine\math\Vector3;

/** Bounded, readable diagnostics shared by commands and inspection tools. */
final class DebugMessages {
	public static function position(Vector3 $at): string { return $at->getFloorX().', '.$at->getFloorY().', '.$at->getFloorZ(); }
	public static function bounded(string $text, int $limit = 1024): string {
		return strlen($text) > $limit ? substr($text,0,$limit).' ... [truncated]' : $text;
	}
	public static function block(Block $block, bool $full = false): array {
		$result = ['§b[Showcase] §f'.$block->getName().' §7@ '.self::position($block->getPosition())];
		$states = [];
		if($block instanceof BlockStates){ foreach($block->getStates() as $state){ $value=$state->getCurrentValue(); $states[]=str_replace(['minecraft:','showcase:'],'',$state->getName()).'='.(is_bool($value) ? ($value ? 'on' : 'off') : $value); } }
		foreach(array_chunk($states,4) as $line){ $result[]='§7States: §f'.implode(' | ',$line); }
		if($block instanceof ShowcaseBlock && ExpansionBlocks::has($block->getSpecimen())){
			$d=ExpansionBlocks::definitions()[$block->getSpecimen()];
			$result[]='§7Structure: §f'.count($block->getAffectedBlocks()).'/'.$d['parts'].' cells | mode '.$d['mode'].' | light '.$block->getLightLevel();
			if(isset($d['ability'])){ $result[]='§7Ability: §f'.$d['ability'].' | power on, then offer Resonance Cell'; }
		}
		if($full){
			if($block instanceof BlockComponents){ foreach(array_chunk(array_map(static fn($c)=>$c->getName(),$block->getComponents()),4) as $line){ $result[]='§7Components: §f'.implode(', ',$line); } }
			$result[]='§7Server: §ftype '.$block->getTypeId().' | state '.$block->getStateId();
			foreach($block->asItem()->getLore() as $line){ $result[]=$line; }
		}
		return $result;
	}
}
