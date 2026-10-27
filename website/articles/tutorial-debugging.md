# Tutorial: diagnose a broken block

Work from registration to saved data to visuals, changing one layer at a time.
Do not start by changing protocol numbers or deleting production worlds.

## 1. Reduce the reproduction

Use a separate test server and one content plugin. Record the exact host/PHP,
Customies and resource-pack versions. Reproduce with one block and the smallest
state set. Preserve an old saved fixture if the failure only happens after reload.
Use `/customies status` and `/customies report` to record the running registry and
dependency information; these operator commands are explained in [diagnostics](diagnostics.html).

## 2. Check registration

Verify the factory closure returns the intended class with the same allocated ID.
Confirm states/traits exist before the parent constructor calculates the layout.
Look for duplicate state names or identifiers. A missing registration is not fixed
by renaming a texture file.

## 3. Inspect bounded data

Read the actual placed state's values, not just constructor defaults. Record the
identifier and position and compare before/after interaction. For items, inspect
tag types and schema version. Keep logs bounded and permission-gated.

## 4. Compare client and PHP shapes

Match model-pixel boxes against local PHP AABBs using the
[box converter](tool-boxes.html). Check every half/direction/part. A visible stair
or slab that cannot be targeted may have mismatched selection or server ray shape.
Rotating a mesh does not rotate a manually supplied PHP box automatically.

## 5. Check materials and state conditions

Confirm namespaced state names, scalar types, matching permutation order and
whole-component replacement semantics. Verify atlas keys and geometry identifiers.
Use the [wrapper inspector](tool-components.html) to inspect readable values, while
remembering its example shape is not a complete native Bedrock schema.

## 6. Verify authoritative behavior

Does PHP actually change collision/light or apply the effect? Is the updated block
written into the world? Is the item consumed twice by a listener and a use hook?
Does a protection/event cancellation path preserve inventory and world data?

## 7. Verify a real client and a restart

Reconnect with the new pack and inspect the client content log. Test both camera
perspectives for attachments and every intended client version. Save/restart and
retest old data. Report which layer failed, the minimal reproduction, expected
behavior and relevant bounded logs—not an unsupported blanket compatibility claim.
