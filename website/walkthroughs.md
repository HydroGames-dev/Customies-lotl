# Build your own Customies plugin

These walkthroughs use the Customies library in your own plugin and resource pack.
They do not require the example content plugin.

## 01 / Set up your plugin

Follow [Getting started](getting-started.html). Declare the Customies dependency,
create your PluginBase entry class, and register definitions in onEnable(). Use
one stable type ID per registered identifier, outside the factory closure.

## 02 / Create a powered block

Copy the complete TutorialLamp class from [Coding](coding.html#create-a-working-block).
Create the two terrain atlas entries in [Resource packs](assets.html), add the
corresponding PNGs to your own pack, then register `tutorial:lamp` in onEnable().

Get the block from CustomiesBlockFactory and give its asItem() result through your
own command. Place it, interact, inspect your power state and verify the server's
light level changes. Save/restart and confirm that state persists.

## 03 / Create an ability item

Follow TutorialWand in [Coding](coding.html#create-a-working-item). Supply its icon,
stack limit and cooldown metadata, match the PHP stack/cooldown methods, then apply
the effect in onClickAir(). The example uses 100 ticks of Speed and a 40-tick
cooldown. Test stacking, repeated use and reconnects.

## 04 / Add rotation and placement states

Use the cardinal/facing presets described in [States and traits](states.html).
Register traits before the parent block constructor and rotate asymmetric server
collision too. For slabs, use vertical-half placement and both half-specific boxes.
For stair corners, test every shape, half and direction before changing saved schemas.

## 05 / Combine components into gameplay

Use the [combination recipes](recipes.html) and the component references. Start
with one behavior and add its matching visuals and metadata. Food should extend
Food; armor should extend Armor; tools should use the appropriate PocketMine base
class. Storage metadata needs a real server inventory and persistence.

## 06 / Add assets and animation

Use your own manifest UUIDs, atlas keys and geometry identifiers. A powered material
can select a client flipbook strip without a repeating PHP task. Worn/held models
need attachables and matching bones, render controllers and animation controllers.
See [Resource packs and animation](assets.html). Test actual client alignment.

## 07 / Test and ship

Test registration, serialization, cloning, placement, collision, drops, inventory
changes and protection events. Then test your resource pack in Minecraft. Back up
saved worlds, keep identifiers stable, bump resource-pack versions, restart and
reconnect. Profile real world/player load using [Performance](performance.html).
Do not infer capacity or client compatibility from a small synthetic test.
