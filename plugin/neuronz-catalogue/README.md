# NeuroNZ Catalogue — WordPress plugin

A searchable register of 79 New Zealand data sources on neurological conditions.

The front end here is **the same CSS and JavaScript as the standalone build**
(`neuronz-catalogue.html`). Only the delivery differs: this plugin injects the data
server-side and enqueues the assets; the standalone file embeds both. Anything you
change in `assets/` shows up in both, so work on the interface is not tied to one
route or the other.

---

## Install on Local

1. Open Local, select the site, click **Site folder**.
2. Copy the `neuronz-catalogue` folder into `app/public/wp-content/plugins/`.
3. Log in as the **super administrator** → Network Admin → Plugins → **Network Activate**
   (or Plugins → Activate on a single site).
4. Create a page, add a **Shortcode** block, and enter:

   ```
   [neuronz_catalogue]
   ```

5. Publish and view the page.

Before activating, it is worth checking the file parses. Open **Site shell** and run:

```
php -l wp-content/plugins/neuronz-catalogue/neuronz-catalogue.php
```

`No syntax errors detected` means it is safe to activate.

---

## What it does

**Front end.** Seven filters — category, organisation, neurological content flags,
condition, data type, IDI availability, period — plus free-text search, sorting,
per-record permalinks (`#NZNEURO-006`), and a link-status ledger at the top that
doubles as a filter.

**Every record carries its provenance:** the date its details were last checked, an
age bar showing how stale that check is, the current status of each source link, and
the evidence-log entries behind it. Ten of the 79 records have no verification date;
that gap is shown rather than hidden.

**Admin screen** at Settings → NeuroNZ Catalogue: Zotero library type, library ID,
read-only API key, a **Refresh now** button, and a history of refreshes.

---

## How the Zotero refresh behaves

The key is stored in the options table and used only in server-side requests. **It is
never sent to the browser** — the rendered page contains catalogue data, not credentials.
This is the reason to run the sync in PHP rather than in front-end JavaScript.

Records are matched on `NeuroNZ Record ID` parsed from Zotero's `extra` field, so a
refresh updates existing entries instead of duplicating them.

Items are **skipped, not published**, when they have no stable record ID, no title, or
a duplicate ID. The count of skipped items is reported after every run.

`extra` is parsed by splitting each line at the *first* colon only, so values that
contain colons — URLs, times — survive. Indented continuation lines are folded into the
preceding key rather than dropped.

Text matching internal review patterns (`verification pending`, `not verified`,
`returned 404`, and similar) is separated into `*_internal` fields and is not rendered.
Twenty-three of the 79 records contain such text in the source workbook.

**Verification history is preserved across a refresh.** Zotero does not carry link
status, last-checked dates, or the evidence log, so the sync keeps the values already
held locally rather than overwriting them with nothing. Losing them would remove
precisely the provenance this catalogue exists to show.

---

## Where this can and cannot run

This plugin requires plugin-installation rights. On a WordPress multisite those belong
to the **network super administrator**, not to a site administrator.

Testing on 20 August 2026 against a local multisite configured to match the COMPASS
permission model found that a subsite administrator:

| | |
|---|---|
| can install plugins | no |
| has `unfiltered_html` | no — `current_user_can()` returns `false` |
| can save inline `<script>` | no — stripped on output |
| can save a hand-written `<iframe>` | no — stripped on output |
| can use an oEmbed embed | yes, for registered providers only |
| can upload `.json` | no |
| can upload `.csv` | yes |

So on the COMPASS site this plugin can only be used if University ITS installs it.
If that is not available, serve the standalone build instead and link to it, or embed
it if a provider registration can be arranged. The interface is identical either way.

### Demonstrating the constraint

The two routes make the limit visible in about a minute, which is more convincing
than describing it:

1. As **super administrator**, add the shortcode to a page — the catalogue renders.
2. As a **subsite administrator**, paste the standalone build's markup into a Custom
   HTML block — the script is stripped and nothing renders.

Same site, same content, different capability.

---

## Files

```
neuronz-catalogue/
├── neuronz-catalogue.php     plugin, shortcode, admin screen, Zotero sync
├── assets/
│   ├── app.css               all rules scoped to .nnz so the theme cannot bleed in
│   ├── app.js                filtering, sorting, permalinks, detail panels
│   └── markup.html           static shell the script populates
├── data/
│   └── catalogue.json        79 records built from the source workbook
└── README.md
```

A synced copy in the options table takes precedence over `data/catalogue.json`, so a
refresh survives a plugin update. Deleting the `nnz_catalogue_json` option restores
the bundled data.

---

## Data provenance

Built from `NZ_Neuro_Data_Catalogue.xlsx` (published 29 May 2026, updated 23 June 2026,
compiled by Lifu Wu), MD5 `23e6e25ac1f38cbbf4605c54a828f0eb`.

Link status comes from an automated check on 19 August 2026 with redirects adjudicated
by hand: 61 resolve, 12 moved but preserved, 13 blocked to automated checks, 1 redirects
to a site root with its content gone, 3 dead.

Verification dates are the latest Evidence Log access date matched to each record.
Sixty-nine of 79 records have one.

Blank NCC / SFL / NWF flags are rendered as absent. Whether a blank cell means "does not
apply" or "not yet assessed" is not documented in the source workbook, and is one of
eight schema questions still open with the compiler.
