# Customies

[![](https://poggit.pmmp.io/shield.state/Customies)](https://poggit.pmmp.io/p/Customies) [![](https://poggit.pmmp.io/shield.dl.total/Customies)](https://poggit.pmmp.io/p/Customies)

A Axolotl-MP plugin that implements support for custom blocks, items and entities.

## Community

<a href="https://discord.gg/Tm6wGxWqgh"><img src="https://img.shields.io/discord/989466131305754625?label=discord&color=7289DA&logo=discord" alt="Discord" /></a>

Official Discord community chat for socializing, receiving help with the plugin, and sharing creations. Join in on the fun!

## Documentation website

The [Customies field guide](https://hydrogames-dev.github.io/Customies-lotl/) contains walkthroughs, all 75 component references, coding examples, an interactive asset gallery, downloads and operator guidance. GitHub Pages must be enabled for the public URL to work. See [website build and publishing instructions](website/README.md).

## Usage

Customies 1.6.0 adds host registry checks, protocol 2193/2225 regression coverage,
runtime palette cache fixes and operator diagnostics:

```text
/customies status
/customies report
/customies inspect mycontent:lamp
```

Register definitions in `onLoad()`/`onEnable()` before the deferred worker snapshot.
Late registration now fails with a restart instruction. The host owns protocol
negotiation; 26.60 is a development API/codec target, not a live-client guarantee.
See [compatibility](https://hydrogames-dev.github.io/Customies-lotl/protocol-updates.html),
[diagnostics](https://hydrogames-dev.github.io/Customies-lotl/diagnostics.html) and
[1.6.0 changes](docs/changes-1.6.0.md).

Start with the [developer and operator guide](docs/customies-guide.md), covering all components, examples, structures, performance and server sizing.

Historical upstream usage guides are available at the [Customies Wiki](https://github.com/CustomiesDevs/Customies/wiki)!

Follow this Wiki https://github.com/nk-archives/Customies/wiki

[![Mojang Item Docs](https://img.shields.io/badge/ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬â€œ-Microsoft_Docs-blue)](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/itemreference/examples/itemcomponentlist?view=minecraft-bedrock-stable)
[![Mojang Block Docs](https://img.shields.io/badge/ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã¢â‚¬â€œ-Microsoft_Docs-blue)](https://learn.microsoft.com/en-us/minecraft/creator/reference/content/blockreference/examples/blockcomponents/blockcomponentslist?view=minecraft-bedrock-stable)

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
