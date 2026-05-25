import { __ } from '@wordpress/i18n';
import DirectivesManager from './DirectivesManager';
import { CSP_DIRECTIVES } from '../constants';

export default function CspTab() {
	return (
		<div className="jcore-turva__tab-content">
			<p className="description">
				{ __(
					'Each directive controls which sources are allowed for a resource type. Sources are compiled into the Content-Security-Policy header on every page load.',
					'jcore-turva'
				) }
			</p>
			<DirectivesManager
				headerType="csp"
				availableDirectives={ CSP_DIRECTIVES }
				sourcePlaceholder={ __(
					"e.g. 'self', 'unsafe-inline', https://cdn.example.com",
					'jcore-turva'
				) }
			/>
		</div>
	);
}
