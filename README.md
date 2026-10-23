# NeuroNZ data catalogue

A searchable register of New Zealand data sources on neurological conditions,
with a list of related research publications. Built for Te Huinga Hāpai Tāngata
COMPASS Research Centre and the Neurological Alliance of New Zealand.

- **Live site:** https://neuronz6.wpcomstaging.com — home page and
  [data catalogue](https://neuronz6.wpcomstaging.com/data-catalogue/)
- **Source of records:** the NeuroNZ Zotero group library (ID `6708514`)
- **This repository:** the WordPress plugin that renders the catalogue, plus tools

## How it works

The catalogue is the `neuronz-catalogue` WordPress plugin, installed on the
WordPress.com site (Personal plan or above is needed to install plugins). The
catalogue page holds one shortcode:

```
[neuronz_catalogue width="full" panel="on" home="https://neuronz6.wpcomstaging.com"]
```

Zotero is the source of truth. The plugin refreshes itself from Zotero once a
week; **Settings → NeuroNZ Catalogue** has a button to refresh straight away, a
link re-check, and exports of the catalogue as a standalone page or JSON.

Link status, verification dates and the evidence log are not stored in Zotero,
so a refresh carries them forward rather than overwriting them.

## GitHub Pages

The catalogue was first hosted here on GitHub Pages. It has moved to the
WordPress site, and `docs/index.html` is now a redirect: old links, including
record anchors such as `#NZNEURO-006`, land on the same record there.

## Updating the plugin

1. Edit `plugin/neuronz-catalogue/` and bump the version in its header.
2. Zip the `neuronz-catalogue` folder and upload it under **Plugins → Upload**
   on the WordPress site, choosing to replace the installed version.

## Layout

```
plugin/neuronz-catalogue/   the WordPress plugin: sync, link check, page assets
build/build.php             runs the plugin's code from the command line
build/wp-shim.php           the small part of WordPress that code needs
data/catalogue.json         catalogue snapshot used by the command-line build
docs/index.html             GitHub Pages redirect to the WordPress catalogue
```

## Command-line build (optional)

Builds the same standalone page the plugin exports, without WordPress — useful
for testing interface changes locally or producing an offline copy.

```
php build/build.php                          # write dist/index.html from data/
php build/build.php --sync                   # refresh from Zotero first
php build/build.php --sync --check-links     # and re-check links
```

Needs PHP 7.4+ with the curl and mbstring extensions. Optional environment
variables: `ZOTERO_LIBRARY_TYPE` (default `groups`), `ZOTERO_LIBRARY_ID`
(default `6708514`), `ZOTERO_API_KEY` (only for a private library — never commit
it to a file).

## Credits

Online platform: Yuxiang Zeng · Zotero reference library: Chandan Kumar ·
Neurological data catalogue: Lifu Wu · Supervisor: Lisa Underwood
