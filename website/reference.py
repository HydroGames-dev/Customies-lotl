"""Render component documentation from the repository's reflected wrapper API."""
import json,re

BLOCK_DOCS='https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/blockcomponentslist?view=minecraft-bedrock-stable'
ITEM_DOCS='https://learn.microsoft.com/en-us/minecraft/creator/reference/content/itemreference/examples/itemcomponentlist?view=minecraft-bedrock-stable'

NOTES={
 'collision_box':('The constructor Box uses geometry pixels; 26.60 voxel min/max mirrors X. Use Box::fromBlockAABB()/toBlockAABB() for local server units. Boxes append up to 16; disabled collision sends an empty NBT list.','Geometry + selection_box. Override recalculateCollisionBoxes() with corresponding occupied volumes; authoring JSON and processed network NBT have different envelopes.'),
 'selection_box':('This controls targeting, not physical collision. A thin mesh with a full selection box may select empty-looking space. Keep selection vectors and server ray targeting compatible.','Geometry + collision_box. Verify the player can target and break every placed state.'),
 'geometry':('A geometry identifier is not a file path. Bone visibility changes visual bones, not server collision. Supply every referenced model/bone and use compatible culling configuration.','Material instances + collision/selection. Author state-dependent models with permutations.'),
 'material_instances':('A later material component replaces the material map; it does not merge individual slots. Atlas keys must exist, and geometry material_instance names must agree.','Geometry + state permutations. Add a flipbook atlas tile for purely client-side texture animation.'),
 'light_emission':('Use a light level from 0 through 15. This wrapper serializes a CompoundTag containing emission; do not assume that the wrapper payload is the same shape as behavior-pack JSON.','Powered Boolean state + material permutation. Match getLightLevel() and write the changed block into the world.'),
 'light_dampening':('Filtering light is different from emitting light. Transparent-looking textures can still block server light unless PHP filtering agrees.','Material transparency + getLightFilter(). Test lighting after placement and removal.'),
 'transformation':('Rendering transforms do not reserve additional world cells. Rotate and scale asymmetric server collision separately. This branch encodes quarter-turn rotations; arbitrary yaw belongs in authored geometry.','Placement direction + geometry. Use separate meshes for unsupported rotations.'),
 'destructible_by_mining':('Client mining metadata and server hardness/tool rules are separate. Matching a client duration does not implement harvest requirements or tool drops.','BlockBreakInfo + correct tool/tier handling; test mining in survival.'),
 'destructible_by_explosion':('Do not equate a client resistance value with authoritative server blast behavior. Test whole grouped blocks under host explosions separately.','BlockBreakInfo blast resistance and correct affected-block/drop handling.'),
 'placement_filter':('Allowed support faces in metadata are not a substitute for server placement validation. A world edit may bypass ordinary placement hooks.','Validate support and bounds before adding blocks to the placement transaction.'),
 'movable':('Movement policy is not a complete PHP piston implementation. Native multi-blocks require a base immovable or popped policy and restrict permutation overrides.','Native multi_block trait and getAffectedBlocks() for grouped behavior.'),
 'friction':('Bedrock surface friction and the host friction-factor convention differ. Do not blindly copy the component number into a PHP override.','Compare the target host movement calculation and test real walking/sliding.'),
 'crafting_table':('Metadata does not create custom recipes or automatically open a PHP inventory window. Recipe tags need matching registered recipes.','Open CraftingTableInventory in onInteract() and register server recipes.'),
 'block_entity':('Optional 26.60 entityStorage and containerTitle are experimental metadata requiring Upcoming Creator Features. Titles require a container and 1-256 UTF-8 characters. This does not create a PHP Tile, inventory or entity persistence system.','Implement authoritative storage, persistence, transactions and access checks in your plugin.'),
 'food':('Nutrition, saturation modifier, eating eligibility and returned containers need matching PHP Food behavior. Effects in metadata are not a substitute for server effects.','Food subclass + use_animation + use_modifiers; implement restoration/effects/residue.'),
 'cooldown':('The component duration is seconds. PocketMine cooldown/effect APIs normally use ticks. Shared category names deliberately share a cooldown group.','Match getCooldownTicks()/getCooldownTag() and return the correct ItemUseResult from the use hook.'),
 'durability':('Client durability does not make plain Item durable. Server damage, wear, repair and item breakage must use a suitable Durable/Tool/Armor base class.','Digger or wearable/damage metadata plus matching maximum durability and wear paths.'),
 'damage':('Client weapon metadata is not authoritative damage. Extra component damage and a PHP weapon base class may use different conventions.','Match getAttackPoints() or weapon tier behavior; test actual attacks and wear.'),
 'digger':('Target descriptors and advertised mining speed do not implement the host tool tier or harvest checks.','Extend the appropriate Tool class and match target blocks, tool type and durability.'),
 'wearable':('An equipment slot and protection metadata do not provide server armor defense or a worn mesh by themselves.','Armor subclass + durability + attachable; match slot/defense and bounded worn-effect logic.'),
 'max_stack_size':('Client and server stack limits must agree. A mismatched override can cause inventory prediction and transaction problems.','Override getMaxStackSize() with the same limit used in the component.'),
 'fuel':('Component burn duration is seconds; the host getFuelTime() result is ticks. Check host furnace acceptance for custom items.','Match getFuelTime(); test consumption and furnace progress.'),
 'projectile':('A projectile identifier and launch parameters do not spawn a PHP entity. Server collision, ownership, damage and consumption still need implementation.','Use the host projectile base/use hook, then combine throwable or shooter metadata as appropriate.'),
 'throwable':('A throwable animation/configuration is not the launch implementation. Consume the stack once and avoid duplicating returned items.','Projectile component + real onClickAir() launch/consumption logic.'),
 'shooter':('Charging, ammo search and launch metadata require matching server inventory and projectile logic. Test no-ammo and cancelled-use paths.','Ammo handling + use animation + projectile + server cooldown and damage.'),
 'storage_item':('This is not a working backpack/container by itself. Contents, nesting, limits and transactions must be authoritative and persisted.','Server inventory and saved NBT/Tile storage plus storage weight components.'),
 'repairable':('Repair ingredient metadata does not automatically add host anvil/repair integration for every custom item.','Durable item + validated server repair recipes/actions; preserve inventory correctness.'),
 'use_animation':('The animation is visual. Eating, drinking or charging must have a coherent server use/consumption implementation.','Food or weapon base class plus use_modifiers and appropriate use hooks.'),
 'use_modifiers':('Use-duration/movement metadata must agree with server timing and movement behavior. Do not confuse seconds with host ticks.','Use animation + consumption/charging logic with matching duration.'),
}

