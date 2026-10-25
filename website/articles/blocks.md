# Introduction to blocks

A custom block needs a server class, a stable registration identifier, client
components and compatible resource-pack assets. Start with `TraitBlock` when you
need placement states or permutations; it supplies the state lifecycle integration.

## The definition pipeline

1. Allocate a block type ID once in your plugin's startup.
2. In your block constructor, add states and traits before the parent constructor.
3. Add geometry, materials, mining/selection/collision and other components.
4. Register a factory closure that reuses the allocated ID.
5. Supply every referenced asset in your own pack.
6. Implement PHP behavior that matches the client definition.

The factory maps your identifier into runtime and saved block state handling.
Do not construct client network IDs yourself. A block's `asItem()` creates its
placeable item; item appearance, placement and server behavior still need to agree.

## What belongs where?

| Requirement | Place to implement it |
| --- | --- |
| What a block looks like | Geometry/material components and resource pack |
| A persistent power or phase | BlockState declared before parent construction |
| Placement direction/half | A compatible trait or preset |
| State-dependent client appearance | BlockPermutation |
| Physical server shape | PHP collision/targeting implementation |
| Mining and tool requirements | PHP BlockBreakInfo/tool behavior and matching metadata |
| Click action | `onInteract()` with normal host event/protection handling |
| Light | Matching PHP light methods and client components |
| What breaks as a group | Correct affected-block handling and drops |

## Two coordinate systems

Block model components normally use pixels: 16 pixels spans one cell, with local
X/Z centered around -8 to 8 and Y from 0 to 16. Server AABBs use block units in
the local 0-to-1 cell. Transform both shape systems deliberately; a large visual
does not reserve more world cells or increase server collision automatically.

## Pick your next topic

- [Complete first block tutorial](coding.html#create-a-working-block)
- [All block components](block-components.html)
- [Custom block states](block-states.html)
- [Placement, connection and multi-block traits](block-traits.html)
- [State-dependent permutations](block-permutations.html)
- [Textures, geometry and animation](assets.html)

The library guide is independent of the optional example-content plugin. Learn
the API here, then inspect examples separately when you want a larger implementation.
