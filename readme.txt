=== JCORE Turva ===
Contributors: jcodigital
Tags: security, csp, permissions-policy, headers, reports
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.13.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage Content Security Policy and Permissions Policy headers, and read the violations they report, from one screen in wp-admin.

== Description ==

JCORE Turva sends your site's security headers and gives you a place to tune them. Directives are stored as individual sources rather than one long string, so a policy can be built up, switched off a line at a time and compared against what browsers actually report.

= Features =

* **Content Security Policy** – build a policy directive by directive, with every source individually enabled or disabled.
* **Report-only and test modes** – run a new policy as `Content-Security-Policy-Report-Only` site-wide, or only for logged-in administrators, before enforcing it.
* **Violation reporting** – the policy points browsers at an endpoint on your own site. Violations are collected, counted, grouped by the document they happened on, and can be turned into an allowed source with one click.
* **Permissions Policy** – control which browser features a page may use.
* **Other headers** – HSTS with a configurable max-age, `X-Content-Type-Options`, `X-XSS-Protection` and `Referrer-Policy`.
* **Regional Google domains** – expand a Google domain in `connect-src` or `img-src` to every regional TLD, so a visitor on google.co.uk is not blocked.
* **JCORE 2 compatibility** – detects the JCORE 2 theme's own security module, unhooks it so two policies are not intersected by the browser, and offers to import its CSP and Permissions Policy.
* **Automatic updates** – through the J&Co Digital update service.

= External services =

The plugin checks for updates against `https://update.jcore.fi`, operated by J&Co Digital Oy. It sends the plugin slug and the installed version, and the site URL as the request's origin. No visitor or content data is involved.

Violation reports stay on your own site: the `report-uri` in the policy points at this plugin's own REST endpoint.

= Source code =

The settings screen is built with `@wordpress/scripts`. Its readable source is published at https://github.com/JCO-Digital/jcore-turva.

== Installation ==

1. Upload the `jcore-turva` folder to `/wp-content/plugins/`, or install the zip from the Plugins screen.
2. Activate the plugin. The tables it needs are created on activation.
3. Open **Settings → Security**.

== Frequently Asked Questions ==

= Where do I start with a policy? =

On the **Content Security Policy** tab, set the mode to **Report only** and leave it there for a while. Every blocked resource shows up under **Reports** with the directive it needs; add the ones that belong to your site, then switch the mode to **Enabled**.

= Can I test a policy without affecting visitors? =

Yes. With the mode set to **Enabled**, turn on **Test mode**: the policy is then sent as report-only, but only to logged-in administrators. Everyone else gets the enforced policy.

= Why are my reports full of browser extension URLs? =

Extensions inject scripts into the page and the browser reports them against your policy. They come from schemes like `chrome-extension:` and can be archived; nothing on your site caused them.

= I use the JCORE 2 theme. Do I need to do anything? =

The plugin notices the theme's security module and switches it off, because two `Content-Security-Policy` headers are intersected and the theme's would keep blocking what you allow here. On the CSP and Permissions Policy tabs there is an **Import from JCORE 2** option that copies the theme's policy over. The takeover can be turned off on the General tab, or with the `jcore_turva_disable_jcore2` filter.

= What happens to my data when I delete the plugin? =

Deleting the plugin drops its three tables and removes its options. Deactivating it leaves everything in place.

== Changelog ==

= 1.13.0 (2026-09-24) =

* Feature: login - add option to hide account details in login errors

= v1.12.2 (2026-09-22) =

* Refactor: plugin - restructure codebase and add configuration files
* Documentation: readme - update tested up to version to 7.1
* Maintenance: plugin - update tested up to version to 7.1

= v1.12.1 (2026-09-17) =

* Build: composer - update jcore-update dependency

= v1.12.0 (2026-09-17) =

* Feature: compat - add compatibility layer for JCORE 2 security module
* Fix: security - disable Content Security Policy by default
* Style: security - format code with prettier and single quotes

= v1.11.0 (2026-06-22) =

* Feature: ci - automate README header updates during release
* Fix: security - handle CSP sub-directive logic in review modal
* Documentation: readme - format project metadata using markdown
* Maintenance: scripts - apply consistent formatting to update-readme.js

= v1.10.0 (2026-06-16) =

* Feature: ui - add mobile responsive layout for security dashboard
* Feature: reports - redesign report list view for better readability
* Fix: api - ignore phpcs warnings for direct database query
* Refactor: plugin - clean up codebase and improve DB query safety
* Refactor: database - replace raw query interpolation with wpdb prepare placeholders
* Style: security - clean up duplicate styles and adjust flex properties
* Style: security - apply project-wide coding style consistency
* Build: deps - update project dependencies
* Build: deps - add plugin-check to development environment
* CI: github - add slack notification to release workflow

