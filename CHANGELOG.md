# Changelog

### 1.9.3 (2026-06-16)

#### Maintenance

- languages: remove translation source file and update gitignore (2e7b84a)
- i18n: update translation template file (943c559)

### v1.9.1 (2026-06-16)

#### Build System

- composer: add plugin metadata to composer.json (ad8bb93)

## v1.9.0 (2026-06-16)

#### Features

- reports: add path suggestion buttons and update archive logic (cfe9c48)

#### Styles

- apply consistent code formatting (50ba915)

#### Maintenance

- i18n: update translation files and POT template (a423b5c)

### v1.8.1 (2026-06-15)

#### Maintenance

- config: update foonver parser to all (cfc1936)
- i18n: update translation build scripts and pot file (484714a)

## v1.8.0 (2026-06-15)

#### Features

- i18n: update translation files and POT template (85f0cf1)

#### Refactor

- security: remove unused sprintf import from CleanCspModal (ff9952f)

#### Build System

- i18n: update translation scripts and configuration (116e3ea)

#### Continuous Integration

- github: add PHP setup and simplify check workflow (40b656d)

#### Maintenance

- refactor codebase to follow standard formatting and update pot file (aa3bcad)
- update project configuration and code style (ddb8a9e)

## v1.7.0 (2026-06-15)

#### Features

- csp: add import and cleanup functionality for CSP sources (9a1dab5)
- reports: add functionality to mark all reports as processed (d84c90c)

#### Refactor

- i18n: format source code and update translation strings (9b1fcb1)

#### Styles

- security: improve component layout and spacing consistency (29be261)
- i18n: replace punctuation marks with standard characters in pot file (97fa3b3)
- security: apply project-wide coding style consistency (70e6fae)
- security: fix code style in AddDirectivePanel component (57de2f1)

#### Continuous Integration

- reset failed version (cc3307f)
- github: upgrade node version to 22 in release workflow (500d968)
- github: remove explicit pnpm version from release workflow (e1f83e1)
- release: add linting and translation audit to CI pipeline (162978b)

#### Maintenance

- version: revert version to 1.6.0 (5d1e605)

## v1.6.0 (2026-06-15)

#### Features

- reports: add bulk archive and delete functionality (ba484be)

### v1.5.1 (2026-06-15)

#### Bug Fixes

- database: strip query strings from blocked_uri during data normalization (604a42f)

#### Documentation

- add README file (af90f7d)

#### Maintenance

- config: add foonver configuration file (575586c)

## v1.5.0 (2026-06-08)

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

