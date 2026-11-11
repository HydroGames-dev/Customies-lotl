# Customies changelog

Version history for this repository's published documentation branch. Library,
website and example-plugin changes are identified separately. A passing host test
does not establish Bedrock client rendering or prediction compatibility.

## 1.10.0 · 2026-11-11

**Areas:** library, showcase, tools, website, compatibility

### Added

- `RedstoneConductivityComponent`, the only block component Mojang's 26.60 host
  networks for vanilla data-driven blocks that Customies did not wrap. It sends the
  processed client fields `redstoneConductor` and `allowsWireToStepDown`; defaults
  follow Mojang (not a conductor, wire may step down). Showcase 1.5.0 (resource
  pack 1.5.0, 140 specimens) adds a laboratory specimen whose value matches
  Axolotl's server wire rule; reinstall the pack so clients fetch the new version.
- `BedrockVersion` gates keyed on the host's network version. `collision_box_32`
  raises `CollisionBoxComponent::getMaxBoxes()` from 16 to 32 once the host runs
  1.26.70; on 26.60 hosts nothing changes. `MAX_BOXES` stays 16, limit errors name the
  host version, and `tools/doctor.php` reports every gate under `bedrock_features`.
- `tools/component-coverage.php` compares Mojang's newest block and item component
  schemas with the wrapped components, including from sparse clones and the preview
  branch. `--strict` fails on unclassified new components.
- A [Bedrock version support record](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/docs/bedrock-version-support.md)
  with 26.60 coverage, server-side equivalents, Axolotl 5.51 redstone notes, 1.26.70
  preview status and an update checklist.

### Changed

- The authoring and migration converter translates `minecraft:redstone_conductivity`.
  It reports specific PHP guidance for `vibration_properties`, `neighbor_change` and
  `enchantment_power_transmitter` (26.60) and `jukebox_playlist_source` (1.26.70
  preview) instead of a generic manual-work note, and does not send them to clients.
- The converter accepts format 1.26.70 `destructible_by_mining.base_time_to_destroy`
  (server ticks) and converts it to seconds. Previously the field was dropped and
  the block was generated with a zero mining time.

### Upgrading from 1.9.1

- Replace the Customies PHAR and restart. The target is still Axolotl API 5.51.0 /
  protocol 2225, and existing content needs no code changes.
- `CollisionBoxComponent::MAX_BOXES` is still 16. Code that checks the limit itself
  should call `CollisionBoxComponent::getMaxBoxes()`, which follows the host
  version. The overflow message now names the limit and host version, so code that
  matches the old message text must be updated.
- For custom blocks that take part in redstone, add `RedstoneConductivityComponent`
  with the value Axolotl computes (`isFullCube() && !isTransparent()`), and implement
  `pocketmine\block\utils\RedstoneSignalEmitter` on blocks that wire should connect to.
- Convert format 1.26.70 packs that use `base_time_to_destroy` again: 1.9.x output
  gave those blocks a zero mining time. Packs at earlier formats are unaffected.
- Showcase users: install Showcase 1.5.0 with resource pack 1.5.0 and reconnect
  clients so they download the new pack version.

### Compatibility and verification

- Rechecked Axolotl stable `7831952d6a970d1700ca82742ab2ac4dbe61ff80` (unchanged
  protocol 2225), Mojang bedrock-samples 1.26.60.5 `15701fa` and preview 1.26.70.23
  `4301426`. No host supports 1.26.70 yet; 1.26.70 support is limited to the gates
  and converter handling above.
- Every block wrapper with a vanilla counterpart in the host's
  `data_driven_blocks.nbt` matches its field names and tag types. `tests/mc2660.php`
  compares redstone conductivity against all 96 vanilla definitions; the new
  `tests/versions.php` covers version parsing and the 1.26.70 gate. No Bedrock client
  was connected.

## 1.9.1 · 2026-11-05

**Areas:** library, showcase, tools, compatibility

### Fixed

- Merge current custom item/block-item definitions into early or cached login
  item-registry packets. Replace stale own entries, preserve other entries and
  subclient routing, and avoid duplicate definitions or mutation of shared packets.
  Conflicting plugin snapshots cancel the affected login without crashing the
  server event loop.
- Register a direct name-only custom block-item deserializer and both directions
  of the host block/item mapping. Full stack/state NBT remains supported.
- Reject existing parser aliases before publishing saved-item mappings, preventing
  partial registration when an exception is caught by a content plugin.
- Register Showcase 1.4.3 and newly generated/migrated definitions in `onLoad()`
  before inventories/worlds are read. Event setup remains in `onEnable()`.

### Diagnostics and verification

- Log registration counts and a stable identifier-set fingerprint after startup;
  expose the fingerprint through the PHP diagnostics API. No commands added.
- Add 16-fresh-process persistence regressions with changed runtime IDs and order,
  sparse slots, names/lore, typed nested tags, block state and 26.60 inventory/registry
  codecs. The suite passed on released Axolotl 5.51.0 and current stable 5.51.2-dev.
- Native server testing passed 12 independent restarts on current stable and six
  on released 5.51.0, retaining a real chest, native world block state, synthetic
  player-file inventory data and all Showcase definitions. No real Bedrock client
  was connected.
- Independently reproduce the name-only reload, early registry snapshot and
  partial alias failures against unchanged 1.9.0. The reporter's production
  inventory plugin/startup log was unavailable, so its exact trigger remains
  unconfirmed; already overwritten data cannot be reconstructed by this update.
- Pin current stable `7831952d6a970d1700ca82742ab2ac4dbe61ff80` in CI and add
  process/server restart checks. BedrockProtocol master remains
  `64eae48f05b8d1e4d474ead89711600996f1c5af`; the target stays 26.60 / protocol 2225.

