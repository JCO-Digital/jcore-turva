# Changelog

### 1.3.2 (2026-05-27)

#### Refactor

- updater: replace custom implementation with jcore-update library (965d28a)

### v1.3.1 (2026-05-27)

#### Refactor

- autoload: rename class parameter for clarity (76dd6c2)

## v1.3.0 (2026-05-26)

#### Features

- plugin: add settings link to the plugins list page (0580c1c)

## v1.2.0 (2026-05-26)

#### Features

- release: bump version to 1.1.0 and initialize changelog (6e150a4)

#### Styles

- update plugin name branding (eaaf2ec)

## v1.1.0 (2026-05-26)

#### Features

- security: seed new CSP directives with default-src sources (33bd89e)
- plugin: implement automatic plugin updates via JCore API (5ab1c9f)
- ci: add automatic release workflow and build configuration (a1918fa)
- plugin: initialize JCore Turva security header management plugin (3c09dc9)

#### Styles

- apply project coding standards to codebase (c5eea9b)

#### Build System

- deps: update pnpm-lock and configure workspace dependencies (00bbe3f)
- deps: add package manager configuration to package.json (b7ab72e)

#### Continuous Integration

- github: remove manual commit step for built blocks (fa7c5ee)
- release: update sync file and add update api notification (c1f5ef5)

