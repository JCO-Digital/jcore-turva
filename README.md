# JCORE Turva

Security header management for WordPress: Content Security Policy, Permissions Policy, and the violation reports they produce, from one screen in wp-admin.

This file covers the repository and the development workflow. What the plugin does, and how to use it, is in [readme.txt](readme.txt).

## Requirements

- PHP 8.2+
- WordPress 6.7+
- Node 22+ and [pnpm](https://pnpm.io/)
- [Composer](https://getcomposer.org/) and [WP-CLI](https://wp-cli.org/) (WP-CLI is only needed for the translation targets)

## Getting started

```sh
pnpm install
composer install
pnpm build
```

Then run a throwaway WordPress with the plugin mounted:

```sh
pnpm playground
```

That serves [WordPress Playground](https://wordpress.org/playground/) on <http://localhost:8882> from `.wp/blueprint.json`, logged in as `admin` / `password` and landing on the plugin's settings screen. Plugin Check is installed alongside it.

## Scripts

| Command | What it does |
| --- | --- |
| `pnpm build` | Build the admin app into `build/`. |
| `pnpm start` | Same, in watch mode. |
| `pnpm check` | Everything CI lints: ESLint, Stylelint and PHPCS. |
| `pnpm lint:js` / `lint:css` / `lint:php` | One linter at a time. |
| `pnpm format` | Format `src/` with `wp-scripts format`. |
| `composer lint:fix` | Fix what PHPCBF can fix. |
| `pnpm i18n` | Regenerate the POT, the MO files and the JS translation JSON. |
| `pnpm playground` | Serve the plugin in WordPress Playground. |

A `Makefile` wraps the same scripts (`make ci` is the entry point the shared publish workflow calls); it is a shim, not a second build system.

## Layout

```
jcore-turva.php              Plugin header, constants, autoloader, bootstrap
uninstall.php                Drops the tables and options on delete
includes/
  class-plugin.php           Wires every component to its hooks
  class-database.php         Table names, schema and migrations
  class-headers.php          Sends the headers on send_headers
  class-csp.php              Builds the Content-Security-Policy value
  class-permissions.php      Builds the Permissions-Policy value
  class-google-domains.php   Regional Google TLDs
  class-compat.php           Detects and unhooks the JCORE 2 security module
  admin/class-menu.php       Settings > Security page and its assets
  rest/class-controller.php  Shared namespace and permission check
  rest/class-*-controller.php  sources, settings, reports
views/admin/page.php         Mount point for the React app
src/security/                The admin app (@wordpress/scripts)
languages/                   .po sources; .pot, .mo and .json are generated
```

Classes autoload from the `Jcore\Turva` namespace: `Jcore\Turva\Rest\Sources_Controller` lives in `includes/rest/class-sources-controller.php`.

## REST API

Everything the admin app does goes through `jcore-turva/v1`. All routes require `manage_options`, except `POST /csp-report`, which is the public endpoint the CSP `report-uri` points browsers at.

| Route | Methods |
| --- | --- |
| `/sources` | GET, POST, DELETE |
| `/sources/import` | POST |
| `/sources/{id}` | PUT/PATCH, DELETE |
| `/jcore2/policies` | GET |
| `/settings` | GET, POST |
| `/reports` | GET |
| `/reports/archive`, `/reports/mark-processed`, `/reports/delete` | POST |
| `/reports/{id}` | PUT/PATCH, DELETE |
| `/reports/{id}/archive`, `/reports/{id}/unarchive` | POST |

## Database

Three tables, created on activation and kept up to date by `Database::maybe_upgrade()` on every load:

- `{prefix}jcore_security_sources` – one row per directive source, for both header types.
- `{prefix}jcore_security_reports` – violations, deduplicated on (directive, blocked URI) and counted.
- `{prefix}jcore_security_report_uris` – the documents each violation was seen on.

## Filters

- `jcore_turva_disable_jcore2` – return `false` to leave the JCORE 2 theme's security module hooked up.

## Releasing

Pushing to `main` runs `.github/workflows/release.yml`:

1. **check** – lint, build, then run Plugin Check against the tree minus `.distignore`. Also runs on pull requests.
2. **release** – [foonver](https://github.com/foonly/foonver) reads the conventional commits since the last tag, bumps the version, syncs it into `jcore-turva.php`, `readme.txt` and `package.json`, writes the `== Changelog ==` section of `readme.txt` and pushes the tag. Its configuration lives in `.foonver.toml`.
3. **publish** – the reusable workflow in [jcore-update](https://github.com/JCO-Digital/jcore-update) builds the zip, attaches it to a GitHub release, registers the version with `update.jcore.fi`, pushes to the dist repository and posts to Slack.

Nothing here is published to wordpress.org. Commit messages must follow [Conventional Commits](https://www.conventionalcommits.org/), or foonver will not know what to bump.

`.distignore` is the single list of what does not ship — it drives both the packaged zip and the dist repository.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
