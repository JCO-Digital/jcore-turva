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
