# Bedrock version support · 2026-11-11

Customies uses the host's codec and does not negotiate protocols, so Axolotl decides
which Bedrock version clients run. This record lists what each Bedrock release
changed for custom blocks and items, what Customies does about it, and how to check
the next release.

## Current target

| Item | Value |
| --- | --- |
| Customies | 1.10.0 |
| Axolotl | API 5.51.0; released [5.51.0 `f5e6a67`](https://github.com/axolotl-pm/PocketMine-MP/tree/f5e6a67fdf2108cf63196c06db1eb5c0d400d4c8) and stable [5.51.2-dev `7831952`](https://github.com/axolotl-pm/PocketMine-MP/tree/7831952d6a970d1700ca82742ab2ac4dbe61ff80) |
| Minecraft | 1.26.60 (display 26.60), protocol 2225 |
| Mojang schemas, release | [bedrock-samples `15701fa`](https://github.com/Mojang/bedrock-samples/tree/15701fa9c13923f12662984c32c124327e40e3d3), 1.26.60.5, 27 October 2026 |
| Mojang schemas, preview | [bedrock-samples preview `4301426`](https://github.com/Mojang/bedrock-samples/tree/430142679bb7daf3160671f06ab32ea433be1e1d), 1.26.70.23, 3 November 2026 |

Axolotl stable `7831952` is the newest host commit checked; it still uses protocol
2225. No Axolotl build supports 1.26.70 yet.

## Component coverage at 1.26.60

Customies wraps 34 of the 52 block components and 42 of the 44 item components in
Mojang's 1.26.60 schemas. Eleven of the remaining block components are deprecated
event components (`on_interact`, `queued_ticking`, `unit_cube` and similar). The rest
describe server behavior, which Customies does not send to clients:

| Component | Customies | Server-side equivalent on Axolotl |
| --- | --- | --- |
| `redstone_conductivity` | `RedstoneConductivityComponent`, new in 1.10.0 | Server conductor rule, see [Redstone](#redstone-on-axolotl-551) |
| `vibration_properties` (new in 1.26.60) | Not sent | Axolotl has no sculk or vibration system |
| `neighbor_change` (new in 1.26.60) | Not sent | A Script API event filter; override `onNearbyBlockChange()` (it does not report the changed side) |
| `enchantment_power_transmitter` (new in 1.26.60) | Not sent | Needs host support: `EnchantingHelper` only counts bookshelves through air gaps |
| `redstone_producer` | Not sent | Implement `pocketmine\block\utils\RedstoneSignalEmitter` |
| `redstone_consumer` | Not sent | Axolotl computes power in PHP |
| `loot` | Not sent | Override `getDropsForCompatibleTool()` |
| `tick` | Not sent | Use `onScheduledUpdate()` or `onRandomTick()` |
| Item `block_placer`, `entity_placer` | Not sent | Customies registers block items; spawn entities from PHP item use |

Mojang's server networks only the components a client uses. Vanilla
`minecraft:black_wool_slab` declares 12 components in its behavior-pack JSON, and the
26.60 host networks four of them: `destructible_by_mining`, `destruction_particles`,
`liquid_detection` and `redstone_conductivity`. Networked field names are often the
processed names, such as `redstoneConductor`, rather than the JSON names, so compare
new wrappers against the host's `data_driven_blocks.nbt`, not against the JSON schema.

Every Customies block wrapper that has a vanilla counterpart in that file was compared
for field names and NBT tag types. All of them match. `tests/mc2660.php` compares
`redstone_conductivity` exactly against all 96 vanilla definitions.

## Redstone on Axolotl 5.51

Axolotl 5.51 stores redstone-wire connections as block states and recalculates them
when neighbors change. The following is taken from `RedstoneWire` at `7831952` and
has not been tested with a client:

- Wire connects sideways to repeaters and to any block implementing
  `pocketmine\block\utils\RedstoneSignalEmitter`. Implement it on custom blocks that
  should look powered-capable to wire.
- The server treats a block as a conductor when `isFullCube() && !isTransparent()`.
  Declare the same value in `RedstoneConductivityComponent` so the client's
  prediction agrees with the server's wire states.

```php
use customiesdevs\customies\block\component\RedstoneConductivityComponent;

// A full, opaque cube: Axolotl treats it as a conductor, so say so to the client too.
$this->addComponent(new RedstoneConductivityComponent(redstoneConductor: true));
```

## Version gates

`customiesdevs\customies\network\BedrockVersion` reads the host's network version
(`ProtocolInfo::MINECRAFT_VERSION_NETWORK`). A gated behavior switches on only when
Axolotl moves to that Minecraft version, so content keeps loading on 26.60 hosts.

| Gate | From | Effect |
| --- | --- | --- |
| `collision_box_32` | 1.26.70 | `CollisionBoxComponent::getMaxBoxes()` rises from 16 to 32 |

`CollisionBoxComponent::MAX_BOXES` stays 16 for existing callers. `tools/doctor.php`
lists every gate and whether it is active under `bedrock_features`. Gates taken from
preview schemas are re-checked against the released schemas before a host update is
accepted.

## 1.26.70 preview

| Change in preview 1.26.70.23 | Customies status |
| --- | --- |
| `collision_box` accepts up to 32 boxes | Gated, see above |
| `collision_box` accepts a shape identifier (`minecraft:empty`, `minecraft:unit_cube` or a custom voxel shape) behind the VoxelShape experiment | Not supported; needs shape registration on the host |
| `jukebox_playlist_source` (Upcoming Creator Features) | Not wrapped. The converter reports it |
| `destructible_by_mining` at format 1.26.70 uses `base_time_to_destroy` in server ticks instead of `seconds_to_destroy` | The converter accepts both and converts ticks at 20 per second. Check the networked form when a 1.26.70 host exists |

## Checking the next release

1. Update both references: `git pull` in Axolotl and in `bedrock-samples`, plus
   `git fetch origin preview` for previews.
2. List schema changes:
   `php tools/component-coverage.php /path/to/bedrock-samples [ref] --strict`.
   It exits 1 when a schema component is neither wrapped nor classified.
3. For every new component, find out whether the host networks it (search the new
   host's `data_driven_blocks.nbt`) and copy the networked field names and tag types.
   A component that only describes server behavior gets a classification in
   `tools/component-coverage.php` and a converter note in `website/authoring-lib.js`.
4. Add a `BedrockVersion` gate for every client limit that changes, with a test in
   `tests/versions.php`.
5. Pin the new host commit in `.github/workflows/compatibility.yml`, add the protocol
   to `RuntimeCompatibility::getProfile()` once its tests pass, and connect a real
   client before release.
