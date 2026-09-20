<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\traits;

use customiesdevs\customies\block\states\BlockState;
use pocketmine\block\Block;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use InvalidArgumentException;

trait BlockTraitsTrait {

	/** @var array<string, BlockTrait> */
	private array $traits = [];
	private bool $traitsInitialized = false;

	public function addTrait(BlockTrait $trait): void {
		$name = $trait->getName();
		if(isset($this->traits[$name])){
			throw new InvalidArgumentException("Trait '{$name}' is already registered.");
		}
		$this->traits[$name] = $trait;
	}

	public function hasTrait(string $name): bool {
		return isset($this->traits[$name]);
	}

	public function getTrait(string $name): ?BlockTrait {
		return $this->traits[$name] ?? null;
	}

	/**
	 * @return BlockTrait[]
	 */
	public function getTraits(): array {
		return array_values($this->traits);
	}

	public function initializeTraits(): void {
		if($this->traitsInitialized){
			return;
		}
		$this->traitsInitialized = true;
		foreach($this->traits as $trait){
			foreach($trait->getStates() as $state){
				$this->addTraitState($state);
			}
		}
	}

	private function addTraitState(BlockState $state): void {
		if(method_exists($this, "hasState") && $this->hasState($state->getName())){
			throw new InvalidArgumentException("Trait state '{$state->getName()}' conflicts with an existing block state.");
		}
		if(method_exists($this, "addState")){
			$this->addState($state);
		}
	}

	public function applyTraitPlacement(
		BlockTransaction $tx,
		Item $item,
		Block $blockReplace,
		Block $blockClicked,
		int $face,
		Vector3 $clickVector,
		?Player $player
	): bool {
		$this->initializeTraits();
		$context = new BlockTraitContext(
			$tx,
			$item,
			$blockReplace,
			$blockClicked,
			$face,
			$clickVector,
			$player
		);
		foreach($this->traits as $trait){
			$trait->applyPlacement($context);
		}
		return true;
	}

	public function updateTraitState(): void {
		$this->initializeTraits();
		$context = new BlockTraitContext(
			throw new \LogicException("Update context is not available."),
			throw new \LogicException("Update context is not available."),
			$this,
			$this,
			0,
			Vector3::zero(),
			null
		);
	}

	public function serializeTraitState(BlockStateWriter $out): void {
		$this->initializeTraits();
		foreach($this->traits as $trait){
			foreach($trait->getStates() as $state){
				$value = $state->getCurrentValue();
				if(is_bool($value)){
					$out->writeBool($state->getName(), $value);
				}elseif(is_int($value)){
					$out->writeInt($state->getName(), $value);
				}elseif(is_string($value)){
					$out->writeString($state->getName(), $value);
				}
			}
		}
	}

	public function deserializeTraitState(BlockStateReader $in): void {
		$this->initializeTraits();
		foreach($this->traits as $trait){
			foreach($trait->getStates() as $state){
				$name = $state->getName();
				$values = $state->getValues();
				$value = match(true){
					$values !== [] && is_bool($values[0]) => $in->readBool($name),
					$values !== [] && is_int($values[0]) => $in->readInt($name),
					default => $in->readString($name),
				};
				$state->setCurrentValue($value);
			}
		}
	}
}