= v1.9.4 (2026-06-16) =

* Fix: security - update CSS selector for toggle control label

= v1.9.3 (2026-06-16) =

* Maintenance: languages - remove translation source file and update gitignore
* Maintenance: i18n - update translation template file

= v1.9.1 (2026-06-16) =

* Build: composer - add plugin metadata to composer.json

= v1.9.0 (2026-06-16) =

* Feature: reports - add path suggestion buttons and update archive logic
* Style: apply consistent code formatting
* Maintenance: i18n - update translation files and POT template

= v1.8.1 (2026-06-15) =

* Maintenance: config - update foonver parser to all
* Maintenance: i18n - update translation build scripts and pot file

= v1.8.0 (2026-06-15) =

* Feature: i18n - update translation files and POT template
* Refactor: security - remove unused sprintf import from CleanCspModal
* Build: i18n - update translation scripts and configuration
* CI: github - add PHP setup and simplify check workflow
* Maintenance: refactor codebase to follow standard formatting and update pot file
* Maintenance: update project configuration and code style

= v1.7.0 (2026-06-15) =

* Feature: csp - add import and cleanup functionality for CSP sources
* Feature: reports - add functionality to mark all reports as processed
* Refactor: i18n - format source code and update translation strings
* Style: security - improve component layout and spacing consistency
* Style: i18n - replace punctuation marks with standard characters in pot file
* Style: security - apply project-wide coding style consistency
* Style: security - fix code style in AddDirectivePanel component
* CI: reset failed version
* CI: github - upgrade node version to 22 in release workflow
* CI: github - remove explicit pnpm version from release workflow
* CI: release - add linting and translation audit to CI pipeline
* Maintenance: version - revert version to 1.6.0

= v1.6.0 (2026-06-15) =

* Feature: reports - add bulk archive and delete functionality

= v1.5.1 (2026-06-15) =

* Fix: database - strip query strings from blocked_uri during data normalization
* Documentation: add README file
* Maintenance: config - add foonver configuration file

= v1.5.0 (2026-06-08) =

* Feature: csp - add option to expand Google domains to regional TLDs
* Feature: report - include document URIs in report details
* Fix: csp - restrict multi-domain expansion to specific directives
* Fix: database - update index to include last_seen column for query performance
* Fix: database - prevent concurrent upgrades during migration

= v1.4.2 (2026-06-02) =

* Build: deps - define package manager

= v1.4.1 (2026-06-02) =

* Build: npm - remove packageManager configuration

= v1.4.0 (2026-06-02) =

* Feature: csp - add configurable CSP mode and reorganize project structure

= v1.3.12 (2026-05-28) =

* Build: composer - remove jetpack-autoloader dependency

= v1.3.11 (2026-05-28) =

* Build: composer - update jcore-update to v1.1 and simplify autoloader

= v1.3.10 (2026-05-28) =

* Build: composer - update jcore-update to v1.1.0

= v1.3.9 (2026-05-28) =

* Maintenance: build - rename zip exclusion file to .zipexclude

= v1.3.8 (2026-05-28) =

* CI: github - remove build steps from release workflow

= v1.3.7 (2026-05-28) =

* Build: composer - update jcore-update to v1.0.1
* Build: composer - add jetpack-autoloader and update autoloader reference

= v1.3.6 (2026-05-28) =

* CI: github - add output parameters to release workflow steps

= v1.3.5 (2026-05-28) =

* CI: github - update release workflow to use correct job outputs
* CI: github - migrate plugin publishing to reusable workflow

= v1.3.4 (2026-05-27) =

* CI: github - extract plugin metadata from file headers for release workflow

= v1.3.3 (2026-05-27) =

* CI: github - add verbose output to release request

= v1.3.2 (2026-05-27) =

* Refactor: updater - replace custom implementation with jcore-update library

= v1.3.1 (2026-05-27) =

* Refactor: autoload - rename class parameter for clarity

= v1.3.0 (2026-05-26) =

* Feature: plugin - add settings link to the plugins list page

= v1.2.0 (2026-05-26) =

* Feature: release - bump version to 1.1.0 and initialize changelog
* Style: update plugin name branding

= v1.1.0 (2026-05-26) =

* Feature: security - seed new CSP directives with default-src sources
* Feature: plugin - implement automatic plugin updates via JCore API
* Feature: ci - add automatic release workflow and build configuration
* Feature: plugin - initialize JCore Turva security header management plugin
* Style: apply project coding standards to codebase
* Build: deps - update pnpm-lock and configure workspace dependencies
* Build: deps - add package manager configuration to package.json
* CI: github - remove manual commit step for built blocks
* CI: release - update sync file and add update api notification
