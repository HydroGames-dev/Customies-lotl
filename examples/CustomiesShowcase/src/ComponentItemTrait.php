<?php
declare(strict_types=1);
namespace customiesdevs\showcase;
use customiesdevs\customies\item\ItemComponentsTrait;
use customiesdevs\customies\item\preset\ItemPresets;
use customiesdevs\customies\item\component as I;

trait ComponentItemTrait {
	use ItemComponentsTrait;
	private string $specimen;
	protected function configureComponents(string $specimen, string $name, int $stack = 64): void {
		$this->specimen = $specimen;
		ItemPresets::basic($this, 'showcase_' . (str_starts_with($specimen, 'lab_') ? 'lab_item_' . substr($specimen,4) : $specimen), $name, $stack);
		$this->addComponent(new I\ItemTagsComponent(['showcase:specimen', 'showcase:' . $specimen]));
		if(str_starts_with($specimen, 'lab_')){
			$key = substr($specimen,4);
			// Supply required companion metadata without mixing incompatible laboratory components.
			if(in_array($key, ['durability_sensor','damage_absorption','repairable'],true)){ $this->addComponent(new I\DurabilityComponent(256)); }
			if($key === 'damage_absorption'){ $this->addComponent(new I\WearableComponent(I\WearableComponent::SLOT_ARMOR_HEAD)); }
			if($key === 'food'){ $this->addComponent(new I\UseAnimationComponent('eat')); $this->addComponent(new I\UseModifiersComponent(0.5,1.6)); }
			if($key === 'shooter'){ $this->addComponent(new I\UseAnimationComponent('bow')); }
			if($key === 'throwable'){ $this->addComponent(new I\ProjectileComponent(0.25,'minecraft:snowball')); }
			if(in_array($key,['bundle_interaction','storage_weight_limit','storage_weight_modifier'],true)){ $this->addComponent(new I\StorageItemComponent(false,16)); }
			$this->addComponent(ComponentCatalog::items()[$key]);
		}
		$this->setLore(SpecimenDescriptions::item($specimen));
	}
	public function getSpecimen(): string { return $this->specimen; }
}
