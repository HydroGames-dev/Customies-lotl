# Validation - 2026-10-24

- PHP 8.4.16, Axolotl-PM 5.50.1+dev, locked protocol 63.0.1+bedrock-1.26.50.
- All 33 block component and 42 item component classes have serializable catalog fixtures.
- All 46 block registrations and 52 item registrations succeed using real factories.
- Every runtime state for every showcase block survives disk serialization/deserialization.
- Targeted checks cover powered light, ghost collision, armor defense, fuel ticks, food and tool limits.
- Resource-pack JSON, atlas paths, 107 PNG textures, 25 geometry definitions, 16 rotor angles, connection bones, distinct manifest UUIDs and original Ogg audio pass validation.
- Both packaged plugins loaded and enabled on an isolated Axolotl server with the resource pack in its stack. Startup logged "Registered 98 specimens" and reached "Done". Async worker initialization completed without reported registration errors.
- Built PHAR files, MCPACK and install ZIP; SHA-256 checksums generated.

A Minecraft client was not available. In-game visuals, custom helmet alignment, all laboratory metadata, animation, recipe UI, connection prediction and protocol-specific client behavior still require client testing. The laboratory metadata does not implement the unsupported server gameplay listed in README.md.

## Selection-box correction (Customies 1.5.41 / Showcase 1.0.1)

The original showcase selection vectors used integer NBT elements when constructors
received integer pixels. The host's canonical data-driven block definitions encode
these vectors as FloatTag lists. A new palette-level test failed on the original
slab's origin vector and passes after explicitly converting all six coordinates to
floats in SelectionBoxComponent. This covers base components and permutations.

An in-memory world using the real World::useBreakOn() and Block::onBreak() checks
ray selection and removal of both slab halves, plus breaking either bench part in
each of four cardinal directions. Both bench parts are removed and one item drops.
These server and wire-format checks do not replace a Minecraft client playtest.

Replace both plugin PHARs from the rebuilt install bundle, restart the server, and
reconnect clients so the corrected palette is sent. Resource textures/models did
not change for this correction.

## Descriptive lore (Showcase 1.0.2)

All 98 specimens have explicit purpose/use descriptions. Tests check nonempty lore,
creative inventory entries, persistence through Item NBT serialization, bench drop
lore, and the command path that appends Prism batch/quality information. Existing
saved inventory lore is preserved; freshly obtained specimens get the new text.

## Combined examples and corner stairs (1.1.0 / Customies 1.5.42)

- Registered 51 blocks and 57 items; all runtime states round-trip through the host serializers.
- Lore and inventory persistence checked for all 108 specimens.
- Corner matrix covers five shapes, four directions, two halves, mixed-half rejection, neighbor removal and migration of older saved stairs.
- Explicit stair model cubes are compared with the PHP server collision boxes.
- Interactions exercise lamp brightness, gate collision/light, cable connections and light, spring landing/sneaking, a nine-slot crafting grid, repair-kit consumption/clamping, scout effects/cooldown, blade wear/effect and food/residue.
- Packaged Customies 1.5.42 and Showcase 1.1.0 enabled with resource pack 1.1.0 on Axolotl, registered 108 specimens, and reached Done.
- Every geometry, material texture and icon reference exported from the registered definitions resolves in the resource pack.

No Minecraft client playtest was available. These checks cover server logic and emitted definitions/assets, not client rendering, prediction or animation.
