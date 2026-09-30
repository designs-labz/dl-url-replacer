# DesignsLabz Relocate

Search and replace URLs and text across a WordPress database without breaking serialized data or JSON. Every replacement is previewed by a dry run first, processed in resumable batches, and recorded in a history with a downloadable file of the original values.

User-facing documentation is in [`readme.txt`](readme.txt).

## Requirements

- WordPress 6.5+
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.4+ (InnoDB for transactional batches)
- Single site only. On Multisite the plugin shows a notice and stays inactive.

## How it fits together

| Part | Where |
| --- | --- |
| Replacing within one value: plain text, serialized PHP, JSON | `src/Replace/` (no WordPress dependency) |
| Table discovery and allowlisting | `src/Database/Schema.php` |
| Jobs: batching, transactions, resume, original values file, clean-up | `src/Jobs/` |
| REST API used by the admin screens | `src/Rest/` (`dlz-relocate/v1`) |
| Admin screens | `src/Admin/`, `templates/admin/`, `assets/` |
| WP-CLI | `src/Cli/Command.php` |

The admin screens and WP-CLI create jobs through `JobStarter` and drive them with `JobRunner::step()`, so both behave identically.

## Hooks

- `dlz_relocate_step_seconds` (filter): how long one step may work before saving and returning. Default 10.
- The `dlz_relocate_manage` capability maps to `manage_options` plus `unfiltered_html`. Change it with a `map_meta_cap` filter.

## WP-CLI

```bash
wp dlz search-replace https://staging.example.com https://example.com --dry-run
wp dlz search-replace https://staging.example.com https://example.com --yes
wp dlz resume 42
```

## Development

Classes are loaded by the PSR-4 autoloader in `designslabz-relocate.php`, so the plugin runs straight from a Git checkout. Composer is only needed for development tools.

```bash
composer install
composer lint              # PHPCS (WordPress-Extra)
composer analyse           # PHPStan level 6
composer test              # Unit tests, no WordPress or database needed
```

Integration tests run against a real WordPress and MySQL. They drop and recreate tables in the database you point them at, so use an empty one:

```bash
WP_TESTS_DB_HOST=127.0.0.1:3306 WP_TESTS_DB_NAME=relocate_tests composer test:integration
```

`WP_TESTS_DB_USER` and `WP_TESTS_DB_PASSWORD` default to `root` and an empty password.

Regenerate the translation template after changing strings:

```bash
wp i18n make-pot . languages/designslabz-relocate.pot --exclude=vendor,tests
```

CI (`.github/workflows/ci.yml`) runs the linters, the unit tests on PHP 8.1–8.4, and the integration tests on WordPress 6.5 and the latest release against MySQL 8.0, MySQL 8.4 and MariaDB 10.11.

## License

GPL-3.0-or-later.
