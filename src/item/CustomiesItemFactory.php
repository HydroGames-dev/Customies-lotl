<?php
declare(strict_types=1);

namespace customiesdevs\customies\item;

use Closure;
use customiesdevs\customies\network\ProtocolRegistry;
use customiesdevs\customies\util\NBT;
use customiesdevs\customies\util\RegistrationGuard;
use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use ReflectionClass;
use function array_values;
use function count;
use function spl_object_id;

final class CustomiesItemFactory {
	use SingletonTrait;

	/** Default values for item_properties */
	private const PROPERTY_DEFAULTS = [
		'allow_off_hand' => false, // Byte
		'can_destroy_in_creative' => true, // Byte
		'damage' => 0, // Int
		'enchantable_slot' => 'none', // String
		'enchantable_value' => 0, // Int
		'foil' => false, // Byte
		'frame_count' => 1, // Int
		'hand_equipped' => false, // Byte
		'liquid_clipped' => false, // Byte
		'max_stack_size' => 64, // Int
		'mining_speed' => 1.0, // Float
		'should_despawn' => true, // Byte
		'stacked_by_data' => false, // Byte
		'use_animation' => 0, // Int
		'use_duration' => 0, // Int
	];

	/** Order in which properties should appear in item_properties */
	private const PROPERTY_ORDER = [
		'allow_off_hand', // Byte
		'can_destroy_in_creative', // Byte
		'creative_category', // Int
		'creative_group', // String
		'damage', // Int
		'enchantable_slot', // String
		'enchantable_value', // Int
		'foil', // Byte
		'frame_count', // Int
		'hand_equipped', // Byte
		'hidden_in_commands', // Byte
		'hover_text_color', // String
		'liquid_clipped', // Byte
		'max_stack_size', // Int
		'minecraft:icon', // Compound|String
		'mining_speed', // Float
		'should_despawn', // Byte
		'stacked_by_data', // Byte
		'use_animation', // Int
		'use_duration', // Int
	];

	/** @var array<string, ItemTypeEntry> */
	private array $itemTableEntries = [];
	/**
	 * Item type dictionaries carrying the custom entries, by object ID. Multi-protocol hosts have one per client
	 * item palette; custom network IDs start far above every vanilla ID, so one ID serves all of them.
	 * @var array<int, ItemTypeDictionary>
	 */
	private array $dictionaries = [];

	public function __construct() {
		ProtocolRegistry::onEveryTypeConverter(fn(TypeConverter $converter) => $this->attach($converter->getItemTypeDictionary()));
	}

	/**
	 * Get a custom item from its identifier. An exception will be thrown if the item is not registered.
	 * 
	 * @param string $identifier The string identifier for the item, usually in the format "namespace:item_name"
	 * @param int $amount The amount of the item to be returned
	 * @return Item The item instance
	 * @throws InvalidArgumentException if the item is not registered
	 */
	public function get(string $identifier, int $amount = 1): Item {
		$item = StringToItemParser::getInstance()->parse($identifier);
		if($item === null){
			throw new InvalidArgumentException("Custom item " . $identifier . " is not registered");
		}
		return $item->setCount($amount);
	}

	/**
	 * Returns all registered item table entries.
	 * 
	 * @return ItemTypeEntry[]
	 */
	public function getItemTableEntries(): array {
		return array_values($this->itemTableEntries);
	}

	/** Validate before changing any global registries. Block registration uses this too. @internal */
	public function assertCanRegister(string $identifier, int $networkId): void {
		RegistrationGuard::assertOpen($identifier);
		RegistrationGuard::validateIdentifier($identifier);
		if(StringToItemParser::getInstance()->parse($identifier) !== null){
			throw new InvalidArgumentException("Item '$identifier' already has a parser alias. Check duplicate content plugins before registering it.");
		}
		if($networkId < -32768 || $networkId > 32767){
			throw new InvalidArgumentException("Item '$identifier' has network ID $networkId outside the signed 16-bit registry range.");
		}
		foreach($this->dictionaries as $dictionary){
			foreach($dictionary->getEntries() as $entry){
				if($entry->getStringId() === $identifier || $entry->getNumericId() === $networkId){
					throw new InvalidArgumentException("Item '$identifier' conflicts with registered item '{$entry->getStringId()}' (network ID {$entry->getNumericId()}). Allocate a unique type ID.");
				}
			}
		}
	}

	/** Number of distinct item type dictionaries carrying the custom entries. */
	public function getDictionaryCount(): int {
		return count($this->dictionaries);
	}

