# Development tools and workflow

Use tools for a particular layer instead of expecting one editor or validator to
prove everything. The [browser toolbox](tools.html) handles small calculations and
starter snippets locally; host tests and Minecraft are still needed for runtime
and visual verification.

| Tool | Use it for | It does not prove |
| --- | --- | --- |
| Git | Isolated branches, diffs, upstream comparisons and release history | That a merged change works in the target host |
| Host PHP binary | Syntax, real class signatures, NBT and server tests | Client appearance or protocol acceptance by itself |
| Composer | Install the host's locked dependencies | That arbitrary dependency updates are compatible |
| PHP-aware editor | Navigation, type assistance, constructor/hook signatures | Correct NBT field types or gameplay |
| Blockbench | Original meshes, UVs, bones and animation authoring | Server collision or inventory behavior |
| Image editor | Textures and animation frame strips | Correct atlas/material references |
| Python | Documentation generation and pack packaging scripts | Minecraft compatibility |
| Pillow / ffmpeg | Regenerate the example's procedural textures/audio | Required tools for every Customies plugin |
| Real Minecraft client | Rendering, camera poses, content log and prediction | Server-side authority or migration correctness alone |
| Host profiler / timings | Actual tick and plugin cost under load | Universal player capacity from one small sample |

## Keep three environments

Use a source checkout for editing, a disposable test server for packaged checks,
and production for accepted releases. Keep test players/worlds and ports separate.
Record the exact PHP binary and host commit used by each environment. A website
build can use normal Python; a server test needs the host's specialized PHP build.

## Read a failure at the correct layer

Syntax errors belong to PHP/editor checks. Unknown identifiers or bad state
serialization belong to factory/persistence tests. Missing models and textures
belong to pack references/client logs. Low FPS can be client rendering even when
server ticks are healthy. Slow interactions can be lighting, listeners or repeated
world writes rather than registration overhead.

## Build the documentation

```text
python -m pip install -r website/requirements.txt
python website/build.py
python website/check.py
node --check website/app.js
node website/authoring.test.cjs
```

The source-only build omits install downloads. For release downloads, first run
the pack builder and pass its output directory with `--downloads`. Use a clean
output directory when switching modes so old artifacts do not linger.

The GitHub Pages workflow builds and checks the site from committed source, then
deploys through the configured environment. Repository Pages settings and allowed
deployment branches must be configured by someone with the appropriate access.
No credentials are needed to use the browser tools.
