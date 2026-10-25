<?php
declare(strict_types=1);
namespace customiesdevs\showcase;

use customiesdevs\customies\block\component as B;
use customiesdevs\customies\item\component as I;
use customiesdevs\customies\block\properties\{Material, PlacementCondition, AllowedFace, BlockDescriptor};
use customiesdevs\customies\item\properties\{RepairItems, RepairAmount, SoundEvent, DamageCause};
use pocketmine\math\Vector3;

/** One explicit, editable constructor example for EVERY shipped component. */
final class ComponentCatalog {
	/** @return array<string, B\BlockComponent> */
	public static function blocks(): array {
		$geometry = new B\GeometryComponent('geometry.showcase.cube');
		$materials = [new Material('*', 'showcase_lab')];
		return [
			'block_entity' => new B\BlockEntityComponent(true, 9),
			'block_tags' => new B\BlockTagsComponent(['showcase:specimen', 'minecraft:stone']),
			'chest_obstruction' => new B\ChestObstructionComponent('shape'),
			'collision_box' => new B\CollisionBoxComponent(false),
			'connection_rule' => new B\ConnectionRuleComponent('all'),
			'crafting_table' => new B\CraftingTableComponent(['crafting_table'], 'Showcase Workbench'),
			'destructible_by_explosion' => new B\DestructibleByExplosionComponent(12.0),
			'destructible_by_mining' => new B\DestructibleByMiningComponent(1.5),
			'destruction_particles' => new B\DestructionParticlesComponent(24, 'showcase_lab'),
			'display_name' => new B\DisplayNameComponent('Display Name Specimen'),
			'embedded_visual' => new B\EmbeddedVisualComponent($geometry, $materials),
			'entity_fall_on' => new B\EntityFallOnComponent(2.0),
			'flammable' => new B\FlammableComponent(5, 20, true),
			'flower_pottable' => new B\FlowerPottableComponent(),
			'friction' => new B\FrictionComponent(0.02),
			'geometry' => $geometry,
			'instrument_sound' => new B\InstrumentSoundComponent('note.pling', 'note.bass'),
			'item_visual' => new B\ItemVisualComponent($geometry, $materials),
			'leashable' => new B\LeashableComponent([0.0, 0.5, 0.0]),
			'light_dampening' => new B\LightDampeningComponent(0),
			'light_emission' => new B\LightEmissionComponent(15),
			'liquid_detection' => new B\LiquidDetectionComponent(true),
			'map_color' => new B\MapColorComponent('#39d8ce'),
			'material_instances' => new B\MaterialInstancesComponent($materials),
			'movable' => new B\MovableComponent('immovable'),
			'placement_filter' => new B\PlacementFilterComponent([new PlacementCondition([AllowedFace::UP], [BlockDescriptor::fromArray(['name' => 'minecraft:stone'])])]),
			'precipitation_interactions' => new B\PrecipitationInteractionsComponent(),
			'random_offset' => new B\RandomOffsetComponent(new Vector3(-0.1, 0, -0.1), new Vector3(0.1, 0, 0.1), new Vector3(0.1, 0, 0.1)),
			'replaceable' => new B\ReplaceableComponent(),
			'selection_box' => new B\SelectionBoxComponent(true, new Vector3(-6, 0, -6), new Vector3(12, 12, 12)),
			'sound' => new B\SoundComponent('stone'),
			'support' => new B\SupportComponent('fence'),
			'transformation' => new B\TransformationComponent(new Vector3(0, 90, 0), scale: new Vector3(0.75, 0.75, 0.75))
		];
	}

	/** @return array<string, I\ItemComponent> */
	public static function items(): array {
		return [
			'allow_off_hand' => new I\AllowOffHandComponent(),
			'bundle_interaction' => new I\BundleInteractionComponent(8),
			'can_destroy_in_creative' => new I\CanDestroyInCreativeComponent(false),
			'compostable' => new I\CompostableComponent(65),
			'cooldown' => new I\CooldownComponent('showcase:cooldown', 2.0),
			'damage_absorption' => new I\DamageAbsorptionComponent([DamageCause::FALL]),
			'damage' => new I\DamageComponent(6),
			'digger' => new I\DiggerComponent(true, [['block' => ['name' => 'minecraft:stone'], 'speed' => 6]]),
			'display_name' => new I\DisplayNameComponent('Display Name Specimen'),
			'durability' => new I\DurabilityComponent(256),
			'durability_sensor' => new I\DurabilitySensorComponent([['durability' => 32, 'sound_event' => SoundEvent::ATTACK_CRITICAL]]),
			'dyeable' => new I\DyeableComponent('#39d8ce'),
			'enchantable' => new I\EnchantableComponent('sword', 12),
			'fire_resistant' => new I\FireResistantComponent(),
			'food' => new I\FoodComponent(false, 6, 0.6),
			'fuel' => new I\FuelComponent(20.0),
			'glint' => new I\GlintComponent(),
			'hand_equipped' => new I\HandEquippedComponent(),
			'hover_text_color' => new I\HoverTextColorComponent('aqua'),
			'icon' => new I\IconComponent('showcase_prism'),
			'interact_button' => new I\InteractButtonComponent('Inspect'),
			'item_tags' => new I\ItemTagsComponent(['showcase:specimen']),
			'kinetic_weapon' => new I\KineticWeaponComponent(),
			'liquid_clipped' => new I\LiquidClippedComponent(),
			'max_stack_size' => new I\MaxStackSizeComponent(16),
			'piercing_weapon' => new I\PiercingWeaponComponent(),
			'projectile' => new I\ProjectileComponent(0.25, 'minecraft:snowball'),
			'rarity' => new I\RarityComponent('rare'),
			'record' => new I\RecordComponent(5, 1.0, 'showcase.chime'),
			'repairable' => new I\RepairableComponent([new RepairItems(['minecraft:amethyst_shard'], RepairAmount::numeric(64))]),
			'shooter' => new I\ShooterComponent('minecraft:arrow', searchInventory: true, chargeOnDraw: true, maxDrawDuration: 1.0, scalePowerByDrawDuration: true),
			'should_despawn' => new I\ShouldDespawnComponent(false),
			'stacked_by_data' => new I\StackedByDataComponent(),
			'storage_item' => new I\StorageItemComponent(false, 16),
			'storage_weight_limit' => new I\StorageWeightLimitComponent(64),
			'storage_weight_modifier' => new I\StorageWeightModifierComponent(8),
			'swing_duration' => new I\SwingDurationComponent(0.5),
			'swing_sounds' => new I\SwingSoundsComponent(),
			'throwable' => new I\ThrowableComponent(true, 1.0, 1.0, 1.5),
			'use_animation' => new I\UseAnimationComponent('drink'),
			'use_modifiers' => new I\UseModifiersComponent(0.5, 1.6),
			'wearable' => new I\WearableComponent(I\WearableComponent::SLOT_ARMOR_HEAD, 2)
		];
	}
}