	/**
	 * Registers the item to the item factory and assigns it an ID. It also updates the required mappings and stores the
	 * item components if present.
	 * 
	 * @param Closure $itemFunc A closure that returns an instance of the item to be registered
	 * @param string $identifier The string identifier for the item, usually in the format "namespace:item_name"
	 * @param CreativeInventoryInfo $creativeInfo Creative inventory information for the item. Default set to `Equipment` Category.
	 * @throws InvalidArgumentException if the closure does not return an Item instance
	 */
	public function registerItem(
		Closure $itemFunc, 
		string $identifier, 
		CreativeInventoryInfo $creativeInfo = new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_EQUIPMENT)
	): void {
		RegistrationGuard::assertOpen($identifier);
		RegistrationGuard::validateIdentifier($identifier);
		if(isset($this->itemTableEntries[$identifier])){ throw new InvalidArgumentException("Item already registered: " . $identifier); }
		$item = $itemFunc();
		if(!$item instanceof Item){
			throw new InvalidArgumentException("Class returned from closure is not a Item");
		}
		$itemId = $item->getTypeId();
		$this->assertCanRegister($identifier, $itemId);
		// Build before mapping, so malformed component data cannot leave a half-registered item.
		$nbt = $this->createItemNbt($item, $identifier, $itemId, $creativeInfo);

		GlobalItemDataHandlers::getDeserializer()->map($identifier, fn() => clone $item);
		GlobalItemDataHandlers::getSerializer()->map($item, fn() => new SavedItemData($identifier));
		StringToItemParser::getInstance()->register($identifier, fn() => clone $item);

		// Adding item components
		$componentBased = $item instanceof ItemComponents;
		// Registers the item to creative inventory
		CreativeInventoryInfo::registerCreativeInfo($item, $creativeInfo);
		// Create the NBT data for the item
		$entry = new ItemTypeEntry(
			$identifier,
			$itemId,
			$componentBased,
			$componentBased ? 1 : 0,
			new CacheableNbt($nbt)
		);
		$this->itemTableEntries[$identifier] = $entry;
		$this->registerCustomItemMapping($identifier, $itemId, $entry);
	}

	/**
	 * Creates the CompoundTag for an item, including components and default properties.
	 */
	private function createItemNbt(Item $item, string $identifier, int $itemId, CreativeInventoryInfo $creativeInfo): CompoundTag {
		// Initialize item_properties with defaults
		$propertiesTag = CompoundTag::create();
		foreach(self::PROPERTY_DEFAULTS as $name => $default){
			$propertiesTag->setTag($name, NBT::getTagType($default));
		}
		// Set creative info
		$propertiesTag->setTag('creative_category', NBT::getTagType((int) $creativeInfo->getNumericCategory()));
		$propertiesTag->setTag('creative_group', NBT::getTagType((string) $creativeInfo->getGroup()));
		$propertiesTag->setByte("hidden_in_commands", 2);
		$tags = [];
		$componentsTag = CompoundTag::create();
		// Process each component
		if($item instanceof ItemComponents){
			foreach($item->getComponents() as $component){
				$name = $component->getName();
				$value = $component->getValue();
				$tag = NBT::getTagType($value) ?? throw new InvalidArgumentException("Component '$name' has an unsupported NBT value.");
				// Icon goes to item_properties
				if($name === 'minecraft:icon'){
					$propertiesTag->setTag('minecraft:icon', $tag);
					continue;
				}
				// Tags go to item_tags
				if($name === 'minecraft:tags'){
					$tags = $value['tags'] ?? [];
					$componentsTag->setTag('minecraft:tags', $tag);
					continue;
				}
				// Components with property mappings also update item_properties
				$mapping = $component->getPropertyMapping();
				if($mapping !== null){
					foreach($mapping as $prop => $propValue){
						if($prop === "use_duration"){
							$propertiesTag->setTag("use_duration", NBT::getTagType((int) round($propValue * 20)));
							continue;
						}
						$propertiesTag->setTag($prop, NBT::getTagType($propValue));
					}
				}
				// All components go to components tag
				$componentsTag->setTag($name, $tag);
			}
		}
		$propertiesTag = NBT::sortCompoundTag($propertiesTag, self::PROPERTY_ORDER);
		$components = CompoundTag::create()
			->setTag('item_properties', $propertiesTag)
			->setTag('item_tags', new ListTag(array_map(static fn(string $tag) => new StringTag($tag), array_values($tags))))
			->merge($componentsTag);

		return CompoundTag::create()
			->setTag('components', $components)
			->setInt('id', $itemId)
			->setString('name', $identifier);
	}

	/**
	 * Registers a custom item ID in every item type dictionary, so each client version can resolve it.
	 * @param string $identifier The string identifier for the item, usually in the format "namespace:item_name"
	 * @param int $itemId The numerical ID to be assigned to the item
	 * @param ItemTypeEntry $entry The ItemTypeEntry instance representing the item
	 */
	private function registerCustomItemMapping(string $identifier, int $itemId, ItemTypeEntry $entry): void {
		foreach($this->dictionaries as $dictionary){
			self::inject($dictionary, $entry);
		}
	}

	/** Adds every custom entry to a dictionary seen for the first time (a client version's palette loaded later). */
	private function attach(ItemTypeDictionary $dictionary): void {
		$key = spl_object_id($dictionary);
		if(isset($this->dictionaries[$key])){ return; }
		$this->dictionaries[$key] = $dictionary;
		foreach($this->itemTableEntries as $entry){
			self::inject($dictionary, $entry);
		}
	}

	private static function inject(ItemTypeDictionary $dictionary, ItemTypeEntry $entry): void {
		$reflection = new ReflectionClass($dictionary);
		$intToString = $reflection->getProperty("intToStringIdMap");
		$stringToInt = $reflection->getProperty("stringToIntMap");
		$itemTypes = $reflection->getProperty("itemTypes");
		/** @var array<int, string> $intToStringValue */
		$intToStringValue = $intToString->getValue($dictionary);
		/** @var array<string, int> $stringToIntValue */
		$stringToIntValue = $stringToInt->getValue($dictionary);
		$identifier = $entry->getStringId();
		$itemId = $entry->getNumericId();
		if(isset($stringToIntValue[$identifier])){ return; }
		if(isset($intToStringValue[$itemId])){
			// Registration validated the dictionaries that existed then. A client palette loaded later is checked
			// here; failing a player's login would be worse than leaving one item out of one version.
			\GlobalLogger::get()->error("[Customies] Item '$identifier' (network ID $itemId) conflicts with '{$intToStringValue[$itemId]}' in a client item palette; players on that version cannot use it.");
			return;
		}
		$intToString->setValue($dictionary, $intToStringValue + [$itemId => $identifier]);
		$stringToInt->setValue($dictionary, $stringToIntValue + [$identifier => $itemId]);
		/** @var ItemTypeEntry[] $itemTypesValue */
		$itemTypesValue = $itemTypes->getValue($dictionary);
		$itemTypesValue[] = $entry;
		$itemTypes->setValue($dictionary, $itemTypesValue);
	}

	/**
	 * Registers the required mappings for the block to become an item that can be placed etc. It is assigned an ID that
	 * correlates to its block ID.
	 * @param string $identifier The string identifier for the block item, usually in the format "namespace:block_name"
	 * @param Block $block The block instance to be registered as an item
	 */
	public function registerBlockItem(string $identifier, Block $block): void {
		if(isset($this->itemTableEntries[$identifier])){ throw new InvalidArgumentException("Item already registered: " . $identifier); }
		$itemId = $block->getIdInfo()->getBlockTypeId();
		$this->assertCanRegister($identifier, $itemId);
		// Some plugin inventories retain only Name/meta/NBT instead of a Block tag.
		// Native full block-state data still goes through the block deserializer.
		GlobalItemDataHandlers::getDeserializer()->map($identifier, static fn() => $block->asItem());
		StringToItemParser::getInstance()->registerBlock($identifier, fn() => clone $block);
		$entry = new ItemTypeEntry($identifier, $itemId, false, 2, new CacheableNbt(CompoundTag::create()));

		$this->itemTableEntries[$identifier] = $entry;
		$this->registerCustomItemMapping($identifier, $itemId, $entry);

		$blockItemIdMap = BlockItemIdMap::getInstance();
		$reflection = new ReflectionClass($blockItemIdMap);

		$itemToBlockId = $reflection->getProperty("itemToBlockId");
		 /** @var array<string, string> $itemToBlockIdValue */
		$itemToBlockIdValue = $itemToBlockId->getValue($blockItemIdMap);
		$itemToBlockId->setValue($blockItemIdMap, $itemToBlockIdValue + [$identifier => $identifier]);
		$blockToItemId = $reflection->getProperty("blockToItemId");
		$blockToItemId->setValue($blockItemIdMap, $blockToItemId->getValue($blockItemIdMap) + [$identifier => $identifier]);
	}
}
