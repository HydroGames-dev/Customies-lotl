# Customies 1.7.0 - Minecraft 26.60

Requires Axolotl API 5.51.0. The primary target is now released protocol 2225,
BedrockProtocol 64.0.0 and BedrockData 6.10.0 for 1.26.60, with matching host
upgrade schemas. CI pins Axolotl 5.51.0, the stable branch and protocol master.

## Removed library commands

Removed the library command handler, manifest command registration and command
permission. Developer diagnostics remain as a PHP API and `tools/doctor.php`.
The separate optional showcase retains its example commands.

## Component changes

- Collision boxes append correctly: adding a second box no longer drops the first.
  Bulk append validates before mutation and rejects more than 16 boxes.
- Disabled collision now emits an empty NBT list, matching canonical 26.60 data.
  Network definitions retain the processed `enabled`/`boxes` envelope; the new
  behavior-pack JSON array requirement does not change that wire envelope.
- Geometry origin/size converts to voxel min/max using the 26.60 X-axis mirror.
  `Box::fromMinMax()`, `fromBlockAABB()` and `toBlockAABB()` make the coordinate
  system explicit and preserve fractional dimensions. Invalid/empty/non-finite
  bounds are rejected. The older AABB helpers retain geometry-pixel semantics.
- Block entity metadata accepts optional `entityStorage` and `containerTitle`.
  Titles require a container and 1-256 UTF-8 characters. These experimental fields
  do not implement a PHP inventory or entity persistence service.
- Durability sensors and swing sounds accept custom sound-definition names as
  well as existing enums. Tests also cover custom use-modifier start sounds.
- Boolean/Molang bone visibility retains the expected byte/string NBT types.

The browser box converter and showcase collision builders use explicit coordinate
conversions. Showcase 1.4.1 retains its assets/UUIDs, increases pack versions and
sets minimum engine version to 1.26.60. API reference exports and guides are updated.

## Upgrade notes

Back up and stop the server. Upgrade the host with its coherent dependency lockfile,
install one Customies library copy, update the matching example pack if you use it,
then restart and reconnect. Definitions must still register before the worker
snapshot; there is no hot reload.

If your code passed local server block units through the older pixel AABB helpers,
switch to `fromBlockAABB()`/`toBlockAABB()`. Asymmetric geometry-origin boxes now
follow the documented X mirror; verify their model, collision and rotations.

## Validation

Run `tests/protocol.php`, `tests/mc2660.php` and the showcase `tests/guide.php`
against the target host. Tests cover packet codecs, actual native collision NBT,
state persistence, invalid inputs, component fields and command removal. The
showcase covers 139 specimens, slab/bench breaking, corner stairs and structures.

The packaged library and Showcase 1.4.1 also booted on an isolated Axolotl 5.51.0
server using a copy of the earlier test-world fixture. A temporary probe confirmed
the command and permission are absent, 73 blocks/3210 states/139 item entries are
registered, the resource pack is loaded and registration is frozen. Shutdown was
clean. Fresh-world generation hit a Windows LevelDB reopen/temporary-file error
before plugin enable; that host/extension path remains a separate environment
limitation and was not changed in Customies.

Live Minecraft client rendering, custom sound playback, prediction and experimental
metadata acceptance remain separate checks. Mojang's Script API/behavior-pack
systems are not automatically implemented by a PHP registry library.

See the [compatibility guide](https://hydrogames-dev.github.io/Customies-lotl/protocol-updates.html)
and [Mojang changelog](https://feedback.minecraft.net/hc/en-us/articles/49852828563853-Minecraft-Bedrock-Edition-26-60-Changelog).
