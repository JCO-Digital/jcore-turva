import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import DirectivesManager from './DirectivesManager';
import ImportCspModal from './ImportCspModal';
import CleanCspModal from './CleanCspModal';
import { CSP_DIRECTIVES } from '../constants';

export default function CspTab() {
	const [ isImportModalOpen, setIsImportModalOpen ] = useState( false );
	const [ isCleanModalOpen, setIsCleanModalOpen ] = useState( false );
	const [ refreshTicket, setRefreshTicket ] = useState( 0 );

	const handleImportComplete = () => {
		setIsImportModalOpen( false );
		setRefreshTicket( ( prev ) => prev + 1 );
	};

	const handleCleanComplete = () => {
		setIsCleanModalOpen( false );
		setRefreshTicket( ( prev ) => prev + 1 );
	};

	return (
		<div className="jcore-turva__tab-content">
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
				} }
			>
				<p className="description">
					{ __(
						'Each directive controls which sources are allowed for a resource type. Sources are compiled into the Content-Security-Policy header on every page load.',
						'jcore-turva'
					) }
				</p>
				<div style={ { display: 'flex', gap: '8px' } }>
					<Button
						variant="secondary"
						onClick={ () => setIsCleanModalOpen( true ) }
					>
						{ __( 'Clean CSP', 'jcore-turva' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => setIsImportModalOpen( true ) }
					>
						{ __( 'Import CSP', 'jcore-turva' ) }
					</Button>
				</div>
			</div>
			<DirectivesManager
				key={ refreshTicket }
				headerType="csp"
				availableDirectives={ CSP_DIRECTIVES }
				sourcePlaceholder={ __(
					"e.g. 'self', 'unsafe-inline', https://cdn.example.com",
					'jcore-turva'
				) }
			/>
			{ isImportModalOpen && (
				<ImportCspModal
					onClose={ () => setIsImportModalOpen( false ) }
					onImport={ handleImportComplete }
				/>
			) }
			{ isCleanModalOpen && (
				<CleanCspModal
					onClose={ () => setIsCleanModalOpen( false ) }
					onClean={ handleCleanComplete }
				/>
			) }
		</div>
	);
}
