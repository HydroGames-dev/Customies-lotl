<?php
declare(strict_types=1);
namespace customiesdevs\showcase;

use pocketmine\utils\TextFormat as F;

/** Shared by commands, creative items, block drops, and every custom item constructor. */
final class SpecimenDescriptions {
	/** @var array<string, list<string>> */
	private const BLOCKS = [
		'signal_lamp' => ['A four-way lamp with four brightness settings.','Interact to cycle light levels 0, 5, 10 and 15.'],
		'lumen_cable' => ['A connecting cable with switchable illumination.','Interact to toggle light; joins Cable and Lumen Cable.'],
		'phase_gate' => ['A directional gate with a luminous open state.','Interact to open/close; open gates have no collision.'],
		'spring_pad' => ['A switchable quarter-height bounce platform.','Interact to arm; land to bounce, sneak to stop.'],
		'prism_workbench' => ['A directional crystal crafting workbench.','Interact to open a real 3-by-3 crafting grid.'],
		'prism_lamp' => ['A crystal lamp with two power states.','Interact to switch its light between 0 and 15.'],
		'machine' => ['A machine with a marked front panel.','Faces you when placed; uses four-way rotation.'],
		'orienter' => ['A directional cube with six orientations.','Look up, down, or sideways while placing.'],
		'face_marker' => ['Marks the face against which it was placed.','Place against any of the six block faces.'],
		'slab' => ['A half-height building block.','Place high or low to choose its vertical half.'],
		'cable' => ['A connecting post with four optional arms.','Join posts together or place beside solid blocks.'],
		'corner_stairs' => ['Stairs with inner/outer corners and two halves.','Place high/low; matching halves join at corners.'],
		'totem' => ['A three-part vertical crystal totem.','Needs three free cells; break any part to remove.'],
		'bench' => ['A two-part, half-height horizontal structure.','Needs two free cells; break either part to remove.'],
		'rotor' => ['A directional pointer with 16 yaw positions.','Aim while placing, or cycle it with State Wrench.'],
		'phase_block' => ['A block with three material phases.','Interact to cycle; the third phase has no collision.'],
		'ice_tile' => ['A slippery tile for movement experiments.','Walk over a row to compare its friction.'],
		'glow_flower' => ['A luminous, replaceable crystal flower.','Emits light level 8 and can be walked through.']
	];
	/** @var array<string, list<string>> */
	private const ITEMS = [
		'scout_wand' => ['Use in the air for speed, jump and night vision.','Speed/Jump: 10s | Night vision: 30s | Cooldown: 5s'],
		'repair_kit' => ['Repairs a damaged durable item in your offhand.','Use in the air: consumes one kit, repairs up to 64.'],
		'energized_ration' => ['A filling travel ration with a movement boost.','Food: 10 | Saturation: 12 | Speed I: 15s'],
		'prism_elixir' => ['Drink even at full hunger; returns a glass bottle.','Resistance I: 10s | Night vision: 30s'],
		'frost_blade' => ['An iron-tier blade that chills living targets.','On hit: Slowness I for 2s; normal wear applies.'],
		'inspector' => ['Read the states and components of a block.','Use on a block; requires showcase permission.'],
		'state_wrench' => ['Adjust lamps, gates, rotors, and spring controls.','Use to cycle its state without replacing it.'],
		'pulse_wand' => ['Release a short burst of movement energy.','Use in the air: Speed I for 5s; cooldown 2s.'],
		'prism' => ['A rare crystal carrying per-stack data.','Can be held offhand; inspect its batch and quality.'],
		'ember_fuel' => ['A fireproof ember that fuels a furnace.','Burn time: 20 seconds / 400 ticks.'],
		'pigment' => ['A turquoise dyeable-component specimen.','Shows dye metadata; no dye-use action is added.'],
		'prism_berry' => ['A restorative berry with a crystal skin.','Food: 6 | Saturation: 7.2 | Regeneration I: 3s'],
		'prism_pickaxe' => ['An iron-tier pickaxe with a crystal head.','Mine stone; normal tool damage and wear apply.'],
		'prism_helmet' => ['A wearable crystal helmet with a custom model.','Armor: 2 | Durability: 256 | Slot: head'],
		'prism_pebble' => ['A throwable pebble using snowball physics.','Use in the air to throw. Stack limit: 16.']
	];
	/** @var array<string, list<string>> */
	private const BLOCK_COMPONENTS = [
		'block_entity' => ['Dynamic properties and nine client slots.','No backing server inventory tile is created.'],
		'block_tags' => ['Adds specimen and stone tags to the definition.','Use tags to organize or query block metadata.'],
		'chest_obstruction' => ['Advertises shape-based chest obstruction.','Compare it with full blocks above a chest.'],
		'collision_box' => ['Disables the collision-box component.','This specimen also has no server collision.'],
		'connection_rule' => ['Accepts connections on all four horizontal sides.','Client connection rules; no automatic server fence.'],
		'crafting_table' => ['Declares the crafting_table recipe tag.','Metadata only; no custom crafting window opens.'],
		'destructible_by_explosion' => ['Advertises explosion resistance 12.','Server blast resistance is not changed by this lab.'],
		'destructible_by_mining' => ['Advertises a 1.5-second client mining time.','Server hardness remains the shared lab value.'],
		'destruction_particles' => ['Requests 24 particles using the lab texture.','Compare the block-break visual effect.'],
		'display_name' => ['Overrides the block display-name component.','Its client label is Display Name Specimen.'],
		'embedded_visual' => ['Embeds cube geometry with a lab material.','A visual-definition specimen.'],
		'entity_fall_on' => ['Declares a minimum fall distance of two blocks.','No server fall-trigger action is attached.'],
		'flammable' => ['Catch chance: 5 | Destroy chance: 20','Includes lava-flammable client metadata.'],
		'flower_pottable' => ['Marks the block as a flower-pot candidate.','Server flower-pot insertion is not implemented.'],
		'friction' => ['Advertises low-friction block behavior.','The server also makes this specimen slippery.'],
		'geometry' => ['References the supplied showcase cube model.','A minimal custom-geometry example.'],
		'instrument_sound' => ['Declares pling and bass instrument sounds.','No server note-playing action is attached.'],
		'item_visual' => ['Supplies geometry and materials for item visuals.','Compare the held block with its placed model.'],
		'leashable' => ['Declares a leash offset half a block above base.','No server leash interaction is attached.'],
		'light_dampening' => ['Sets client light dampening to zero.','The server also uses a zero light filter.'],
		'light_emission' => ['Declares maximum emitted light: 15.','The server also lights nearby blocks.'],
		'liquid_detection' => ['Advertises the ability to contain water.','Server waterlogging is not implemented.'],
		'map_color' => ['Sets the block map color to turquoise.','Compare its representation on client maps.'],
		'material_instances' => ['Assigns the shared lab texture to every face.','A minimal material-instance example.'],
		'movable' => ['Declares immovable piston behavior.','Piston handling depends on server support.'],
		'placement_filter' => ['Declares placement on top of stone only.','The lab does not enforce this rule server-side.'],
		'precipitation_interactions' => ['Declares rain obstruction and snow accumulation.','Server weather behavior is not implemented.'],
		'random_offset' => ['Declares small horizontal model offsets.','No matching server position randomization.'],
		'replaceable' => ['Marks this specimen as replaceable.','A new block may replace it on the server too.'],
		'selection_box' => ['A centered selection box measuring 12 pixels.','Compare the cursor outline with the full cube.'],
		'sound' => ['Uses the stone block sound identifier.','A block-sound metadata example.'],
		'support' => ['Declares the fence support shape.','Server support rules remain unchanged.'],
		'transformation' => ['Rotates 90 degrees and scales visuals to 75%.','Server collision is still the shared lab cube.']
	];
	/** @var array<string, list<string>> */
	private const ITEM_COMPONENTS = [
		'allow_off_hand' => ['Allows the item in the offhand slot.','Compare it with an ordinary main-hand item.'],
		'bundle_interaction' => ['Declares eight visible bundle slots.','A client storage/UI specimen, not a server bundle.'],
		'can_destroy_in_creative' => ['Disables creative breaking in client metadata.','No server attack restriction is attached.'],
		'compostable' => ['Advertises a 65 percent composting chance.','No server composting action is attached.'],
		'cooldown' => ['Declares a two-second use cooldown.','Use Pulse Wand to test an actual server cooldown.'],
		'damage_absorption' => ['Declares fall-damage absorption when worn.','No server absorption handler is attached.'],
		'damage' => ['Attack damage: 6.','This specimen also overrides server attack damage.'],
		'digger' => ['Advertises digging speed 6 for stone.','Use Prism Pickaxe for actual server tool behavior.'],
		'display_name' => ['Overrides the client display-name component.','Its client label is Display Name Specimen.'],
		'durability' => ['Declares maximum durability 256.','Use Prism Pickaxe to test actual tool wear.'],
		'durability_sensor' => ['Declares a sound at durability threshold 32.','No server durability-sensor handler is attached.'],
		'dyeable' => ['Declares a turquoise default dye color.','No dye-use action is implemented for this item.'],
		'enchantable' => ['Declares sword enchanting with value 12.','Server enchanting eligibility is not implemented.'],
		'fire_resistant' => ['Marks the item as resistant to fire.','The server also treats this specimen as fireproof.'],
		'food' => ['Declares food value 6 and eating metadata.','Use Prism Berry for actual server consumption.'],
		'fuel' => ['Furnace fuel: 20 seconds / 400 ticks.','Both client metadata and server fuel time are set.'],
		'glint' => ['Requests the enchanted-item glint.','This visual effect does not add enchantments.'],
		'hand_equipped' => ['Uses the hand-equipped item render style.','Compare how it is held in third person.'],
		'hover_text_color' => ['Sets the hover-name color to aqua.','A tooltip-color specimen.'],
		'icon' => ['Uses the supplied Prism texture as its icon.','A minimal item-atlas reference example.'],
		'interact_button' => ['Labels the client action button Inspect.','No inspect action is attached to this lab item.'],
		'item_tags' => ['Adds the showcase:specimen item tag.','Tags describe the item for component queries.'],
		'kinetic_weapon' => ['Declares speed-based kinetic weapon parameters.','No server kinetic combat handler is attached.'],
		'liquid_clipped' => ['Declares liquid clipping for client targeting.','A targeting metadata specimen.'],
		'max_stack_size' => ['Stack limit: 16.','The server and client limits both use 16.'],
		'piercing_weapon' => ['Declares piercing reach and hitbox parameters.','No server piercing combat handler is attached.'],
		'projectile' => ['Declares a snowball projectile entity.','Use Prism Pebble for actual server throwing.'],
		'rarity' => ['Declares rare item rarity.','Compare its client presentation with common items.'],
		'record' => ['References the original one-second showcase chime.','No server jukebox playback action is attached.'],
		'repairable' => ['Declares amethyst shards as repair material.','Each repair advertises 64 restored durability.'],
		'shooter' => ['Declares arrow ammo and a one-second bow draw.','No server bow-shooting action is attached.'],
		'should_despawn' => ['Disables item despawning in client metadata.','No server dropped-item lifetime override.'],
		'stacked_by_data' => ['Advertises stacking by item data.','A stack-metadata specimen.'],
		'storage_item' => ['Declares 16 storage slots without nested storage.','No backing server storage inventory is created.'],
		'storage_weight_limit' => ['Declares a maximum storage weight of 64.','A storage metadata specimen only.'],
		'storage_weight_modifier' => ['Declares a stored-item weight of 8.','A storage metadata specimen only.'],
		'swing_duration' => ['Sets the client swing duration to half a second.','Does not change the server attack interval.'],
		'swing_sounds' => ['Declares critical, hit, and miss swing sounds.','No custom server swing-sound handler.'],
		'throwable' => ['Declares swing and launch-power parameters.','Use Prism Pebble for actual server throwing.'],
		'use_animation' => ['Requests the drinking use animation.','The generic lab item cannot be consumed.'],
		'use_modifiers' => ['Declares a 1.6-second use at half movement speed.','No server use action is attached.'],
		'wearable' => ['Declares the head slot with two protection points.','Use Prism Helmet for actual server armor behavior.']
	];

	/** @return list<string> */
	public static function block(string $specimen): array {
		return self::describe($specimen, self::BLOCKS, self::BLOCK_COMPONENTS, 'Block');
	}
	/** @return list<string> */
	public static function item(string $specimen): array {
		return self::describe($specimen, self::ITEMS, self::ITEM_COMPONENTS, 'Item');
	}
	/** @param array<string, list<string>> $playable @param array<string, list<string>> $laboratory @return list<string> */
	private static function describe(string $specimen, array $playable, array $laboratory, string $kind): array {
		$isLab = str_starts_with($specimen, 'lab_');
		$key = $isLab ? substr($specimen,4) : $specimen;
		$lines = ($isLab ? $laboratory : $playable)[$key] ?? throw new \InvalidArgumentException('Missing description: '.$kind.' '.$specimen);
		$lore = [F::RESET.F::AQUA.'Prism Laboratory'.F::DARK_GRAY.' | '.($isLab ? $kind.' component' : $kind)];
		foreach($lines as $line){ $lore[] = F::RESET.F::GRAY.$line; }
		return $lore;
	}
}
