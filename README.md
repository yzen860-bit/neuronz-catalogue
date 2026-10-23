# NeuroNZ data catalogue

A searchable register of New Zealand data sources on neurological conditions,
with a list of related research publications. Built for Te Huinga Hāpai Tāngata
COMPASS Research Centre and the Neurological Alliance of New Zealand.

- **Catalogue:** published from this repository with GitHub Pages
- **Introduction and methods:** https://neuronz6.wpcomstaging.com
- **Source of records:** the NeuroNZ Zotero group library (ID `6708514`)

## How it stays up to date

Zotero is the source of truth. A scheduled GitHub Action
(`.github/workflows/update.yml`) does the rest:

| When | What |
|---|---|
| Every Monday, 18:00 UTC | Refresh from Zotero, rebuild the page, publish |
| 1st of each month | The same, plus a re-check of every source link |
| Any time | Actions → **Update catalogue** → **Run workflow** (tick the box to check links) |

Each run commits the updated `data/` and `docs/` so the next run starts from it.
Link status, verification dates and the evidence log are not stored in Zotero, so
they are carried forward from `data/catalogue.json` rather than overwritten.

To add or correct a record, change it in Zotero, then run the workflow (or wait
for Monday).

## Layout

```
plugin/neuronz-catalogue/   the WordPress plugin: sync, link check, page assets
build/build.php             runs the plugin's code from the command line
build/wp-shim.php           the small part of WordPress that code needs
data/catalogue.json         the catalogue as last built
data/state.json             refresh and link-check history
docs/index.html             the published page (generated; do not edit)
```

The page is the plugin's own standalone export, so the hosted catalogue and the
plugin always look and behave the same. To change the interface, edit
`plugin/neuronz-catalogue/assets/` and push; the page is rebuilt automatically.

## Running locally

```
php build/build.php                          # rebuild from data/
php build/build.php --sync                   # refresh from Zotero first
php build/build.php --sync --check-links     # and re-check links
```

Needs PHP 7.4+ with the curl and mbstring extensions. Optional environment
variables: `ZOTERO_LIBRARY_TYPE` (default `groups`), `ZOTERO_LIBRARY_ID`
(default `6708514`), `ZOTERO_API_KEY` (only for a private library — store it as a
repository secret, never in a file).

## Credits

Online platform: Yuxiang Zeng · Zotero reference library: Chandan Kumar ·
Neurological data catalogue: Lifu Wu · Supervisor: Lisa Underwood
