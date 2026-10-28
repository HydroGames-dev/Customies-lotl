# Customies for Axolotl-PM

Register custom blocks and items with Bedrock client definitions, states, traits
and resource-pack assets. Your PHP content plugin owns the gameplay. Customies
is a library dependency; CustomiesShowcase is an optional example project.

## Start here

- [Guide and API reference](https://hydrogames-dev.github.io/Customies-lotl/): installation, concepts, 75 component references and tested examples.
- [Build tools](https://hydrogames-dev.github.io/Customies-lotl/tools.html): configurable item/block generation, collision/selection previews and native JSON conversion.
- [Migration Lab](https://hydrogames-dev.github.io/Customies-lotl/migration.html): bring scripts, JSON and pack archives into a PHP migration project.
- [Changelog](CHANGELOG.md): concrete changes, migration requirements and verification.
- [Downloads](https://hydrogames-dev.github.io/Customies-lotl/downloads.html): library and optional example bundles.

## Current target

Customies 1.9.0 targets Axolotl API 5.51.0 and Minecraft Bedrock 26.60 / protocol
2225. The host handles protocol negotiation. This library does not translate
arbitrary protocols or run behavior-pack JavaScript, and registers no in-game commands.

Register content in `onLoad()`/`onEnable()` before the deferred worker snapshot.
Install the matching resource pack, restart and reconnect after definitions change.
See the [compatibility matrix](https://hydrogames-dev.github.io/Customies-lotl/protocol-updates.html)
and [verification record](docs/bedrock-26.60-verification.md). Automated PHP/packet/asset
tests do not replace a real Bedrock client playtest.

## Development

Use a compatible Axolotl checkout with its locked Composer dependencies. Run the
[verification workflow](docs/bedrock-26.60-verification.md), then build the
[documentation](website/README.md). Browser tools use pinned npm dependencies;
inputs stay local and uploaded scripts are parsed rather than executed.

Historical upstream documentation remains at the [Customies Wiki](https://github.com/CustomiesDevs/Customies/wiki).
Native concepts are documented in the [Microsoft item](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/itemreference/examples/itemcomponentlist?view=minecraft-bedrock-stable)
and [block](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/blockcomponentslist?view=minecraft-bedrock-stable) references.

## Community

[Customies Discord](https://discord.gg/Tm6wGxWqgh) is available for community discussion and historical upstream support.

## Important Contributors

| Name                                                  | Contribution                                                                                 |
|-------------------------------------------------------|----------------------------------------------------------------------------------------------|
| [TwistedAsylumMC](https://github.com/TwistedAsylumMC) | Helped research and develop the first versions of Customies as well as maintain the code     |
| [DenielW](https://github.com/DenielWorld)             | Helped research and develop the first versions of Customies                                  |
| [Abimek](https://github.com/abimek)                   | Helped develop the item components implementation and block-related bug fixes                |
| [Unickorn](https://github.com/Unickorn)               | Maintained the code during the PM4 betas and kept it up to date                              |
| [JackNoordhuis](https://github.com/JackNoordhuis)     | Suggested the idea of using async workers and helped write the code which made them function |
> Future contributors will not be added to this list. This is a list of the contributors who helped develop the plugin before the public release.
## Traits and presets

See [traits, presets, compatibility, and regression checks](docs/traits-and-presets.md).

## Showcase plugin and assets

[Customies Showcase](examples/CustomiesShowcase/README.md) includes 73 blocks, 66 items, a resource pack, complete component specimens, and reproducible build tools.
