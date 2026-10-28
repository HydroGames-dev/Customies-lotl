# Customies 1.6.0

Historical notes: the library commands below were removed in 1.7.0. Use the current
diagnostics guide for the PHP API and source-checkout helper.

## Compatibility and correctness

- Add pinned Axolotl 26.50/26.60 API and wire-codec targets (protocols 2193/2225),
  including both host lockfiles and selected newer BedrockProtocol commits.
- Check the registry fields/entry constructor Customies uses at plugin load,
  before dependent plugins attempt registrations. Unknown protocol numbers are
  labelled unverified rather than silently advertised as supported.
- Invalidate `BlockTranslator::networkIdCache` after inserting/sorting block
  states. Existing vanilla IDs can move too; stale translations could show the
  wrong block even when the new custom dictionary itself was correct.
- Preserve incoming StartGame palette entries from other plugins, refresh the
  merged cache when its source changes, and avoid duplicate custom entries.
- Hash each distinct name once per sort, preserving intra-block state order.
- Use the public actor-identifier packet getter and replace cloned NBT, retaining
  existing encoded snapshots. Ensure a missing `idlist` is attached to the root.
- Keep the prior string-state-name fix in both serialization directions, including
  numeric PHP array-key regression coverage.

## Registration lifecycle

Blocks, items and entities must register in `onLoad()`/`onEnable()`. Customies
closes registration after its deferred startup worker snapshot. New late
registrations throw before invoking the factory callback; restart after changing
definitions. Worker-hook installation is idempotent.

Reject invalid/reserved identifiers, existing identifiers/network IDs and item IDs
outside the wire registry's signed 16-bit range. Prepare component NBT before
publishing registry entries so common definition errors do not leave parser aliases
or item entries. This is preflight validation, not a full transaction/rollback
mechanism for third-party callbacks or global registries.

## Diagnostics

Operators can use `/customies status`, `/customies report` and
`/customies inspect namespace:name`; permission `customies.command.status` defaults
to operators. The report shows PHP, host/protocol, relevant package versions/refs,
definition counts and registration lifecycle. It sends no network requests and
contains no player data or local paths. Inspect reports a registered prototype,
not a world-position or inventory-instance snapshot.

Developers can run `php tools/doctor.php /path/to/host` before booting a server.
The wiki adds dedicated compatibility and diagnostics guides and takes its library
version from `plugin.yml` so header/download labels match the packaged library.

## Verification

PHP 8.4.16: state, cloning, placement, cache, registry validation, entity NBT,
diagnostics and complete StartGame/ResourcePackStack/ItemRegistry/actor-registry
encode/decode regressions; all 139 showcase specimens and the runnable guide.
The new GitHub Actions matrix repeats these checks on the pinned host/protocol
combinations. Exact refs are in the workflow and
[compatibility guide](https://hydrogames-dev.github.io/Customies-lotl/protocol-updates.html).

Both packaged plugins also loaded/enabled on isolated localhost servers using the
26.50 and 26.60 host/protocol-head fixtures. Each reached `Done`, registered 139
specimens and completed startup without reported worker registration errors.
Console status/report/inspect commands were exercised; status showed 73 blocks,
3210 variants and 139 item entries with registration closed. Both servers stopped
through their console command. These are server smoke checks, not client playtests.

Customies does not translate protocols. The host must supply matching codecs/data
and accept the client version. Live Minecraft client visuals, prediction and
26.52/26.60 connections have not been verified in this update.
