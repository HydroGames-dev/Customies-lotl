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
		if($this->traitsInitialized){ throw new \LogicException("Traits are frozen after initialization"); }
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
		if(!$this instanceof \customiesdevs\customies\block\permutations\BlockPermutations){
			throw new \LogicException("Trait blocks must implement BlockPermutations");
		}
		if($this->hasTrait("minecraft:multi_block")){
			foreach($this->getPermutations() as $permutation){
				foreach(["minecraft:placement_filter", "minecraft:replaceable", "minecraft:random_offset"] as $forbidden){
					if(array_key_exists($forbidden, $permutation->toArray()["components"])){ throw new InvalidArgumentException("Multi-block permutation cannot contain " . $forbidden); }
				}
			}
			foreach($this->traits as $trait){
				if($trait instanceof MultiBlockTrait){ continue; }
				if(!$trait instanceof PlacementDirectionTrait || array_map(static fn(BlockState $s) => $s->getName(), $trait->getStates()) !== [PlacementDirectionTrait::CARDINAL_DIRECTION]){
					throw new InvalidArgumentException("Multi-block only supports cardinal placement direction");
				}
			}
		}
		foreach($this->traits as $trait){
			foreach($trait->getStates() as $state){
				$this->addTraitState($state);
			}
		}
		$this->traitsInitialized = true;
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
			$player,
			$this
		);
		foreach($this->traits as $trait){
			$trait->applyPlacement($context);
		}
		return true;
	}

	public function updateTraitState(): void {
		$this->initializeTraits();
		$context = new BlockTraitContext(
			new BlockTransaction($this->getPosition()->getWorld()),
			$this->asItem(), $this, $this, 0, Vector3::zero(), null, $this
		);
		foreach($this->traits as $trait){ $trait->update($context); }
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