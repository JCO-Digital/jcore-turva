export const CSP_DIRECTIVES = [
	'script-src',
	'script-src-elem',
	'script-src-attr',
	'style-src',
	'style-src-elem',
	'style-src-attr',
	'img-src',
	'font-src',
	'connect-src',
	'media-src',
	'object-src',
	'frame-src',
	'worker-src',
	'manifest-src',
	'child-src',
	'prefetch-src',
	'base-uri',
	'form-action',
	'frame-ancestors',
	'upgrade-insecure-requests',
];

export const PERMISSIONS_DIRECTIVES = [
	'camera',
	'microphone',
	'geolocation',
	'display-capture',
	'fullscreen',
	'accelerometer',
	'gyroscope',
	'magnetometer',
	'payment',
	'usb',
	'web-share',
	'sync-xhr',
];

// Directives that are boolean flags with no source list.
export const FLAG_DIRECTIVES = [ 'upgrade-insecure-requests' ];
