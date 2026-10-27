## What Customies 1.6.0 adds

Customies now checks the host registry fields it depends on before content plugins
register definitions. It recognizes the Axolotl 26.50 stable and 26.60 development
protocol targets, exposes host/package information for support, and tests the
actual packet codecs. It uses the host's packet classes; it does not implement a
second login handler or translate clients between protocols.

| Target | Protocol | Host snapshot | Dependency mode |
| --- | --- | --- | --- |
| Stable | 2193 / 1.26.50 | `c96300392ade9fb4ed423c2662815be7e79c45a3` | Host Composer lockfile |
| Stable protocol head | 2193 / 1.26.50 | Same host | Protocol `da83718c6e329de77dc4b9d654839bf5460dd6f8` |
| Development | 2225 / 1.26.60 | `3bf5c010a1c05790b2ba64d12832102b62822b1f` | Host lockfile, protocol `bed7911d02783ce0bb7cc1ca1bfa4dc622db9a20` |
| Development protocol head | 2225 / 1.26.60 | Same development host | Protocol `f9d8b40da3940cef8242b0da34ba41fe49ea7364` |

These are pinned API/codec regression targets checked on 27 October 2026 with PHP
8.4.16. They are not a promise that every client build renders every component.
The development branch is an opt-in test target. Keep a stable production server
until your own content and clients pass testing. A 1.26.52 client is not separately
verified here; the host decides which clients may connect.

## Why a protocol update involves several repositories

The server owns login, inventory transactions, world/chunk encoding, entity
spawning, and packet ordering. BedrockProtocol supplies packet definitions and
codecs. BedrockData supplies the vanilla registries and other client data. Upgrade
schemas handle saved identifiers and state migrations. Customies extends those
registries with the definitions supplied by your plugin.

Changing `CURRENT_PROTOCOL`, the displayed version, or a resource pack's
`min_engine_version` cannot update those other layers. Use a coherent host checkout
and its lockfile. Test a newer protocol dependency in an isolated checkout only
when you are deliberately maintaining a development port.

The 26.60 branch includes host changes for chunk packets and skins, and protocol
changes such as hand-slot fields and an editor migration field in level settings.
Customies leaves those changes to the host codecs. Its own merge preserves the
incoming StartGame palette and unrelated experiments, then adds custom definitions
in the same FNV-1 order as its runtime dictionary.

## Runtime IDs and cache invalidation

Custom blocks are inserted into a name-sorted runtime state dictionary. Inserting
one name can shift the network IDs of existing vanilla blocks. Previously the
dictionary was rebuilt but `BlockTranslator` could still return an ID cached from
before insertion. Customies 1.6.0 clears that translation cache whenever it rebuilds
the dictionary. The regression suite warms vanilla mappings first, inserts a
custom definition, and verifies all warmed mappings against the new dictionary.

Names are hashed once per palette sort instead of repeatedly inside the comparator.
State order inside a block is preserved. This reduces hashing work during
registration; it is not a claim about a server's overall TPS or memory capacity.

## Registration is a startup operation

Register blocks, items and entities in `onLoad()` or `onEnable()`. On the first
scheduled startup task, Customies snapshots block callbacks for async workers and
closes registration. Adding definitions later throws an actionable exception
before calling your factory closure. This prevents a new definition appearing on
the main thread while workers or connected clients still use an older palette.

There is no hot-reload switch. Restart after changing registered definitions.
Duplicate identifiers, reused item network IDs, reserved vanilla names and IDs
outside the signed 16-bit item registry range are rejected. Keep using the host's
`BlockTypeIds::newId()` and `ItemTypeIds::newId()` allocators and retain one type ID
per registered definition. Common definition/NBT errors are checked before entries
are published; this is not a general rollback system for arbitrary callbacks.

## Reproduce the checks

Install the matching host PHP binary and a pinned Axolotl checkout with its
Composer dependencies. Run each command as a separate process:

```text
php tools/doctor.php /path/to/host
php tests/protocol.php /path/to/host
php examples/CustomiesShowcase/tests/guide.php /path/to/host
```

The protocol suite includes the original state/placement regressions. It also
round-trips complete StartGame, ResourcePackStack, ItemRegistry and
AvailableActorIdentifiers packets, checks palette cache invalidation, verifies
other-plugin metadata survives merging, and rejects invalid or late registrations.
The four-target GitHub Actions workflow pins both host and optional protocol refs.
The example suite is a separate integration fixture for blocks, items and guides.

The packaged library and showcase also passed localhost startup smoke checks on
the 26.50 and 26.60 protocol-head fixtures. Both registered 139 specimens, reached
`Done` without reported worker registration errors, ran console diagnostics and
stopped through the console. These checks did not include a connected Minecraft
client.

Before deploying, test a real server and Minecraft client: connect/reconnect,
creative inventory, place/rotate/break, inventory actions, workers loading chunks,
and save/restart. Back up worlds and packs before upgrading. Use the
[diagnostics guide](diagnostics.html) to record the exact runtime you tested.

Primary sources: [Axolotl stable snapshot](https://github.com/axolotl-pm/PocketMine-MP/tree/c96300392ade9fb4ed423c2662815be7e79c45a3),
[Axolotl 26.60 snapshot](https://github.com/axolotl-pm/PocketMine-MP/tree/3bf5c010a1c05790b2ba64d12832102b62822b1f),
[26.60 protocol changes](https://github.com/axolotl-pm/BedrockProtocol/compare/da83718c6e329de77dc4b9d654839bf5460dd6f8...f9d8b40da3940cef8242b0da34ba41fe49ea7364).
