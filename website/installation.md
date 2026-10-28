## Install Customies

Customies is a library for registering custom blocks and items on Axolotl-PM.
You write your own content plugin and resource pack. The optional Showcase plugin
is an example; it is not a dependency of your project.

### Check the target first

This branch targets **Axolotl API 5.51.0**, **Minecraft Bedrock 26.60** and **protocol
2225**. Use the host's matching PHP distribution. Automated checks currently use
PHP 8.4; the [compatibility matrix](protocol-updates.html) lists exact commits.
Customies uses the host protocol and does not translate older clients.

### Install the library and your plugin

1. Create a test server and start it once to verify the host/PHP installation.
2. Put one `Customies-Axolotl.phar` from [downloads](downloads.html) in `plugins/`.
3. Create a separate content plugin with the manifest below. The
   [generator](tool-generator.html) can create its source files, registration and
   pack scaffolding. Existing add-ons can start in the [Migration Lab](migration.html).
4. Register content in your plugin's `onEnable()` before Customies takes the
   deferred worker snapshot. Allocate each type ID once, outside its factory closure.
5. Build/install your plugin, complete its resource pack, and add the pack to the
   server's resource stack. Restart and reconnect clients after definition changes.

```yaml
name: MyContent
main: tutorial\Main
src-namespace-prefix: tutorial
version: 1.0.0
api: 5.51.0
load: POSTWORLD
depend: [Customies-Axolotl]
```

Put `Main extends PluginBase` in `src/Main.php` under the `tutorial` namespace.
The [coding guide](coding.html) contains complete registration and class examples.
Generators download source folders, not precompiled plugin binaries; package them
with DevTools or your normal PHP build pipeline.

### Verify the first definition

Confirm the identifier registers, then retrieve it with
`CustomiesBlockFactory::get()` or `CustomiesItemFactory::get()`. Give it through
your own inventory or command logic. Customies itself does not register in-game
commands. Check the [diagnostics API](diagnostics.html) for registry information.

Join with a matching Bedrock client. Verify the texture/model, targeting, placement,
physical collision, use action and mining/drop behavior. Save, restart and reload
the world or inventory. A working texture does not establish correct server physics,
and passing a host test does not establish client prediction.

### Choose the next guide

- [Blocks](blocks.html): components, states, placement traits and permutations.
- [Items](items.html): base classes, abilities, food, durability and armor.
- [Resource packs](assets.html): atlas paths, geometry, attachables and animation.
- [How Customies works](how-it-works.html): startup, worker snapshots and saved data.
- [Changelog](changelog.html): behavior changes, migration notes and verification.
