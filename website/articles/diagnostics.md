## Customies has no in-game commands

Customies 1.7.0 removes the library command handler, registrations and command permission. Developer diagnostics remain available through a PHP API and a standalone source-checkout tool. The optional CustomiesShowcase example keeps its separate demo commands.

## Check a source checkout

```text
php tools/doctor.php /path/to/axolotl-checkout-with-vendor
```

The helper loads that host's Composer autoloader and prints PHP, host/protocol and relevant package versions/references. It checks the registry fields Customies uses. It does not start a server or load content plugins, so standalone definition counts are zero.

| Exit code | Meaning |
| --- | --- |
| 0 | Checked contracts exist; an unknown protocol is still labelled unverified |
| 1 | A checked registry contract is missing |
| 2 | The host checkout/autoloader was not supplied or found |

Run the component, state and packet tests against the same host, then test a real server/client. See the [compatibility matrix](protocol-updates.html) for pinned 26.60 targets.

## Inspect registrations from your own PHP

```php
use customiesdevs\customies\util\Diagnostics;

$runtime = Diagnostics::report();
$definition = Diagnostics::inspect('mycontent:lamp');
```

Call after registration. Keep any logging or UI you add bounded and permission-gated. These calls do not send requests, change blocks, give items or reload registries.

`report()` includes registration status, custom blocks, state variants, item entries and entities. Item-entry counts include block items. These are definition counts, not a world scan or a memory/TPS estimate.

For blocks, `inspect()` returns the registered class, state domains and encoded definition NBT size. For items, it returns the network ID, registry version, component flag and NBT size. It reads a registered prototype, not a placed block's current values or an inventory instance. Unknown identifiers throw `InvalidArgumentException`.

## Interpret failures

**Missing definition:** check dependency order, startup errors and the namespaced identifier. Resource packs do not register server blocks.

**Registration closed:** normal after the worker snapshot. Register during `onLoad()`/`onEnable()` and restart after changing definitions.

**Duplicate network ID:** allocate separate type IDs for separate definitions, then reuse each ID when its constructor runs again on a worker.

**Host contract error:** install a compatible host/library pair. Do not bypass the check and assume old reflection writes remain safe.

**Wrong appearance:** check pack versions, texture/model identifiers, state conditions and Minecraft's content log. Use the [debugging tutorial](tutorial-debugging.html) and [browser tools](tools.html). The report cannot validate rendering or prediction.

The built-in report contains no player names, network addresses, tokens or world paths. Review anything else you attach to a bug report.