def table_descriptions(source):
    return {m[1]:(m[2].strip(),m[3].strip()) for m in re.finditer(r'^\| `([^`]+)` \| ([^\n]+?) \| ([^\n]+?) \|\s*$',source,re.M)}

def detail(kind,key,api,summary,responsibility,usage):
    note,combination=NOTES.get(key,(
        'This wrapper describes a client-facing property. Engine acceptance depends on the target client, format and experiments. Constructor defaults are library defaults, not a promise that a host implements the entire native feature.',
        responsibility+' Combine only with components that match the feature you implement; verify the authoritative host behavior.'
    ))
    rows=[]
    for p in api['parameters']:
        default='Required' if p['required'] else p['default_constant'] or json.dumps(p['default'],ensure_ascii=False)
        description=p['description'] or 'See the constructor and implementation for this parameter; no extra constraint is declared in its parameter comment.'
        rows.append('| `'+p['name']+'` | `'+p['type']+'` | `'+default.replace('|','\\|')+'` | '+description.replace('|','\\|')+' |')
    params='| Parameter | PHP type | Default | Meaning |\n| --- | --- | --- | --- |\n'+'\n'.join(rows) if rows else 'This component has no constructor parameters.'
    constants='\n'.join('| `'+name+'` | `'+json.dumps(value,ensure_ascii=False).replace('|','\\|')+'` |' for name,value in (api['constants'].items() if isinstance(api['constants'],dict) else []))
    constants=('\n\n## Constants\n\n| Constant | Value |\n| --- | --- |\n'+constants) if constants else ''
    methods=''
    for method in api.get('methods',[]):
        signature=method['name']+'('+', '.join(p['type']+' $'+p['name']+(' = '+json.dumps(p['default']) if not p['required'] else '') for p in method['parameters'])+'): '+method['return_type']
        documentation=re.sub(r'^\s*\* ?','',method['documentation'].replace('/**','').replace('*/',''),flags=re.M).strip()
        methods+='\n\n### '+method['name']+'\n\n```php\n'+signature+'\n```\n\n'+('```text\n'+documentation+'\n```' if documentation else 'The method is exported from the wrapper; inspect its implementation for validation and chaining semantics.')
    methods=('\n\n## Additional wrapper methods'+methods) if methods else ''
    official=BLOCK_DOCS if kind=='block' else ITEM_DOCS
    imports=('use customiesdevs\\customies\\block\\component as B;\nuse customiesdevs\\customies\\block\\properties\\{Material, Box, PlacementCondition, AllowedFace, BlockDescriptor};' if kind=='block' else 'use customiesdevs\\customies\\item\\component as I;\nuse customiesdevs\\customies\\item\\properties\\{RepairItems, RepairAmount, SoundEvent, DamageCause};')
    return f'''## Overview

{summary}

| Customies API | Value |
| --- | --- |
| Component identifier | `{api['name']}` |
| PHP class | `{api['class']}` |
| Definition type | {kind.title()} component |
| Authority | Client metadata; PHP must enforce gameplay |

## Constructor parameters

{params}

Required/default status and types are exported from the checked-in PHP constructor.
Parameter descriptions come from this wrapper's comments. An undocumented constraint
must not be inferred from a permissive constructor: check the target engine reference.

## PHP usage

```php
{imports}
use pocketmine\\math\\Vector3;

$this->addComponent({usage});
```

Use this inside the appropriate component-enabled block/item class. Register block
components before registration; define states and traits before TraitBlock's parent
constructor. The texture, geometry and custom identifiers in this example are
placeholders for resources provided by your own plugin and pack.

{methods}

## Serialized wrapper value

```json
{json.dumps({api['name']:api['serialized_example']},indent=2,ensure_ascii=False)}
```

This is the normalized output of the repository's example fixture `getValue()`.
NBT tags are represented as JSON values for readability. It is **not** a complete
behavior-pack file, and the network wrapper shape can differ from native Bedrock
JSON. Do not paste it into a pack without checking the official content schema.

## Server behavior

{responsibility}

{note}

## Combining this component

{combination}

## Verify before shipping

1. Confirm registration emits the intended identifier and wrapper fields.
2. Check referenced assets and all state-dependent overrides.
3. Test the matching PHP interaction, collision, inventory or effect behavior.
4. Check saved data after restart and inspect the real client's content log.

Passing a constructor test verifies the wrapper, not every client/host feature.
{constants}

## Implementation and references

- [Customies implementation](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/{api['source']})
- [Official {kind} component index]({official})
- [Metadata versus PHP behavior](getting-started.html#library-architecture)

<details><summary>Read the actual constructor</summary>

```php
{api['constructor'].strip()}
```

</details>
'''
