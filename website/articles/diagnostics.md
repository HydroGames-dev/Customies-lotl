## Inspect the running library

Customies 1.6.0 adds an operator-only command. The permission is
`customies.command.status`, granted to operators by default. Commands read server
registries; they do not modify blocks, give items, reload plugins or upload data.

```text
/customies status
/customies report
/customies inspect mycontent:lamp
```

The console accepts the same commands without `/`. If another plugin owns the
short name, use the plugin command's namespaced fallback as listed by `/help`.

| Command | Output | Use it when |
| --- | --- | --- |
| `status` | Library/host versions, Minecraft/protocol, custom definition counts and registration status | Confirm the loaded library and check that your plugin registered content |
| `report` | JSON with PHP, protocol target, relevant Composer package versions and refs, host contract errors and counts | Record an exact compatibility reproduction |
| `inspect namespace:name` | Block class, state domains and registry NBT bytes; or item network ID, component flag, registry version and NBT bytes | Check the definition actually registered under your identifier |

Item-entry counts include block items. Block-state counts count all registered
variants, not only the blocks placed in the world. NBT bytes measure an encoded
definition, not a player's total connection bandwidth or the server's RAM needs.
`inspect` reads a registered prototype, not a placed block's coordinates/current
state or a particular inventory stack's saved NBT.

## Interpret the results

**No blocks registered:** check plugin dependency order, startup errors and whether
the registration call ran. Use your full namespaced identifier. A resource pack
does not register server blocks by itself.

**Registration closed:** normal after startup. Move registration out of repeating
or delayed tasks and into `onEnable()`, then restart. Adding a component to an
already registered object will not rebuild every client/worker definition.

**Duplicate network ID:** allocate a separate type ID for each distinct block/item.
Reuse a single definition's ID when its constructor is called again by its factory
or a worker. Do not reuse one type ID across several named definitions.

**Unverified host protocol:** the host has a protocol number outside the pinned
test targets. Matching reflection fields are a basic contract check, not proof of
wire or client compatibility. Reproduce against the intended host/dependencies and
run the protocol suite before advertising support.

**Contract error on load:** the host removed or renamed a registry field Customies
uses. Update a compatible host/library pair. Do not disable the check and assume
the old reflection writes are safe.

**Valid definition, wrong rendering:** the report cannot validate texture paths,
geometry, client component support or prediction. Inspect the Minecraft content
log, pack versions and state-conditioned permutations using the
[debugging tutorial](tutorial-debugging.html).

## Diagnose a source checkout without a running server

```text
php tools/doctor.php /path/to/axolotl-checkout-with-vendor
```

The helper loads the specified host's Composer autoloader and prints the same
runtime report. Exit code 0 means the checked contracts exist; 1 means a contract
error; 2 means the host autoloader was not supplied/found. Standalone counts are
zero because your content plugins have not booted in that process. An unknown
protocol remains labelled unverified even when the contract check exits 0.

The report contains no player names, network addresses, tokens, world paths or
inventory contents. It makes no outbound requests. Add your minimal PHP definition,
exact pack version, expected/actual behavior and the relevant client content-log
message when preparing a useful bug report.

## A practical support workflow

1. Run `status` and confirm the installed version and counts.
2. Inspect the failing definition by identifier.
3. Record `report` with your reproduction; review anything else you attach.
4. Run the [browser tools](tools.html) for state products, boxes and identifier checks.
5. Run `tests/protocol.php` against the same host when maintaining a port.
6. Reproduce in a minimal content plugin, then test a real client and a restart.

The command is intentionally quiet during ordinary gameplay. There is no repeating
debug broadcast or per-tick registry scan.
