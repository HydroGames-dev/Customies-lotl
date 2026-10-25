# Updating Customies yourself

Updating your installed PHAR is different from porting the library to a new host
API or Minecraft protocol. Use a separate checkout and test server for a port;
do not experiment by replacing production dependencies in place.

## Prepare a reproducible workspace

Use Git, the host's required PHP binary/extensions, Composer, Python for the site
and pack scripts, and an editor with PHP language support. Blockbench is useful for
models; Pillow/ffmpeg are only needed when regenerating the supplied example assets.
See [toolchain setup](toolchain.html) for how these tools fit together.

```text
git clone https://github.com/HydroGames-dev/Customies-lotl.git
git -C Customies-lotl status
git -C Customies-lotl switch -c maintenance/my-update <tested-base-ref>
git clone https://github.com/axolotl-pm/PocketMine-MP.git axolotl-test
composer --working-dir=axolotl-test install
```

Replace `<tested-base-ref>` with a real tag/commit/branch you intend to maintain.
Inspect the repository instructions and dependency lock before changing anything.
Use the fork's PHP build; stock web PHP often lacks required server extensions.
Record all commit SHAs and preserve the host's lockfile for a reproducible baseline.

## Decide what actually changed

| Change | What to inspect |
| --- | --- |
| A component gains fields | Wrapper constructor, value encoding, property mappings, fixture and docs export |
| A host API signature changes | Factories, serializers, conversion APIs, packet listener and workers |
| A Minecraft protocol/data version changes | Host protocol dependency, item/block data, packet types, palette/order assumptions |
| Your plugin changes saved states | Existing chunks, item NBT, deserializer migrations and rollback data |
| Resource pack visuals change | Atlas keys, geometry, attachables, version/UUID update policy and real client |

Changing a plugin API version or protocol number is not a compatibility port. Do
not advertise a new client version until the target host can accept it and the
registered content works with its actual data/conversion pipeline.

## Port a component carefully

1. Read the official reference/schema for the target version and compare canonical
   host definitions. Distinguish behavior-pack JSON from the network wrapper shape.
2. Add or update typed PHP fields and constructor defaults without silently changing
   existing caller behavior. Check actual NBT tag types, nested lists and constants.
3. Review item property mappings where the host expects legacy/parallel properties.
4. Update a minimal fixture and check the emitted definition, not just PHP syntax.
5. Implement matching server behavior if the feature needs it; metadata alone does
   not provide inventories, damage, placement restrictions or projectile logic.
6. Re-export documentation when wrapper classes change.

```text
php website/export-api.php /path/to/axolotl-test
python website/build.py
python website/check.py
```

The docs checker compares wrapper source hashes so a changed API cannot silently
leave old constructor documentation in place. Review the generated examples too:
a passing export does not prove native engine support.

## Validate in increasing layers

```text
php tests/run.php /path/to/axolotl-test
php examples/CustomiesShowcase/tests/guide.php /path/to/axolotl-test
python examples/CustomiesShowcase/tests/verify_assets.py
python examples/CustomiesShowcase/tools/build.py --php /path/to/server/php
```

The example plugin is optional for library users; maintainers can use it as a
fixture-rich integration test. It remains a separate example section in this wiki.
Use the asset generator first only if you changed generated assets; it needs its
additional dependencies. The builder can package already committed assets.

Then start an isolated server with the packaged library, your minimal content
plugin and the matching resource pack. Verify startup, worker initialization,
joining, placement, targeting, breaking, transactions, lighting and save/restart.
Test old saved content as well as fresh content. Test each intended client version
in Minecraft; PHP tests cannot verify render alignment or protocol prediction.

## Release and roll back deliberately

Publish a change summary with host/PHP/protocol data versions, migration notes,
checks performed and remaining client limits. Keep stable identifiers and preserve
pack UUIDs while increasing header/module versions for updates. Stop the server,
back up, install one library copy, restart and reconnect clients.

Keep the previous PHAR, pack and matching data backup. If a migration rewrites data,
rolling back only the code may not restore compatibility; restore the corresponding
backup in a controlled rollback. Do not use hot reload as a substitute for a
registration/schema restart.

Primary references: [Axolotl source](https://github.com/axolotl-pm/PocketMine-MP),
[Microsoft block components](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/blockcomponentslist?view=minecraft-bedrock-stable),
[GitHub workflow syntax](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax).
