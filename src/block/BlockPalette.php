<?php
declare(strict_types=1);

namespace customiesdevs\customies\block;

use customiesdevs\customies\network\BlockPaletteBuilder;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use ReflectionProperty;
use RuntimeException;
use function array_keys;
use function count;

final class BlockPalette {
	use SingletonTrait;

	/** @var BlockStateDictionaryEntry[] */
	private array $states = [];
	/** @var BlockStateDictionaryEntry[] */
	private array $customStates = [];

	private BlockTranslator $translator;
	private ReflectionProperty $bedrockKnownStates;
	private ReflectionProperty $stateDataToStateIdLookup;
	private ReflectionProperty $idMetaToStateIdLookupCache;
	private ReflectionProperty $fallbackStateId;
	private ReflectionProperty $networkIdCache;

	public function __construct() {
		$this->translator = $instance = TypeConverter::getInstance()->getBlockTranslator();
		$dictionary = $instance->getBlockStateDictionary();
		$this->states = $dictionary->getStates();

		$this->bedrockKnownStates = new ReflectionProperty($dictionary, "states");
		$this->stateDataToStateIdLookup = new ReflectionProperty($dictionary, "stateDataToStateIdLookup");
		$this->idMetaToStateIdLookupCache = new ReflectionProperty($dictionary, "idMetaToStateIdLookupCache");
		$this->fallbackStateId = new ReflectionProperty($instance, "fallbackStateId");
		$this->networkIdCache = new ReflectionProperty($instance, "networkIdCache");
	}

	/**
	 * Returns all block states in the palette.
	 * @return BlockStateDictionaryEntry[]
	 */
	public function getStates(): array {
		return $this->states;
	}

	/**
	 * Returns all custom block states.
	 * @return BlockStateDictionaryEntry[]
	 */
	public function getCustomStates(): array {
		return $this->customStates;
	}

	/**
	 * Inserts the provided state in to the correct position of the palette.
	 * @param CompoundTag $state
	 * @param int $meta
	 */
	public function insertState(CompoundTag $state, int $meta = 0): void {
		$this->insertStates([[$state, $meta]]);
	}

	/** @param array<array{CompoundTag, int}> $states */
	public function insertStates(array $states): void {
		$entries = [];
		foreach($states as [$state, $meta]){
			$name = $state->getString(BlockStateData::TAG_NAME, "");
			$properties = $state->getCompoundTag(BlockStateData::TAG_STATES);
			if($name === "" || $properties === null){ throw new RuntimeException("Block states require name and states tags"); }
			$entries[] = new BlockStateDictionaryEntry($name, $properties->getValue(), $meta);
		}
		if($entries === []){ return; }
		$this->sortWith($entries);
		array_push($this->customStates, ...$entries);
	}

	/**
	 * Sorts the palette's block states in the correct order, also adding the provided state to the array.
	 * @param list<BlockStateDictionaryEntry> $newStates
	 */
	private function sortWith(array $newStates): void {
		// To sort the block palette we first have to split the palette up in to groups of states. We only want to sort
		// using the name of the block, and keeping the order of the existing states.
		/** @var array<string, BlockStateDictionaryEntry[]> $states */
		$states = [];
		foreach($this->states as $state){
			$states[$state->getStateName()][] = $state;
		}
		// Append the new state we are sorting with at the end to preserve existing order.
		foreach($newStates as $newState){ $states[$newState->getStateName()][] = $newState; }

		// As of 1.18.30, blocks are sorted using a fnv164 hash of their names.
		$names = BlockPaletteBuilder::sortNames(array_keys($states));
		$sortedStates = [];
		$stateId = 0;
		$stateDataToStateIdLookup = [];
		foreach($names as $name){
			// With the sorted list of names, we can now go back and add all the states for each block in the correct order.
			foreach($states[$name] as $state){
				$sortedStates[$stateId] = $state;
				if(count($states[$name]) === 1){
					$stateDataToStateIdLookup[$name] = $stateId;
				}else{
					$stateDataToStateIdLookup[$name][$state->getRawStateProperties()] = $stateId;
				}
				$stateId++;
			}
		}
		$this->states = $sortedStates;
		$dictionary = $this->translator->getBlockStateDictionary();
		$this->bedrockKnownStates->setValue($dictionary, $sortedStates);
		$this->stateDataToStateIdLookup->setValue($dictionary, $stateDataToStateIdLookup);
		$this->idMetaToStateIdLookupCache->setValue($dictionary, null); //set this to null so pm can create a new cache
		// Sorting changes IDs for existing blocks too. Any cached translation now refers to the old palette.
		$this->networkIdCache->setValue($this->translator, []);
		$this->fallbackStateId->setValue($this->translator, $stateDataToStateIdLookup[BlockTypeNames::INFO_UPDATE] ??
			throw new AssumptionFailedError(BlockTypeNames::INFO_UPDATE . " should always exist")
		);
	}
}
