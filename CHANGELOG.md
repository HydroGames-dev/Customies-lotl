# Customies changelog

Version history for this repository's published documentation branch. Library,
website and example-plugin changes are identified separately. A passing host test
does not establish Bedrock client rendering or prediction compatibility.

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
