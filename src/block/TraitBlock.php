<?php
declare(strict_types=1);

namespace customiesdevs\customies\block;

use customiesdevs\customies\block\traits\BlockTraits;
use customiesdevs\customies\block\traits\BlockTraitsTrait;
use customiesdevs\customies\block\permutations\BlockPermutations;
use customiesdevs\customies\block\permutations\BlockPermutationsTrait;
use pocketmine\block\Block;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

/** Base for blocks whose server states are provided by Bedrock traits. Add traits before parent::__construct(). */
class TraitBlock extends Block implements BlockTraits, BlockPermutations, \customiesdevs\customies\block\component\BlockComponents {
	use \customiesdevs\customies\block\component\BlockComponentsTrait;
	use BlockTraitsTrait;
	use BlockPermutationsTrait;

	public function __clone(){
		parent::__clone();
		foreach($this->states as $name => $state){ $this->states[$name] = clone $state; }
		foreach($this->traits as $name => $trait){
			$this->traits[$name] = $trait = clone $trait;
			foreach($trait->getStates() as $state){ $this->states[$state->getName()] = $state; }
		}
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w): void {
		$this->initializeTraits();
		foreach($this->getStates() as $state){
			$values = $state->getValues();
			$index = array_search($state->getCurrentValue(), $values, true);
			if(count($values) > 1){ $w->boundedIntAuto(0, count($values) - 1, $index); }
			$state->setCurrentValue($values[$index]);
		}
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool {
		try{
			$this->applyTraitPlacement($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
		}catch(\RuntimeException){ return false; }
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function getAffectedBlocks(): array {
		$trait = $this->getTrait("minecraft:multi_block");
		if(!$trait instanceof \customiesdevs\customies\block\traits\MultiBlockTrait){ return parent::getAffectedBlocks(); }
		$direction = $this->getState("minecraft:cardinal_direction")?->getCurrentValue();
		$part = $this->getState($trait::PART)->getCurrentValue();
		$origin = $this->getPosition()->subtractVector($trait->getOffsetForPart($part, $direction));
		$blocks = [];
		for($i = 0; $i < $trait->getParts(); ++$i){
			$other = $this->getPosition()->getWorld()->getBlock($origin->addVector($trait->getOffsetForPart($i, $direction)));
			if($other instanceof self && $other->getTypeId() === $this->getTypeId()
				&& $other->getState($trait::PART)?->getCurrentValue() === $i
				&& $other->getState("minecraft:cardinal_direction")?->getCurrentValue() === $direction){ $blocks[] = $other; }
		}
		return $blocks;
	}

	public function onNearbyBlockChange(): void {
		parent::onNearbyBlockChange();
		$before = $this->getStateId();
		$this->updateTraitState();
		if($before !== $this->getStateId()){ $this->getPosition()->getWorld()->setBlock($this->getPosition(), $this); }
	}
}
