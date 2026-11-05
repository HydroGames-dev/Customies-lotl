# Items missing after restart: fixes and verification

Customies 1.9.1 addresses registration and reload gaps on Bedrock 26.60 / protocol
2225. The reported screenshots show occupied custom slots becoming empty. They
do not identify the inventory plugin, startup failure or saved-data format, so
the exact production trigger has not been established from those images alone.

## What changed

- **Login registry repair:** the packet listener now merges current Customies
  item and block-item entries into `ItemRegistryPacket`, including packets cached
  before registration. Matching Customies entries replace stale definitions;
  unrelated entries and subclient routing survive. Repeated processing does not
  duplicate definitions. The source packet remains untouched. Conflicting numeric
  IDs are rejected with the identifiers involved rather than sent ambiguously.
- **Block-item reload:** custom block items now have a direct saved-item
  deserializer as well as the normal block-state deserializer. A plugin using
  `SavedItemData` without a `Block` tag can restore the default item. Both
  directions of the host block/item name mapping are registered. Full NBT remains
  necessary to retain per-stack data and explicit block-state data.
- **Earlier registration:** Showcase 1.4.3 and newly generated/migrated plugins
  register definitions in `onLoad()`. Inventories read during `onEnable()` and
  worlds loaded between startup phases now see their definitions. Listener
  registration and world-dependent work stay in `onEnable()`.
- **Failed registration:** an existing parser alias is checked before publishing
  serializer/deserializer mappings. Catching that error no longer leaves a
  partial saved-item registration behind.
- **Startup evidence:** the `Registry ready` log records block/item counts and a
  SHA-256 fingerprint of the sorted identifier list. It excludes temporary
  numeric IDs, so reordered registration and ID allocation do not change it.
  This is an identifier-set comparison, not a component or resource-pack hash.

## Content plugin rules

Declare `depend: [Customies-Axolotl]`. Register all definitions in `onLoad()`
before reading inventories that contain them. A separate storage/GUI plugin
must depend on the content plugin and load its inventories after registration.
Two plugins doing unrelated work in `onLoad()` without that dependency still
have an ordering problem; the library cannot infer their dependency graph.

Allocate one `ItemTypeIds::newId()` or `BlockTypeIds::newId()` per definition and
capture that integer in its factory closure. Reuse the same namespaced
identifier and state schema across restarts. Use `Item::nbtSerialize()` and
`Item::nbtDeserialize()` with typed NBT for stored inventory stacks; PHP object
serialization, numeric type IDs and network IDs are not durable item formats.

PocketMine's `safeNbtDeserialize()` logs a failure and returns AIR. Reading an
inventory before its content is registered can therefore turn missing definitions
into empty slots, and saving those slots can overwrite the original items. An
inventory plugin should retain the original data on failure and surface the
error instead of treating a failed load as an intentionally empty inventory.
Customies cannot reconstruct items already overwritten by another plugin.

## Reproductions and tests

Against unchanged Customies 1.9.0, the new fixtures fail independently for an
unmapped name-only `SavedItemData` custom block item and an early login packet
missing custom entries. These are controlled reproductions of specific gaps;
they are not a reproduction with the reporter's unavailable inventory plugin.

```text
php tests/restarts.php /path/to/axolotl-with-vendor
python tests/restart-server.py /path/to/axolotl-with-vendor --php php --cycles 12
```

The first suite creates 16 independent PHP processes against one saved NBT
inventory. It changes type-ID allocation and registration order, retains sparse
slots/counts/name/lore/nested typed tags and placed-block orientation, and checks
inventory and registry packet codecs, duplicate aliases, foreign registry entries,
same-size cache invalidation and stale custom definitions.

The server suite packages the library, Showcase and a probe into PHARs. It starts
independent servers against the same isolated world and player-data directory.
It reloads a real chest tile and a placed custom block through the native world
provider, reads/writes a synthetic player's data through
`DatFilePlayerDataProvider`, checks all Showcase definitions and the actual packet
event, and shuts down cleanly each time. It never uses the operator's world.
The temporary directory and per-cycle logs are printed and retained for review.
This does not connect a real Player or Bedrock client.

Local verification on 2026-11-05 used PHP 8.4.16, released Axolotl 5.51.0
(`f5e6a67fdf2108cf63196c06db1eb5c0d400d4c8`) and current stable 5.51.2-dev
(`7831952d6a970d1700ca82742ab2ac4dbe61ff80`). The latter still uses the released
26.60 protocol dependency; protocol master remains
`64eae48f05b8d1e4d474ead89711600996f1c5af`. CI also runs the pinned protocol-master
variant. Local native server runs passed 12 cycles on current stable and six on
released 5.51.0; both also passed all 16 standalone process cycles. Results and
limits are recorded in the changelog.

## Check an affected server

1. Keep a copy of the affected player/container/plugin data before another save.
2. Install one current library PHAR, update the content plugin's registration
   lifecycle and do a complete server restart.
3. Compare `Registry ready` counts/fingerprint between working and failing starts.
   Missing identifiers indicate incomplete registration; identical counts alone
   do not prove the identifier set is the same.
4. Read earlier startup exceptions and any `item will be replaced by AIR` lines.
   Include the content/GUI plugin name and its saved-data format in the report.
5. If server parsing and saved NBT are intact but the client shows empty slots,
   reconnect with the matching pack and collect the Bedrock content log.

Real Bedrock inventory rendering and the reporter's production plugin remain a
separate verification step. No commands or speculative protocol translations
were added.
