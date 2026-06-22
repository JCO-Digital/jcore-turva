# JCORE Turva

**Contributors:** jco-fi  
**Tags:** security, csp, permissions-policy, headers, reports  
**Requires at least:** 6.7  
**Tested up to:** 7.0  
**Requires PHP:** 8.2  
**Stable tag:** 1.10.0  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Manage security headers like CSP and Permissions Policy with violation reporting through a modern React interface.

## Description

JCORE Turva is a WordPress plugin designed to help you manage security headers for your website. It provides a user-friendly interface for configuring Content Security Policy (CSP) and Permissions Policy, along with a built-in violation reporting system to help you monitor and refine your security settings.

## Features

- **Content Security Policy (CSP) Management**: Easily define directives for your CSP to protect your site from XSS and other injection attacks.
- **Permissions Policy**: Control which browser features and APIs can be used on your site.
- **Violation Reporting**: Capture and view security policy violations directly within your WordPress admin dashboard.
- **Regional Google Domains**: Automatically expand Google domains to their regional TLDs for broader compatibility.
- **Automatic Updates**: Integrated with the J&Co Digital update system.
- **React-based UI**: A modern, responsive settings interface built with React.

## Requirements

- **WordPress**: 6.7 or higher
- **PHP**: 8.2 or higher

## Installation

1. Upload the `jcore-turva` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Settings > JCORE Turva** to configure your security headers.

## Development

To set up the development environment, you will need `pnpm` and `composer` installed.

### Makefile Commands

A `Makefile` is provided for convenience:

- `make install`: Install both JavaScript and PHP dependencies.
- `make build`: Run the build process.
- `make start`: Start the WordPress environment.
- `make stop`: Stop the WordPress environment.
- `make release`: Create a zip file for release in the `release/` directory.
- `make clean`: Remove all build and dependency directories.

### Build Scripts

- `pnpm install`: Install JavaScript dependencies.
- `composer install`: Install PHP dependencies.
- `pnpm update-readme`: Update `README.md` headers from the plugin file.
- `pnpm build`: Build the production assets.
- `pnpm start`: Start the development server with live reloading.
- `pnpm format`: Format the code according to WordPress standards.
- `pnpm lint:js`: Lint the JavaScript files.
- `pnpm lint:css`: Lint the SCSS files.

### WordPress Environment

You can use the provided `@wordpress/env` configuration to quickly spin up a local WordPress environment:

```bash
pnpm env:start
```

To stop the environment:

```bash
pnpm env:stop
```

## License

This project is licensed under the GPL-2.0-or-later License. See the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

## Author

Created by [J&Co Digital](https://jco.fi).
