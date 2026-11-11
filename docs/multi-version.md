# Multi-version support · 2026-11-11

Some PocketMine forks accept several Bedrock client versions on one server. Customies
detects such a host and registers its blocks and items for every client version, then
sends each client the definition formats its version reads. Nothing needs configuring.

## Hosts

| Host | Client versions | Customies |
| --- | --- | --- |
| Axolotl-PM 5.51, PocketMine-MP | One: 1.26.60, protocol 2225 | One block and one item dictionary, as before |
| [NetherGames PocketMine-MP `eaa2e58`](https://github.com/NetherGamesMC/PocketMine-MP/tree/eaa2e58156e71e7a10093cffc98b02e1023f05ee) (5.51.2) | 32 protocols: 1.20.0 (589) to 1.26.60 (2225) | Per-version dictionaries and join packets |

A host is treated as multi-version when `ProtocolInfo::ACCEPTED_PROTOCOL` exists and
`TypeConverter` offers `getAll()` and `addCreationListener()`. Client versions come from the
host's `ProtocolInfo::PROTOCOL_1_<minor>_<patch>` constants, so new versions the host adds
are picked up without a Customies change. `tools/doctor.php` lists them under
`accepted_protocols`.

## What Customies does for each client version

**Block palettes.** NetherGames keeps one block state dictionary per client palette (24 for
its 32 protocols) and creates each when the first client of that version joins, in the
main thread and in every async worker that encodes chunks. Customies inserts its states
into each one as it is created. Clients number states by sorting block names by FNV-1a 64
hash. NetherGames stores older palettes under current block names, so Customies sorts each
palette by the names its client uses. Sorting by current names would shift every vanilla
block for older clients; `tests/multiversion.php` checks the order against each version's
own palette file.

**Item dictionaries.** Custom items are added to every item dictionary (28 on NetherGames).
Their network IDs start at 20329 for items and 10858 for block items, above every vanilla
ID in all accepted versions (-1251 to 887), so one ID works everywhere. A dictionary
created later is checked when it loads; a conflict leaves that item out of that version
and logs an error instead of failing the player's login.

**Join packets.**

| Client version | Customies sends | Evidence |
| --- | --- | --- |
| Before 1.21.60 (protocol < 776) | Custom items in the StartGame item table; item components in the legacy component packet after the biome definitions, listing component-based items only | Host protocol library (format changes at 1.21.60); Customies' own 1.21.60 port |
| Before 1.21.130 (protocol < 898) | One `origin`/`size` collision box. Several boxes become their enclosing box; height is clipped to 16 pixels | Customies history: `origin`/`size` until 14 Nov 2025, `boxes` from 14 Dec 2025, five days after 1.21.130 shipped |
| Below the host's collision-box limit | The first boxes, plus one box enclosing the rest | `BedrockVersion` gates |
| Every version | Custom block palette and the experiments Customies needs | Unchanged |

The gates are `item_registry_packet` and `collision_box_list` in `BedrockVersion`, next to
`collision_box_32`.

## Limits

- No Bedrock client of any version was connected. The checks below cover the server's
  registries and the packets as encoded for each protocol, not rendering.
- Only the formats above are converted. Components and fields newer than a client's
  version, such as `redstone_conductivity`, `liquid_detection` or geometry
  `culling_shape`, are sent unchanged; how older clients treat them is untested.
- An enclosing legacy collision box is solid where the real shape has gaps. The server
  still uses the precise PHP collision, so older clients may predict a collision the
  server does not have.
- Resource packs must load on the oldest client you accept. CustomiesShowcase's pack
  declares `min_engine_version` 1.26.60, so older clients cannot use it; lower that and
  avoid newer pack features for a multi-version server.
- NetherGames' `StartGamePacket` decoder reads an uninitialized property. Servers never
  decode that packet; the tests give it a placeholder.
- NetherGames, like Axolotl, requires `ext-webrtc` for NetherNet. Install with
  `composer install --ignore-platform-req=ext-webrtc` when your PHP build lacks it and
  you use RakNet.

## Checks

```text
php -d memory_limit=2G tests/multiversion.php /path/to/nethergames-checkout
python tests/restart-server.py /path/to/nethergames-checkout --cycles 3
```

`tests/multiversion.php` registers a block with two collision boxes and a component item,
then, for each of the 32 protocols: compares the vanilla state order with that version's
own palette file, checks that names are sorted as the client sorts them, resolves custom
and vanilla blocks to runtime IDs, round-trips custom item stacks, and encodes and decodes
the join packets for that protocol. On single-version hosts it checks the conversion rules
only. The server restart test also translates a custom block with the oldest client's
palette inside an async worker. The full suite passes on both NetherGames and Axolotl.
