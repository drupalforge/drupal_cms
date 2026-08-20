# SFDUG content scripts

Maintenance and repair tooling for the SFDUG site. Every script is idempotent —
re-running one converges on the same result rather than duplicating work.

Run them with:

```
ddev drush php:script <name> \
  --script-path=/var/www/html/web/modules/custom/sfdug_migrate/scripts
```

The repair scripts default to a **dry run** that writes a review CSV to `logs/`.
Read that first, then pass `-- --write` to apply.

| Script | Purpose |
| --- | --- |
| `setup_session_fields.php` | Adds the seven `session` fields and four view modes. |
| `setup_view_displays.php` | Sets what each view mode renders. |
| `setup_theme_blocks.php` | Places blocks, builds the menus and their links, points the front page at `/home`. |
| `import_2026_content.php` | Creates the ECA session, the 2026 season, the speakers and the Home node. |
| `restore_files.php` | Copies `assets/` back into `public://sfdug/` after a database transfer. |
| `apply_meetup_dates.php` | Sets session dates from Meetup's own listings. Preferred date source. |
| `backfill_session_dates.php` | Parses dates out of node titles. Superseded by the above; kept for sessions Meetup does not cover. |
| `rematch_session_videos.php` | Repairs session ↔ recording pairings. |
| `_keywords.inc` | Shared title-matching helpers. |

## Deploying to another environment

What lives where matters, because no single transfer carries everything:

- **Git** carries the theme, these scripts, and `config/sync/`.
- **The database** carries content *and* active configuration.
- **Neither** carries `web/sites/default/files/`, so managed images must be
  restored separately.

### If you are importing the database (backup_migrate, SQL dump)

The database includes the `config` table, so configuration comes with it — do
**not** also run `drush cim`, or you will fight yourself.

```
git checkout sfdug-2026-redesign
composer install
# import the database
drush php:script restore_files --script-path=$(pwd)/web/modules/custom/sfdug_migrate/scripts
drush cr
```

A full database import replaces everything on the target, including users and
any content added there. Take a backup of the target first.

### If you are deploying config only, onto existing content

```
git checkout sfdug-2026-redesign
composer install
drush cim
drush php:script import_2026_content   --script-path=…   # content: nodes, media, files
drush php:script setup_theme_blocks    --script-path=…   # menu links are content, not config
drush php:script apply_meetup_dates    --script-path=… -- --write
drush php:script rematch_session_videos --script-path=… -- --write
drush cr
```

`drush cim` sets the front page to `/home` before the Home node exists, so the
front page 404s until `import_2026_content.php` has run. Do them back to back.

## Known bad source data

`migrations/sfdug_nodes_parsed_enriched.csv`, the drupal.org scrape behind the
74 legacy sessions, is offset by one row in at least two columns:

- `meetup_urls` — verified: 40 mismatches against 1 agreement when each URL's
  real start date is compared to the date in its node's title.
- `body_html_clean` — every legacy session's description describes the
  **previous** month's meetup. This is **not yet fixed.**

Also in that file: `event_date` is the drupal.org creation date, not the meeting
date, and `event_start` is populated on only three rows and disagrees with the
titles. Do not date anything from these columns.

Use `migrations/sfdug_meetup_events.csv` instead — 66 events scraped from the
JSON-LD on Meetup.com event pages, carrying the real name and start time. It is
checked in, so `apply_meetup_dates.php` needs no network access.
