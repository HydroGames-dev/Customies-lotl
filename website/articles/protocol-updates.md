## Minecraft 26.60 is the current target

Customies 1.10.0 requires Axolotl API 5.51.0 and targets protocol 2225 with released 26.60 dependencies. This replaces the earlier development-branch target. Customies extends the host's registries and uses its codecs rather than translating client protocols.

| CI target | Axolotl commit | Protocol source |
| --- | --- | --- |
| Released 5.51.0 | `f5e6a67fdf2108cf63196c06db1eb5c0d400d4c8` | Host lockfile, `64.0.0+bedrock-1.26.60` |
| Stable snapshot | `7831952d6a970d1700ca82742ab2ac4dbe61ff80` | Same released dependency |
| Protocol master | Same stable host | `64eae48f05b8d1e4d474ead89711600996f1c5af` |

The released dependency uses protocol source `f9d8b40da3940cef8242b0da34ba41fe49ea7364`. The host locks BedrockData `6.10.0+bedrock-1.26.60`, block-upgrade schema `5.5.0` and item-upgrade schema `1.20.0`. Tests use PHP 8.4.16. The pinned workflow is the reproducible record.

## Collision authoring JSON and network NBT differ

For 26.60 behavior-pack blocks, author an array of collision boxes rather than a single object. Customies sends processed network metadata: a compound containing `enabled` and a `boxes` NBT list. The released host's native definitions still use that envelope. Replacing it with raw behavior-pack JSON would be incorrect.

`addBox()` and `addBoxes()` now preserve earlier boxes, enforce the host's limit (16 on 26.60, see [version gates](#version-gates-and-the-12670-preview)), and leave the old list intact after an invalid bulk append. Disabled collision emits an empty list rather than an empty-box placeholder. PHP must still return matching physical boxes.

## Explicit coordinate helpers

`new Box($origin, $size)` uses geometry pixels. `Box::fromMinMax($min, $max)` uses 26.60 voxel pixels: X/Z 0-16, Y 0-24. Converting geometry coordinates mirrors X. Origin `[-8,0,-8]` and size `[12,24,8]` become min `[4,0,0]` and max `[16,24,8]`.

```php
use customiesdevs\customies\block\component\CollisionBoxComponent;
use customiesdevs\customies\block\properties\Box;
use pocketmine\math\Vector3;

$shape = Box::fromMinMax(new Vector3(4, 0, 0), new Vector3(16, 24, 8));
$client = (new CollisionBoxComponent())->addBox($shape);
$serverBox = $shape->toBlockAABB();
```

Use `fromBlockAABB()` for local PocketMine block units. It and `toBlockAABB()` preserve fractional dimensions. Older `fromAABB()`/`toAxisAlignedBB()` methods use geometry pixels, not server units. The [shape visualizer](tool-shapes.html) now exposes voxel min/max and follows the same X-mirror rule.

## Updated component APIs

Durability sensors and swing sounds now accept custom sound-definition names alongside `SoundEvent` enums. Use modifiers already accepted custom start sounds; tests cover all three. Showcase fixtures use their existing custom chime.

`BlockEntityComponent` adds optional `entityStorage` and `containerTitle` arguments after its original parameters. Titles require a container and 1-256 UTF-8 characters. These experimental metadata fields require Upcoming Creator Features. PHP Tile/inventory/entity persistence remains your plugin's responsibility.

Boolean and Molang bone visibility retain their respective NBT types. Minecraft supplies the n-way visual-rotation synchronization fix; Customies continues sending registered state values.

`RedstoneConductivityComponent` (1.10.0) sends the processed client fields `redstoneConductor` and `allowsWireToStepDown`, matching all 96 vanilla definitions in the host. Axolotl 5.51 computes wire connections on the server and treats full, non-transparent cubes as conductors; declare the same value. Wire connects sideways to blocks that implement `pocketmine\block\utils\RedstoneSignalEmitter`.

The other components added in Mojang's 1.26.60 schemas, `vibration_properties`, `neighbor_change` and `enchantment_power_transmitter`, describe server behavior and are not sent to clients. The converter explains the PHP equivalent for each.

## Version gates and the 1.26.70 preview

`BedrockVersion` reads the host's Minecraft network version. Gated behavior switches on only when Axolotl moves to that version: from 1.26.70, `CollisionBoxComponent::getMaxBoxes()` rises from 16 to 32. `MAX_BOXES` stays 16, and `tools/doctor.php` lists every gate under `bedrock_features`.

No Axolotl build supports 1.26.70 yet. Preview 1.26.70.23 also adds collision shape identifiers behind the VoxelShape experiment and the experimental `jukebox_playlist_source` component; neither is supported. Format 1.26.70 replaces `seconds_to_destroy` with `base_time_to_destroy` in server ticks, which the converter accepts and converts at 20 ticks per second. Run `php tools/component-coverage.php /path/to/bedrock-samples origin/preview --strict` to compare Mojang's newest schemas with Customies; the [version support record](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/docs/bedrock-version-support.md) has the full checklist.

## Verification and boundaries

```text
php tools/doctor.php /path/to/host
php tests/protocol.php /path/to/host
php tests/mc2660.php /path/to/host
php tests/versions.php /path/to/host
php examples/CustomiesShowcase/tests/guide.php /path/to/host
```

Tests cover join/registry codecs, state persistence, cache invalidation, canonical collision NBT, asymmetric coordinates, component fields and command removal. The example suite checks all 140 specimens, including slab/bench breaking, stairs and structures.

Mojang's Script API, stricter entity JSON schemas, loot changes and new experimental script components do not automatically become PocketMine PHP systems. Customies does not run `@minecraft/server` or a BDS behavior-pack interpreter. Use PHP hooks for authoritative gameplay. Test a real Minecraft client for rendering, custom sounds, prediction and experimental metadata acceptance.

Sources: [Mojang changelog](https://feedback.minecraft.net/hc/en-us/articles/49852828563853-Minecraft-Bedrock-Edition-26-60-Changelog), [Axolotl 5.51.0](https://github.com/axolotl-pm/PocketMine-MP/tree/f5e6a67fdf2108cf63196c06db1eb5c0d400d4c8), [released lockfile](https://github.com/axolotl-pm/PocketMine-MP/blob/f5e6a67fdf2108cf63196c06db1eb5c0d400d4c8/composer.lock).