See the [restart investigation and test instructions](https://github.com/HydroGames-dev/Customies-lotl/blob/feat/block-traits-presets/docs/restart-persistence.md).
Sources: [Axolotl stable snapshot](https://github.com/axolotl-pm/PocketMine-MP/tree/7831952d6a970d1700ca82742ab2ac4dbe61ff80),
[26.60 protocol snapshot](https://github.com/axolotl-pm/BedrockProtocol/tree/64eae48f05b8d1e4d474ead89711600996f1c5af).

## 1.9.0 · 2026-10-28

**Areas:** library, tools, website, compatibility

### Added

- A separate Migration Lab with a script/pack converter, migration walkthrough
  and supported-pattern matrix. Inputs include .js, JSON/JSONC, .mcpack, .mcaddon
  and ZIP archives, including nested behavior/resource packs.
- AST-based translation of supported Script API item-use/break subscriptions and
  bound custom item/block callbacks into separate PocketMine listener methods.
  Supported player actions include messages, action bars, effects, XP and fire/motion.
- Project ZIP exports containing PHP source, resource files, originals and a
  migration report with source locations. Unsupported callbacks remain entirely
  manual; no uploaded script is executed.
- Generator controls for lore, uploaded PNG textures, hardness, light, food,
  durability, armor slots/defense, additional states and component overrides.
- Forest, Ocean and Ember palettes, light/dark modes, bundled variable fonts,
  responsive section tabs, reduced-motion-aware transitions and active chapter links.
- This filterable changelog, a rewritten documentation home and a clearer first-use
  guide. CustomiesShowcase remains a separate example section.

### Fixed

- Normalize legacy single collision-box objects to arrays in exported native
  definitions. Mojang requires arrays for box objects at format version 1.26.60.
- Match generated PHP collision and selection boxes to client Y-axis quarter-turn
  transformations, including matching state permutations. Other transformations
  receive explicit manual-review notes.
- Preserve binary assets when exporting ZIPs and isolate each translated callback's
  early returns. Bound archive extraction checks size, paths, checksums and nesting.
- Match script identifiers through cached Bedrock names, preserving colored-block
  distinctions and shared potion identifiers. Leave JavaScript coercion patterns
  with different PHP semantics for manual work.

### Compatibility and verification

- Rechecked the released Axolotl 5.51.0 host, stable commit
  `61eb9b4e23457145efd39ae4785825d4045c765f` and BedrockProtocol master
  `64eae48f05b8d1e4d474ead89711600996f1c5af` on 2026-10-28. The target remains
  Bedrock 26.60 / protocol 2225; this release does not add a protocol translator.
- Extend automated coverage to generated migration classes, real event execution,
  custom component binding, resource copying, compressed packs and malformed inputs.
  Existing palette, packet, state, slab and 40 stair-variant checks remain required.
- Packaged startup checks enabled Customies 1.9.0 and four generated migration
  plugins on isolated Axolotl 5.51.0 with two packs, 74 blocks, 3213 states and
  141 item entries. Registry freezing and clean shutdown passed.
- Browser visual checks remained blocked by the browser tool's saved permission
  policy after approval and a reported reset. Static site/resource checks passed;
  the new responsive appearance and interactive upload flow remain unverified in-browser.
- Native 26.60 experimental APIs, including named ticks, entity storage and newer
  metadata, still require an explicit host/gameplay implementation where unsupported.
  Real Bedrock appearance, input behavior and prediction require client playtests.

Sources: [Mojang 26.60 changelog](https://feedback.minecraft.net/hc/en-us/articles/49852828563853-Minecraft-Bedrock-Edition-26-60-Changelog),
[Microsoft custom components](https://learn.microsoft.com/en-us/minecraft/creator/documents/scripting/custom-components?view=minecraft-bedrock-stable),
[Axolotl stable](https://github.com/axolotl-pm/PocketMine-MP/tree/61eb9b4e23457145efd39ae4785825d4045c765f),
[protocol snapshot](https://github.com/axolotl-pm/BedrockProtocol/tree/64eae48f05b8d1e4d474ead89711600996f1c5af).

## 1.8.0 · 2026-10-28

**Areas:** library, tools, website, showcase

- Added item/block generators, JSON-to-PHP conversion, collision/selection previews
  and a 40-variant stair rotation workbench.
- Added `SlabBlock`, `SlabPlacement` and `BlockPresets::slab()` for same-cell merging,
  single/double collision, support faces, saved-state migration and two-slab drops.
- Showcase 1.4.2 corrected stair model X origins to match voxel bounds and vanilla
  corners while preserving the cardinal rotation mapping.
- Verified generated PHP, slab placement/save/reload/drops, mesh occupancy, packaged
  server startup, Pages deployment and public download checksums.

## 1.7.0 · 2026-10-28

**Areas:** library, compatibility

- Targeted released Minecraft 26.60 dependencies on Axolotl API 5.51.0, protocol 2225.
- Removed library commands and command permissions; retained the PHP diagnostics
  API and standalone host doctor.
- Added canonical collision NBT, mirrored min/max coordinates, fractional box
  conversions, block-entity field and custom item-sound checks.
- Published a three-target host/protocol compatibility workflow and versioned downloads.

## 1.6.0 · 2026-10-27

**Areas:** library, compatibility, website

- Added host contract diagnostics and protocol/cache validation.
- Expanded state, registry and packet checks across pinned Axolotl snapshots.
- Added compatibility guidance and updated library documentation.

## Maintaining this log

For each release, add a dated entry with its version, areas, concrete behavior
changes, migration requirements and test evidence. Link upstream changes when
they affect compatibility. Mark experiments and manual work explicitly. Update
the host/protocol pins and verification record together; do not infer support from
a version number alone. The website renders this file directly.
