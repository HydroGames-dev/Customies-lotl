# Bedrock 26.60 verification · 2026-10-28

This record separates current upstream data, automated host tests and client tests.
The repository targets Axolotl API 5.51.0 and protocol 2225. No newer protocol was
found in the checked Axolotl stable and BedrockProtocol master commits.

## Sources checked

| Source | Snapshot / finding |
| --- | --- |
| [Mojang 26.60 release notes](https://feedback.minecraft.net/hc/en-us/articles/49852828563853-Minecraft-Bedrock-Edition-26-60-Changelog) | Box objects use collision arrays at format 1.26.60; boolean bone visibility restored; custom item sounds and experimental metadata documented |
| [Axolotl release 5.51.0](https://github.com/axolotl-pm/PocketMine-MP/tree/f5e6a67fdf2108cf63196c06db1eb5c0d400d4c8) | Released host and locked protocol/data packages |
| [Axolotl stable](https://github.com/axolotl-pm/PocketMine-MP/tree/61eb9b4e23457145efd39ae4785825d4045c765f) | Current stable snapshot checked |
| [BedrockProtocol master](https://github.com/axolotl-pm/BedrockProtocol/tree/64eae48f05b8d1e4d474ead89711600996f1c5af) | Current development snapshot checked; protocol 2225 |
| [Mojang schemas](https://github.com/Mojang/bedrock-samples/tree/46ba6ea985fb5a92d79a9419198f10dda14c199d/metadata/json_schemas) | Current repository snapshot checked. Its block directory contains an older Facing fragment; item schemas include 1.26.30/beta. These are not a complete 26.60 validation schema |
| [Microsoft custom components](https://learn.microsoft.com/en-us/minecraft/creator/documents/scripting/custom-components?view=minecraft-bedrock-stable) | Modern namespaced component bindings and Script API registration model |

Use the released host's actual canonical block NBT for network metadata checks.
Behavior-pack JSON and processed client-definition NBT have different shapes; do
not replace the network `enabled`/`boxes` envelope with an authoring JSON array.

## Automated coverage

- `tests/protocol.php`: state enumeration/persistence, registry and join packet
  encoding/decoding, palette ordering, cache invalidation and host contracts.
- `tests/mc2660.php`: canonical collision NBT, mirrored voxel coordinates,
  fractional boxes, disabled/multiple boxes, block-entity fields, boolean bone
  visibility, custom sounds, removed library commands and permissions.
- `examples/CustomiesShowcase/tests/guide.php`: all 139 specimens, PHP guide examples,
  abilities, inventories, structures, lore, save/reload and grouped breaking.
- `examples/CustomiesShowcase/tests/shapes.php`: real World placement/break paths,
  slab merge acceptance/support against vanilla, saved doubles and drops, plus
  40 actual stair meshes compared with server and vanilla occupancy.
- `website/authoring.test.cjs` and `website/check-php.php`: generated classes on
  actual host APIs, configured food/armor/slabs, literal lore, 26.60 JSON
  normalization and four-direction asymmetric collision/selection ray tests.
- `website/migration.test.cjs` and `website/check-migration.php`: compressed nested
  packs, entry/import graphs, JSONC, binary assets, generated registrations and
  real PocketMine event classes; item/block filtering, effects and messages;
  unsupported whole callbacks, literal PHP strings and bounded archive failures.
- Asset/site checks validate JSON, PNG references, models, animations, search
  scopes, links, fonts, licenses, changelog entries and public artifact checksums.

## Limits and client acceptance

The packaged 1.9.0 library and four generated migration plugins also enabled on
an isolated Axolotl 5.51.0 server with two resource packs: 74 custom blocks, 3213
states and 141 item entries. Registration froze and shutdown completed cleanly.
The test used RakNet and a copy of the existing isolated test world; it did not
exercise WebRTC or player connections.

Current browser visual checks were blocked by the browser tool's saved permission
policy, including after the user authorized access and reported clearing the denial.
Site links, scopes, resources, fonts and licenses passed automated checks; browser
appearance, uploads and responsive interactions remain unverified for this update.

These are Axolotl/PHP, packet/data and resource-file tests. A Bedrock game client
was not connected, so they do not prove client rendering, animations, custom sound
playback, controller/touch interaction or prediction. Browser previews also do not
substitute for a client playtest.

Before production, join a test server with the matching pack. Check every physical
orientation, cancelled/protected actions, food/durability, inventories, drops,
save/reload and two-player behavior. Inspect the Bedrock content log. Keep
experimental APIs and unsupported Script API features marked as manual work.
