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
