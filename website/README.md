# Customies field guide website

Static, accessible GitHub Pages documentation generated from repository sources.
No analytics, external fonts, runtime framework or backend is required. Markdown
library guide is independent of the optional example plugin. Catalog examples, gallery entries,
textures and interactive footprint models come from the showcase.

## Build and preview

```text
python -m pip install -r website/requirements.txt
npm ci --prefix website --ignore-scripts
python examples/CustomiesShowcase/tools/build.py --php php
python website/build.py --downloads examples/CustomiesShowcase/dist
python website/check.py
python -m http.server 8765 --bind 127.0.0.1 --directory website/dist
```

Open http://127.0.0.1:8765/. Build output is ignored. Omit `--downloads` for a
source-only site; download links appear only when the corresponding files exist.
Build into a clean output directory if changing download modes, so old artifacts
are not retained. Artifact mode uses the pack builder's SHA256SUMS for validation.

## Publishing on GitHub Pages

Repository: `HydroGames-dev/Customies-lotl`.
Expected URL: https://hydrogames-dev.github.io/Customies-lotl/

The workflow `.github/workflows/pages.yml` builds PHARs/resource packs, generates
and checks the website, uploads the artifact, then deploys it with the official
GitHub Pages actions. It runs for the documentation feature branch and the default
branch, and can also be run manually. Pull requests build/check without deploying.

One-time repository setup, if Pages is not enabled:

1. Open repository **Settings → Pages**.
2. Set **Build and deployment → Source → GitHub Actions**.
3. In **Settings → Environments → github-pages**, ensure the publishing branch is
   allowed by deployment branch rules. This work currently lives on
   `feat/block-traits-presets`; after merging, use the default branch policy.
4. Re-run the Pages workflow. Its deployment output gives the actual live URL.

If a prebuilt `gh-pages` branch is provided instead, you may choose **Deploy from
a branch → gh-pages → /(root)**. Do not enable both publication methods at once;
disable the workflow deployment job if retaining branch publication.
The initial prebuilt branch is a snapshot; switch to Actions for automatic updates
from guide/catalog source changes.

The connected repository tools do not expose Pages administration. A signed-in
repository administrator may need to make that one-time setting change. Do not
interpret an uploaded branch or artifact as proof the public URL is live.

## Sections

`index.html` is the Customies library Guide/Wiki. It documents installation, your own
plugins, API components, resource packs, performance and troubleshooting.
`showcase.html` is the separate CustomiesShowcase example section, with its own
installation, walkthroughs, prefab demo, catalog, downloads and benchmark.
Navigation, previous/next links, branding and search indexes are scoped to each
section. The section switch explicitly links between them.

## Editing

- `website/core-guide.md`: library-only installation, architecture, resources and operations.
- `docs/customies-guide.md`: source of reusable API tutorial/component material.
- `docs/traits-and-presets.md`: trait detail and compatibility boundaries.
- `website/walkthroughs.md`: library walkthroughs for your own plugin.
- `website/showcase-walkthroughs.md`: walkthroughs of the optional demo implementation.
- `website/build.py`: page structure, catalog extraction and asset copying.
- `website/style.css`: responsive documentation theme, print styles and reduced motion.
- `website/app.js`: local search, copy controls, gallery filters and canvas previews.

The canvas is an explanatory geometry viewer, not a Bedrock renderer or gameplay
simulation. Powered preview colors are illustrative. Verify real textures,
lighting, placement prediction and animation playback with a Minecraft client.
PHP tutorial examples are checked by `tests/guide.php`; site generation and
`check.py` check coverage and static navigation. No changes to server behavior are
required to edit the website.

## Wiki reference data

The wiki has dedicated block/state/trait/permutation/item topics and 75 component
articles. Constructor signatures, defaults, constants and fixture serialization
come from `api-reference.json`, exported from the actual PHP classes:

```text
php website/export-api.php /path/to/axolotl-checkout
```

Commit the export when wrapper classes change. Site checks compare normalized
source hashes and refuse stale metadata. Building on GitHub does not need the host
server dependencies because the validated export is checked in. `reference.py`
adds purpose, behavior boundaries, combinations, pitfalls and source references;
`articles/` contains the independent conceptual guides. The visual hierarchy is
inspired by Bedrock Wiki, with original styling, topic folders, article outlines
and a persisted light/dark preference. Showcase keeps its independent scope.

## Guides and browser tools

Architecture, persistence, maintaining your own fork, tooling and additional
project tutorials live in `articles/`. The library and example scopes remain
independent. `authoring_pages.py` supplies the item/block generator, interactive
collision/selection visualizer, Mojang JSON converter and state/rotation workbench.
`authoring-lib.js` contains pure conversion, validation, code and ZIP generation;
`authoring.js` binds the forms and draws the orbitable previews. Inputs stay local.
`authoring.test.cjs` checks valid/invalid definitions, PHP quoting and ZIP output.

For host integration checks of generated PHP and the charged-item guide:

```text
node website/authoring.test.cjs --fixtures .compat/authoring.json
php website/check-php.php /path/to/axolotl .compat/authoring.json
php examples/CustomiesShowcase/tests/shapes.php /path/to/axolotl
node website/migration.test.cjs --fixtures .compat/migration.json
php website/check-migration.php /path/to/axolotl .compat/migration.json
```

The reader controls use local theme/text-size preferences, system-font fallbacks,
reduced-motion handling, progress/back-to-top controls and responsive forms.
Modern-browser desktop/tablet/mobile viewport checks supplement static output and
Node checks; they do not constitute testing every device or browser version.

`shape-fixtures.json` is generated by `shapes.php` from actual meshes, Showcase PHP
and vanilla stair collision. Commit changes to it alongside intentional shape fixes.
CI rejects fixture drift. The eight-cell occupancy check fully describes these
half-cube stairs, not arbitrary meshes. The builder removes the retired toolbox
pages and scripts when rebuilding an existing preview directory.

`articles/protocol-updates.md` records the 1.7.0 released 26.60 host/protocol matrix and
`articles/diagnostics.md` documents the PHP API and standalone CLI doctor; the library has no in-game commands.
The library version in page headers/download labels comes from `plugin.yml`.

## Migration Lab and design system

`migration_pages.py` defines the separate Migration Lab scope. `migration-lib.js`
parses JavaScript with pinned Acorn, translates supported AST patterns, and reads
ZIP central directories before bounded fflate decompression. It never executes
uploaded JavaScript. `migration-worker.js` keeps extraction/translation off the UI
thread; `migration.js` manages cancellation, files, reports and ZIP downloads.
Unsupported callbacks remain entirely manual. Original files and resource assets
are preserved; generated plugins never load the originals.

Install the pinned npm lockfile before building. `build.py` copies the parser,
decompressor, Latin variable fonts and license notices into static assets. There
are no runtime CDN requests or upload endpoints. Other writing systems use system
font fallbacks. Limits: 32 MiB archives, 8 MiB per expanded file, 64 MiB expanded
input, 1,500 entries, two nested archives, 256 definitions, 128 KiB per script/JSON
and 128 MiB generated output. Encrypted/split/ZIP64 archives require external extraction.

`studio.css`, `preferences.js` and `studio.js` provide section tabs, Forest/Ocean/
Ember palettes, light/dark themes, reduced-motion-aware transitions and chapter
tracking. `CHANGELOG.md` is the release source of truth; the build creates a
filterable article. Migration and Showcase each have their own navigation/search.
