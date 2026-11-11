<?php
declare(strict_types=1);
namespace customiesdevs\restarttest;

use customiesdevs\customies\block\TraitBlock;
use customiesdevs\customies\block\traits\PlacementDirectionTrait;
use pocketmine\block\{BlockIdentifier, BlockTypeInfo, BlockBreakInfo};

// One class per file: async workers autoload block classes by name when they replay registration.
final class ProbeBlock extends TraitBlock {
	public function __construct(int $id){
		$this->addTrait(PlacementDirectionTrait::cardinal());
		parent::__construct(new BlockIdentifier($id), 'Restart probe', new BlockTypeInfo(BlockBreakInfo::instant()));
	}
}
