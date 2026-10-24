<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\states;

trait BlockStatesTrait {

	/**
	 * @var BlockState[] Array of registered block states
	 */
	private array $states = [];

	/**
	 * Adds a state to the block.
	 * @param BlockState $state
	 */
	public function addState(BlockState $state): void {
		if(isset($this->states[$state->getName()])){
			throw new \InvalidArgumentException("Duplicate block state: " . $state->getName());
		}
		$this->states[$state->getName()] = $state;
	}

	/**
	 * Checks whether the block has a state with the given name.
	 * @param string $name
	 * @return bool
	 */
	public function hasState(string $name): bool {
		return isset($this->states[$name]);
	}

	/**
	 * Retrieves a state by its name.
	 * @param string $name
	 * @return BlockState|null
	 */
	public function getState(string $name): ?BlockState {
		return $this->states[$name] ?? null;
	}

	/**
	 * Returns all registered block states.
	 * @return BlockState[]
	 */
	public function getStates(): array {
		return $this->states;
	}
	public function getCurrentStates(): array {
		return array_values(array_map(static fn(BlockState $state) => $state->getCurrentValue(), $this->states));
	}

	public function serializeState(\pocketmine\data\bedrock\block\convert\BlockStateWriter $out): void {
		foreach($this->states as $state){
			$name = $state->getName();
			$value = $state->getCurrentValue();
			match(true){
				is_bool($value) => $out->writeBool($name, $value),
				is_int($value) => $out->writeInt($name, $value),
				default => $out->writeString($name, $value)
			};
		}
	}

	public function deserializeState(\pocketmine\data\bedrock\block\convert\BlockStateReader $in): void {
		foreach($this->states as $state){
			$name = $state->getName();
			$default = $state->getValues()[0];
			$state->setCurrentValue(match(true){
				is_bool($default) => $in->readBool($name),
				is_int($default) => $in->readInt($name),
				default => $in->readString($name)
			});
		}
	}
}