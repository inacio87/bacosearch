## Quick goal

Help contributors and AI assistants quickly make safe, small changes in the Bacosearch PHP codebase: fixes, localized text edits, search improvements, and small UI/template adjustments.

## Repo big picture (what matters)

- This is a monolithic PHP site rooted at the `bacosearch.com/` folder. The public entry points are files like `index.php`, `search.php`, and `providers.php` which load a central bootstrap (`core/bootstrap.php`).
- Templates are included via `TEMPLATE_PATH` and the flow is: bootstrap -> prepare translations/data -> include `head.php`, `header.php`, page template, `footer.php`.
- Database access uses PDO and helper `getDBConnection()`; search uses MySQL FULLTEXT + LIKE queries (see `search.php`). Primary tables observed: `providers`, `providers_logistics`, `providers_body`, `providers_service_offerings`, `countries`, `categories`.

## Project-specific patterns & conventions

- Translations: code builds a translations map and calls `getTranslation($key, $language_code, $context)`; follow the existing mapping pattern when adding new text keys (see `index.php` and `search.php`).
- Output escaping: use `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (project uses this heavily). Some pages define a small `$e` helper for convenience (see `providers.php`). Prefer the same pattern.
- Canonical URLs / slugs: `providers.php` builds slugs and issues 301 redirects if the current URL differs — maintain this behavior when touching providers/SEO logic.
- JS translations: server-side translations are exported into JS objects (example: `FRONTEND_TRANSLATIONS` in `index.php`) — update both server-side key and JS export if you change labels used by client scripts.
- Ad/asset rendering: there are small helper functions like `renderAdBanner(...)` in `index.php` — reuse them for consistent markup and accessibility attributes.

## How to run locally (developer commands)

1) Install PHP deps (if you have `composer.json`):

```powershell
composer install
```

2) Quick local server for manual testing (from the folder that contains the site's `index.php`):

```powershell
# run from the folder that contains index.php (e.g. bacosearch.com\)
php -S 127.0.0.1:8000 -t .\
```

3) Tests & quality tools (defined in `composer.json`):

```powershell
composer run test        # phpunit
composer run phpstan      # static analysis
composer run cs           # phpcs
composer run cs-fix       # phpcbf (auto-fix style)
```

## Files & locations worth referencing

- `core/bootstrap.php` — central initialization (load config, constants like `SITE_URL`, `TEMPLATE_PATH`, session start). Changes here affect all pages.
- `index.php`, `search.php`, `providers.php` — canonical page examples demonstrating translation usage, DB queries, template composition and common helpers.
- `composer.json` — shows supported PHP version (>=7.4), dev tools (phpunit, phpstan, phpcs) and composer scripts.
- `backups/` — contains DB dump(s); do not commit secrets to repo.

## Common maintenance tasks and hints for AI edits

- Adding a new translation key: add the key in the same context used by pages (e.g. `'search'`, `'header'`, `'provider_page'`) and call `getTranslation` where used. Update any JS exports if the key is surfaced to the frontend.
- Database changes: edit code that reads rows with defensive null checks and JSON decoding guarded by `json_last_error()` as the codebase does (see `providers.php` for gallery/videos handling).
- SQL in search: keep FULLTEXT syntax and fallback LIKE queries for ranking — tests or staging verification recommended when changing relevance logic.
- Security: the app relies on htmlspecialchars for escaping and uses prepared statements for DB. Preserve prepared statements (PDO) and avoid string concatenation in SQL.

## Quick examples (copyable patterns found in repo)

- Safe output helper (providers.php):

```php
$e = static function (?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
echo $e($provider['display_name']);
```

- PDO prepared query with FULLTEXT + LIKE fallback (search.php): keep the same binding pattern for `:search_terms`, `:like_term`, and use `bindValue` for ints like `:offset`.

## What NOT to change without review

- Global bootstrap constants (SITE_URL, TEMPLATE_PATH, LANGUAGE_CONFIG) — changing them affects every page and routing.
- Database schema assumptions used across pages (column names referenced widely such as `display_name`, `gallery_photos`, `is_active`). If you alter column names, update all usages.
- Removing committed `vendor/` without adding a composer install step in onboarding docs — the repo currently includes `vendor/` in some copies. Prefer to document moving to composer-managed dependencies.

## Next steps I can do for you

- Create `.env.example` listing expected env variables and a `.gitignore` to exclude `.env`, `vendor/` (if you prefer), `uploads/` and `error_log`.
- Add a minimal `docker-compose.yml` to run PHP + MySQL for local development.

If anything here is unclear, tell me which area you want expanded (DB model, bootstrap, templates or run/test commands) and I will iterate.
