import { __ } from '@wordpress/i18n';
import DirectivesManager from './DirectivesManager';
import { PERMISSIONS_DIRECTIVES } from '../constants';

export default function PermissionsTab() {
	return (
		<div className="jcore-turva__tab-content">
			<p className="description">
				{ __(
					'Control which browser features are allowed on this site. Each directive takes one or more origins: * (all), self, or "https://example.com".',
					'jcore-turva'
				) }
			</p>
			<DirectivesManager
				headerType="permissions"
				availableDirectives={ PERMISSIONS_DIRECTIVES }
				sourcePlaceholder={ __(
					'e.g. self, *, "https://example.com"',
					'jcore-turva'
				) }
			/>
		</div>
	);
}
