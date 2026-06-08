# Changelog

## 1.5.0 (2026-06-08)

#### Features

- csp: add option to expand Google domains to regional TLDs (e1ac0a4)
- report: include document URIs in report details (92e695b)

#### Bug Fixes

- csp: restrict multi-domain expansion to specific directives (ffa978c)
- database: update index to include last_seen column for query performance (8f88879)
- database: prevent concurrent upgrades during migration (da3f6a5)

### v1.4.2 (2026-06-02)

#### Build System

- deps: define package manager (bd43bb8)

### v1.4.1 (2026-06-02)

#### Build System

- npm: remove packageManager configuration (59515b8)

## v1.4.0 (2026-06-02)

#### Features

- csp: add configurable CSP mode and reorganize project structure (2c20cfb)

### v1.3.12 (2026-05-28)

#### Build System

- composer: remove jetpack-autoloader dependency (f3381ed)

### v1.3.11 (2026-05-28)

#### Build System

- composer: update jcore-update to v1.1 and simplify autoloader (e698827)

### v1.3.10 (2026-05-28)

#### Build System

- composer: update jcore-update to v1.1.0 (1942719)

### v1.3.9 (2026-05-28)

#### Maintenance

- build: rename zip exclusion file to .zipexclude (13aa968)

### v1.3.8 (2026-05-28)

#### Continuous Integration

- github: remove build steps from release workflow (cc394b7)

### v1.3.7 (2026-05-28)

#### Build System

- composer: update jcore-update to v1.0.1 (07bb5ee)
- composer: add jetpack-autoloader and update autoloader reference (a9442d3)

### v1.3.6 (2026-05-28)

#### Continuous Integration

- github: add output parameters to release workflow steps (d8d917c)

### v1.3.5 (2026-05-28)

#### Continuous Integration

- github: update release workflow to use correct job outputs (1f991e6)
- github: migrate plugin publishing to reusable workflow (f024948)

### v1.3.4 (2026-05-27)

#### Continuous Integration

- github: extract plugin metadata from file headers for release workflow (753a817)

### v1.3.3 (2026-05-27)

#### Continuous Integration

- github: add verbose output to release request (e3b4753)

### v1.3.2 (2026-05-27)

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

