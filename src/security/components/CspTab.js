import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import DirectivesManager from './DirectivesManager';
import ImportCspModal from './ImportCspModal';
import CleanCspModal from './CleanCspModal';
import ImportJcore2Modal from './ImportJcore2Modal';
import { CSP_DIRECTIVES, JCORE2_DETECTED } from '../constants';
import { sourceValueClassName } from '../utils';

function SourceLegend() {
	const items = [
		[ 'https://cdn.example.com', __( 'whole host', 'jcore-turva' ) ],
		[ '*.example.com', __( 'subdomains only', 'jcore-turva' ) ],
		[
			'https://example.com/js/',
			__( 'directory and below', 'jcore-turva' ),
		],
		[
			'https://example.com/js/app.js',
			__( 'exact file only', 'jcore-turva' ),
		],
	];

	return (
		<ul className="jcore-turva__source-legend">
			{ items.map( ( [ example, label ] ) => (
				<li key={ example }>
					<code className={ sourceValueClassName( example ) }>
						{ example }
					</code>
					{ label }
				</li>
			) ) }
		</ul>
	);
}

export default function CspTab() {
	const [ isImportModalOpen, setIsImportModalOpen ] = useState( false );
	const [ isCleanModalOpen, setIsCleanModalOpen ] = useState( false );
	const [ isJcore2ModalOpen, setIsJcore2ModalOpen ] = useState( false );
	const [ refreshTicket, setRefreshTicket ] = useState( 0 );

	const handleImportComplete = () => {
		setIsImportModalOpen( false );
		setRefreshTicket( ( prev ) => prev + 1 );
	};

	const handleCleanComplete = () => {
		setIsCleanModalOpen( false );
		setRefreshTicket( ( prev ) => prev + 1 );
	};

	const handleJcore2ImportComplete = () => {
		setIsJcore2ModalOpen( false );
		setRefreshTicket( ( prev ) => prev + 1 );
	};

	return (
		<div className="jcore-turva__tab-content">
			<div className="jcore-turva__tab-header">
				<p className="description">
					{ __(
						'Each directive controls which sources are allowed for a resource type. Sources are compiled into the Content-Security-Policy header on every page load.',
						'jcore-turva'
					) }
				</p>
				<div className="jcore-turva__tab-actions">
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
					{ JCORE2_DETECTED && (
						<Button
							variant="secondary"
							onClick={ () => setIsJcore2ModalOpen( true ) }
						>
							{ __( 'Import from JCORE 2', 'jcore-turva' ) }
						</Button>
					) }
				</div>
			</div>
			<SourceLegend />
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
			{ isJcore2ModalOpen && (
				<ImportJcore2Modal
					headerType="csp"
					onClose={ () => setIsJcore2ModalOpen( false ) }
					onImport={ handleJcore2ImportComplete }
				/>
			) }
		</div>
	);
}
