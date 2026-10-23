<?php
declare(strict_types=1);

namespace customiesdevs\customies\block\component;

final class CraftingTableComponent implements BlockComponent {

	/** @param list<string> $craftingTags Recipe tags accepted by this table. */
	public function __construct(private array $craftingTags, private string $tableName = '') {
		if(count($craftingTags) > 64 || strlen($tableName) > 256){
			throw new \InvalidArgumentException('Crafting tables support at most 64 tags and a 256-character name');
		}
		foreach($craftingTags as $tag){
			if(!is_string($tag) || strlen($tag) > 64){
				throw new \InvalidArgumentException('Crafting tags must be strings of at most 64 characters');
			}
		}
		$this->craftingTags = array_values(array_unique($craftingTags));
	}

	public function getName(): string {
		return 'minecraft:crafting_table';
	}

	public function getValue(): array {
		$data = ['table_name' => $this->tableName];
		// An explicit list is needed because [] otherwise denotes an empty NBT compound.
		$data['crafting_tags'] = $this->craftingTags === [] ? new \pocketmine\nbt\tag\ListTag() : $this->craftingTags;
		return $data;
	}
}