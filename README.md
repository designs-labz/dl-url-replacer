# DesignsLabz Relocate

Search and replace URLs and text across a WordPress database without breaking serialized data. Dry runs, chunked processing for large databases, and a full operation history.

> **Status:** in development (0.1.0). Not ready for production sites yet.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.4+
- Single-site installs only. On Multisite the plugin shows a notice and stays inactive.

## Development

```bash
composer install
composer lint      # PHPCS (WordPress-Extra)
composer analyse   # PHPStan level 6
```

Classes live in `src/` under the `DesignsLabz\Relocate` namespace and are loaded by the PSR-4 autoloader in `designslabz-relocate.php`, so the plugin runs straight from a Git checkout without `composer install`. Composer is only needed for dev tooling.

Admin screens are plain PHP templates in `templates/admin/`. There is no JavaScript build step.

## License

GPL-3.0-or-later.
