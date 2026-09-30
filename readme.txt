=== DesignsLabz Relocate – Search Replace & Migration ===
Contributors: designs_labz
Tags: search replace, migration, database, urls, serialized
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Safely search and replace URLs and text across your WordPress database, with dry runs, serialized data support and a full history.

== Description ==

DesignsLabz Relocate changes text across your database: moving a site from staging to production, switching to HTTPS, changing a domain, or renaming something everywhere it appears.

It is built for doing that on real sites without breaking them.

= Preview first, always =

Every replacement starts as a dry run. It searches the tables you choose, changes nothing, and shows exactly what would change: rows and replacements per table and column, values it would leave alone and why, and before/after examples. Only a completed dry run can be applied, and applying it repeats exactly what was previewed.

= Serialized data and JSON handled properly =

Plugins and themes store settings as serialized PHP. A plain text replacement breaks those values whenever the length changes. Relocate rewrites serialized data token by token and recalculates every length, without unserializing it, so no plugin code runs and objects, private properties, references and numbers stay exactly as they were. URLs inside JSON (as page builders store them, with escaped slashes) are found and replaced too.

A value is left untouched, and reported, when changing it could corrupt it: serialized data that is already broken, a custom serialized format only its own class understands, or JSON that would become invalid.

= Made for large databases =

Tables are processed in small batches by primary key, a few seconds per request, so there are no timeouts or memory limits to hit however big the tables are. Progress is saved after every batch. If the page is closed or the connection drops, the job can be continued from the last completed batch.

= Safety measures =

* Each batch of a replacement is one database transaction: it is saved completely or not at all, so an interruption never leaves a batch half applied or applies it twice. This needs InnoDB tables, which WordPress uses by default; the confirmation step warns about any that are not.
* The original value of everything a replacement changes is saved to a downloadable file of SQL statements. Importing it puts those values back.
* The site address (siteurl and home) is changed last, so you are not logged out part-way through.
* Tables without a primary key or suitable unique key are skipped, because their rows cannot be updated one at a time safely.
* Post GUIDs are left alone unless you ask for them to be changed.
* Only administrators who can post unfiltered HTML can use it.

= Also included =

* Case-insensitive and whole-word matching, and matching the http:// and protocol-relative versions of a URL.
* A history of every job, a log, and automatic clean-up of old history.
* WP-CLI commands: `wp dlz search-replace` and `wp dlz resume`.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/designslabz-relocate`, or install it from the Plugins screen.
2. Activate it.
3. Go to Tools → DesignsLabz Relocate.

== Frequently Asked Questions ==

= Do I still need a backup? =

Yes. Take a full database backup before replacing anything. The file of original values covers what a replacement changed, but it is not a substitute for a backup.

= Can I undo a replacement? =

There is no undo button. Each replacement can save the original value of everything it changed to a downloadable `.sql.gz` file, and importing that file (for example with phpMyAdmin, or `gunzip -c file.sql.gz | mysql your_database`) puts those values back. It overwrites any edits made to those values since, so use it soon after the replacement.

= Does it change serialized data safely? =

Yes. Serialized values are rewritten with their lengths recalculated, and checked to still be readable afterwards. Values that could not be changed safely are left as they were and listed in the results.

= What happens if I close the page during a replacement? =

The batch in progress either completes or is rolled back (on InnoDB tables, which WordPress uses by default). Open the job from the Dashboard or History and continue it: it picks up from the last completed batch.

= Does it support Multisite? =

Not yet. On a Multisite network the plugin shows a notice and does not run.

= Which tables does it search? =

The tables you tick. By default those are the tables with your WordPress prefix. Other tables in the same database can be added. The plugin's own tables are never searched.

= How do I use it from WP-CLI? =

`wp dlz search-replace https://staging.example.com https://example.com --dry-run` shows what would change. Without `--dry-run` the command asks for confirmation and applies the dry run it just made. See `wp help dlz search-replace` for all options.

= Does it send any data anywhere? =

No. Everything happens in your own database. Nothing is sent to DesignsLabz or anyone else.

== Changelog ==

= 0.1.0 =
* First development release.
