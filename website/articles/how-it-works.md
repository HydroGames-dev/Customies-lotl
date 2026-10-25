# How Customies works

Customies connects three systems: PocketMine's authoritative objects and saved data,
Bedrock's client definitions, and your resource pack. It is a registration and
translation layer, not a replacement for the host server or a behavior-pack script
runtime. Your plugin still implements gameplay in PHP.

## Startup: build definitions once

1. The Customies plugin registers its packet listener and schedules deferred worker
   initialization. Your dependent content plugin registers in `onEnable()`.
2. `CustomiesBlockFactory` calls your block factory, initializes traits, registers
   runtime states, and creates the block's item registration.
3. Components and traits are serialized into client definition NBT. State values
   are enumerated into runtime/palette combinations; permutations are included as
   client conditions, not executed as PHP.
4. Serializer/deserializer mappings connect stable identifiers and states to the
   host's saved-data handlers.
5. The async worker initialization hook reconstructs compatible block registrations
   in workers that need to process block data.

Allocate one type ID outside the closure and reuse it. Do not capture a Player,
World, mutable inventory or other live object in a worker-reconstructed factory.
Keep construction deterministic and register before the deferred initialization.

## Joining: describe content to the client

The listener merges custom and host block definitions for `StartGamePacket`, sorts
the combined palette in the required identifier order and caches it by registration
count. It also merges the branch's required experiment flags into startup and pack
stack metadata. The cache is not an invitation to hot-reload arbitrary definitions:
restart and reconnect after registration changes.

Item registration maps saved identifiers and the item parser, adds creative entries,
constructs component NBT and supplies the host's item conversion mappings. Runtime
type IDs and palette order are implementation details; do not save network IDs as
your content's permanent identity.

The resource pack supplies the referenced meshes, textures, sounds and animations.
A correct server definition with a missing or stale pack can still look broken.
An accepted pack does not prove the PHP interaction or collision is correct.

## Playing: PHP owns the result

For placement, TraitBlock applies trait placement hooks through the transaction
before normal block placement. Neighbor-dependent traits refresh state when the
host notifies the block. Your overrides handle lighting, collision, use effects,
replacement, drops and other authoritative rules.

For a powered lamp, the client evaluates a state condition to choose the lit tile.
PHP changes that power state, returns the corresponding light level and writes the
block into the world. Neither side should independently invent a different power
value. Client prediction is a convenience; it is not the authority for rewards,
damage, ownership or inventory contents.

## Saving: identifiers outlive runtime IDs

Block state handlers serialize namespaced state values. Item handlers map custom
identifiers and the host saves stack data. A new startup can allocate different
runtime IDs while retaining the same saved identifier/schema. Removing a registered
identifier or changing its state types can therefore break old content even when
freshly created objects look fine.

Read [the data guide](data-guide.html) before storing additional state, and
[the maintainer guide](updating-customies.html) before changing protocol mappings.

## Where to read the implementation

| Area | Source |
| --- | --- |
| Plugin startup | `src/Customies.php` |
| Join definitions and experiment merge | `src/CustomiesListener.php` |
| Block/state/serializer registration | `src/block/CustomiesBlockFactory.php` |
| Item NBT and conversion mappings | `src/item/CustomiesItemFactory.php` |
| Trait lifecycle | `src/block/TraitBlock.php` and `src/block/traits/` |
| Worker registration | `src/task/AsyncRegisterBlocksTask.php` |
| PHP values to NBT | `src/util/NBT.php` |

This description follows this branch's source. Other forks or future host versions
may have different packet and conversion interfaces.
