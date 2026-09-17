import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import DirectivesManager from './DirectivesManager';
import ImportJcore2Modal from './ImportJcore2Modal';
import { PERMISSIONS_DIRECTIVES, JCORE2_DETECTED } from '../constants';

export default function PermissionsTab() {
	const [ isJcore2ModalOpen, setIsJcore2ModalOpen ] = useState( false );
	const [ refreshTicket, setRefreshTicket ] = useState( 0 );

	const handleJcore2ImportComplete = () => {
		setIsJcore2ModalOpen( false );
		setRefreshTicket( ( prev ) => prev + 1 );
	};

	return (
		<div className="jcore-turva__tab-content">
			<div className="jcore-turva__tab-header">
				<p className="description">
					{ __(
						'Control which browser features are allowed on this site. Each directive takes one or more origins: * (all), self, or "https://example.com".',
						'jcore-turva'
					) }
				</p>
				{ JCORE2_DETECTED && (
					<div className="jcore-turva__tab-actions">
						<Button
							variant="secondary"
							onClick={ () => setIsJcore2ModalOpen( true ) }
						>
							{ __( 'Import from JCORE 2', 'jcore-turva' ) }
						</Button>
					</div>
				) }
			</div>
			<DirectivesManager
				key={ refreshTicket }
				headerType="permissions"
				availableDirectives={ PERMISSIONS_DIRECTIVES }
				sourcePlaceholder={ __(
					'e.g. self, *, "https://example.com"',
					'jcore-turva'
				) }
			/>
			{ isJcore2ModalOpen && (
				<ImportJcore2Modal
					headerType="permissions"
					onClose={ () => setIsJcore2ModalOpen( false ) }
					onImport={ handleJcore2ImportComplete }
				/>
			) }
		</div>
	);
}
