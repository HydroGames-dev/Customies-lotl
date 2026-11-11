<?php
declare(strict_types=1);

namespace customiesdevs\customies\block;

use customiesdevs\customies\network\BlockPaletteBuilder;
use customiesdevs\customies\network\ProtocolRegistry;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\network\mcpe\convert\BlockTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\SingletonTrait;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use function array_keys;
use function count;
use function property_exists;
use function array_push;
use function spl_object_id;

/**
 * Inserts custom block states into every block state dictionary the host uses.
 *
 * Clients number block states by sorting block names by FNV-1a 64 hash, so each dictionary is re-sorted after an
 * insertion. Multi-protocol hosts keep one dictionary per client palette and store older palettes under current
 * block names; those palettes are sorted by the names their clients use, not by the current names.
 */
final class BlockPalette {
	use SingletonTrait;

	/** @var list<BlockStateDictionaryEntry> */
	private array $customStates = [];
	/**
	 * Per dictionary: its translator, the host's vanilla states in host order and their network names.
	 * @var array<int, array{translator: BlockTranslator, vanilla: list<BlockStateDictionaryEntry>, names: list<string>}>
	 */
	private array $targets = [];

	private ReflectionProperty $states;
	private ReflectionProperty $stateDataToStateIdLookup;
	private ReflectionProperty $idMetaToStateIdLookupCache;
	private ReflectionProperty $fallbackStateId;
	private ReflectionProperty $networkIdCache;
	/** Multi-protocol hosts keep the original state of upgraded entries; null for current-only hosts. */
	private ?ReflectionProperty $oldBlockStateData;
	private ?ReflectionProperty $addedPropertyDefaults;
	private bool $entryTakesOldState;

	public function __construct() {
		$this->states = new ReflectionProperty(BlockStateDictionary::class, "states");
		$this->stateDataToStateIdLookup = new ReflectionProperty(BlockStateDictionary::class, "stateDataToStateIdLookup");
		$this->idMetaToStateIdLookupCache = new ReflectionProperty(BlockStateDictionary::class, "idMetaToStateIdLookupCache");
		$this->fallbackStateId = new ReflectionProperty(BlockTranslator::class, "fallbackStateId");
		$this->networkIdCache = new ReflectionProperty(BlockTranslator::class, "networkIdCache");
		$this->oldBlockStateData = property_exists(BlockStateDictionaryEntry::class, "oldBlockStateData") ? new ReflectionProperty(BlockStateDictionaryEntry::class, "oldBlockStateData") : null;
		$this->addedPropertyDefaults = property_exists(BlockStateDictionary::class, "addedPropertyDefaults") ? new ReflectionProperty(BlockStateDictionary::class, "addedPropertyDefaults") : null;
		$this->entryTakesOldState = (new ReflectionMethod(BlockStateDictionaryEntry::class, "__construct"))->getNumberOfParameters() >= 4;
		ProtocolRegistry::onEveryTypeConverter(fn(TypeConverter $converter) => $this->attach($converter->getBlockTranslator()));
	}

	/**
	 * Returns all block states in the current protocol's palette.
	 * @return BlockStateDictionaryEntry[]
	 */
	public function getStates(): array {
		return TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary()->getStates();
	}

	/**
	 * Returns all custom block states.
	 * @return BlockStateDictionaryEntry[]
	 */
	public function getCustomStates(): array {
		return $this->customStates;
	}

	/** Number of distinct block state dictionaries carrying the custom states. */
	public function getDictionaryCount(): int {
		return count($this->targets);
	}

	/**
	 * Inserts the provided state in to the correct position of the palette.
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
			// Custom states are identical in every client version, so they never carry an older form.
			$entries[] = $this->entryTakesOldState
				? new BlockStateDictionaryEntry($name, $properties->getValue(), $meta, null)
				: new BlockStateDictionaryEntry($name, $properties->getValue(), $meta);
		}
		if($entries === []){ return; }
		array_push($this->customStates, ...$entries);
		foreach($this->targets as $target){
			$this->rebuild($target);
		}
	}

	private function attach(BlockTranslator $translator): void {
		$dictionary = $translator->getBlockStateDictionary();
		$key = spl_object_id($dictionary);
		if(isset($this->targets[$key])){ return; }
		$vanilla = [];
		$names = [];
		foreach($dictionary->getStates() as $state){
			$vanilla[] = $state;
			/** @var BlockStateData|null $old */
			$old = $this->oldBlockStateData?->getValue($state);
			$names[] = $old?->getName() ?? $state->getStateName();
		}
		$this->targets[$key] = ['translator' => $translator, 'vanilla' => $vanilla, 'names' => $names];
		if($this->customStates !== []){
			$this->rebuild($this->targets[$key]);
		}
	}

	/** @param array{translator: BlockTranslator, vanilla: list<BlockStateDictionaryEntry>, names: list<string>} $target */
	private function rebuild(array $target): void {
		// Group states by the name the client sees, keeping the host's order inside each group, then sort the
		// groups as the client does. Custom names never collide with vanilla names.
		/** @var array<string, list<BlockStateDictionaryEntry>> $groups */
		$groups = [];
		foreach($target['vanilla'] as $i => $state){
			$groups[$target['names'][$i]][] = $state;
		}
		foreach($this->customStates as $state){
			$groups[$state->getStateName()][] = $state;
		}
		$sorted = [];
		foreach(BlockPaletteBuilder::sortNames(array_keys($groups)) as $name){
			array_push($sorted, ...$groups[$name]);
		}

		$translator = $target['translator'];
		$dictionary = $translator->getBlockStateDictionary();
		// Let the host build its own lookup table (including aliases such as NG's skull fallbacks) for the new order,
		// then move it into the dictionary that item translators and chunk encoders already reference.
		$rebuilt = $this->addedPropertyDefaults === null
			? new BlockStateDictionary($sorted)
			: new BlockStateDictionary($sorted, $this->addedPropertyDefaults->getValue($dictionary));
		$this->states->setValue($dictionary, $sorted);
		$this->stateDataToStateIdLookup->setValue($dictionary, $this->stateDataToStateIdLookup->getValue($rebuilt));
		$this->idMetaToStateIdLookupCache->setValue($dictionary, null); // rebuilt lazily by the host
		// Sorting changes IDs for existing blocks too. Any cached translation now refers to the old palette.
		$this->networkIdCache->setValue($translator, []);
		$this->fallbackStateId->setValue($translator, $dictionary->lookupStateIdFromData(BlockStateData::current(BlockTypeNames::INFO_UPDATE, [])) ??
			throw new AssumptionFailedError(BlockTypeNames::INFO_UPDATE . " should always exist")
		);
	}
}
