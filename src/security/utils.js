import { __, sprintf } from '@wordpress/i18n';

/**
 * Maps browser CSP report `blocked-uri` shorthand values to valid CSP source expressions.
 * https://www.w3.org/TR/CSP3/#violation-reports
 */
const BLOCKED_URI_TO_SOURCE = {
	inline: "'unsafe-inline'",
	eval: "'unsafe-eval'",
	'wasm-eval': "'wasm-unsafe-eval'",
	data: 'data:',
	blob: 'blob:',
	filesystem: 'filesystem:',
	self: "'self'",
	none: "'none'",
	'strict-dynamic': "'strict-dynamic'",
	'unsafe-hashes': "'unsafe-hashes'",
	'report-sample': "'report-sample'",
};

/**
 * Translates a browser-reported `blocked-uri` value to the corresponding CSP
 * source expression. Full URLs are returned unchanged.
 *
 * @param {string} blockedUri The raw blocked-uri from the CSP report.
 * @return {string} The normalized CSP source value.
 */
export function normalizeBlockedUri( blockedUri ) {
	if ( ! blockedUri ) {
		return blockedUri;
	}
	return BLOCKED_URI_TO_SOURCE[ blockedUri.toLowerCase() ] ?? blockedUri;
}

/**
 * Describes how broadly a CSP source expression matches, so the UI can tell
 * a whole host apart from a directory, an exact file or a subdomain wildcard.
 *
 * A path ending in `/` matches everything below it; any other path matches
 * only that exact URL. `*.example.com` matches subdomains, not the apex.
 * https://www.w3.org/TR/CSP3/#match-paths
 *
 * @param {string} source CSP source expression.
 * @return {{ classNames: string[], description: string }} BEM modifiers for
 *         `jcore-turva__source-value` and a human-readable explanation.
 */
export function describeSource( source ) {
	const value = ( source ?? '' ).trim();

	if ( /^'.*'$/.test( value ) ) {
		return {
			classNames: [ 'keyword' ],
			description: __( 'Keyword', 'jcore-turva' ),
		};
	}

	if ( value === '*' ) {
		return {
			classNames: [ 'wildcard-host' ],
			description: __(
				'Matches any host (except data:, blob: and similar schemes).',
				'jcore-turva'
			),
		};
	}

	if ( /^[a-z][a-z0-9+.-]*:$/i.test( value ) ) {
		return {
			classNames: [ 'scheme' ],
			description: sprintf(
				/* translators: %s: URL scheme, e.g. https: */
				__( 'Matches anything using the %s scheme.', 'jcore-turva' ),
				value
			),
		};
	}

	const match = value.match( /^(?:[a-z][a-z0-9+.-]*:\/\/)?([^/]+)(\/.*)?$/i );
	if ( ! match ) {
		return { classNames: [], description: '' };
	}

	const host = match[ 1 ].replace( /:(\d+|\*)$/, '' );
	const path = match[ 2 ] ?? '';
	const classNames = [];
	const parts = [];

	if ( host.startsWith( '*.' ) ) {
		classNames.push( 'wildcard-host' );
		parts.push(
			sprintf(
				/* translators: 1: domain, e.g. example.com */
				__(
					'Matches subdomains of %1$s, but not %1$s itself.',
					'jcore-turva'
				),
				host.slice( 2 )
			)
		);
	}

	if ( ! path || path === '/' ) {
		if ( ! classNames.length ) {
			parts.push( __( 'Matches any path on this host.', 'jcore-turva' ) );
		}
	} else if ( path.endsWith( '/' ) ) {
		classNames.push( 'path-prefix' );
		parts.push(
			sprintf(
				/* translators: %s: URL path ending in a slash */
				__( 'Matches %s and everything below it.', 'jcore-turva' ),
				path
			)
		);
	} else {
		classNames.push( 'path-exact' );
		parts.push(
			sprintf(
				/* translators: %s: URL path */
				__(
					'Matches only the exact path %s. End it with / to match a directory.',
					'jcore-turva'
				),
				path
			)
		);
	}

	return { classNames, description: parts.join( ' ' ) };
}

/**
 * Class string for a `<code>` showing a CSP source, with its match modifiers.
 *
 * @param {string} source CSP source expression.
 * @return {string} Space-separated class names.
 */
export function sourceValueClassName( source ) {
	return [
		'jcore-turva__source-value',
		...describeSource( source ).classNames.map(
			( name ) => `jcore-turva__source-value--${ name }`
		),
	].join( ' ' );
}
