/* eslint-disable no-alert */
import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Notice, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Groups flat directive/source pairs into { directive: [ source, ... ] }.
 *
 * @param {Array} directives Pairs returned by the REST endpoint.
 */
function groupByDirective( directives ) {
	return directives.reduce( ( acc, { directive, source } ) => {
		acc[ directive ] = acc[ directive ] ?? [];
		acc[ directive ].push( source );
		return acc;
	}, {} );
}

export default function ImportJcore2Modal( { headerType, onClose, onImport } ) {
	const [ directives, setDirectives ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isImporting, setIsImporting ] = useState( false );

	useEffect( () => {
		apiFetch( {
			path: `/jcore-turva/v1/jcore2/policies?header_type=${ headerType }`,
		} )
			.then( ( response ) => setDirectives( response.directives ?? [] ) )
			.catch( () =>
				setError(
					__(
						'Could not read the policy from the JCORE 2 theme.',
						'jcore-turva'
					)
				)
			);
	}, [ headerType ] );

	const handleImport = async ( action ) => {
		setIsImporting( true );
		try {
			await apiFetch( {
				path: '/jcore-turva/v1/sources/import',
				method: 'POST',
				data: {
					header_type: headerType,
					directives,
					action, // 'merge' or 'replace'
				},
			} );
			onImport();
		} catch ( e ) {
			window.alert(
				__( 'Import failed:', 'jcore-turva' ) +
					( e.message || __( 'Unknown error', 'jcore-turva' ) )
			);
		} finally {
			setIsImporting( false );
		}
	};

	const grouped = directives ? groupByDirective( directives ) : {};
	const isEmpty = directives !== null && directives.length === 0;

	return (
		<Modal
			title={ __( 'Import from JCORE 2', 'jcore-turva' ) }
			onRequestClose={ onClose }
		>
			<p className="description">
				{ __(
					'The policy currently configured in the JCORE 2 theme. Importing copies it here so the theme module can be switched off without loosening the policy.',
					'jcore-turva'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ ! error && directives === null && <Spinner /> }

			{ isEmpty && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'The JCORE 2 theme has no policy configured for this header.',
						'jcore-turva'
					) }
				</Notice>
			) }

			{ ! isEmpty && directives !== null && (
				<div className="jcore-turva__import-preview">
					{ Object.entries( grouped ).map(
						( [ directive, sources ] ) => (
							<div
								key={ directive }
								className="jcore-turva__import-preview-row"
							>
								<code className="jcore-turva__import-preview-directive">
									{ directive }
								</code>
								<code className="jcore-turva__import-preview-sources">
									{ sources.every( ( s ) => s === '' )
										? __(
												'(empty — feature denied)',
												'jcore-turva'
										  )
										: sources.join( ' ' ) }
								</code>
							</div>
						)
					) }
					<p className="description">
						{ sprintf(
							/* translators: %d: number of sources found. */
							_n(
								'%d source found.',
								'%d sources found.',
								directives.length,
								'jcore-turva'
							),
							directives.length
						) }
					</p>
				</div>
			) }

			<div className="jcore-turva__modal-actions">
				<Button
					variant="secondary"
					onClick={ onClose }
					disabled={ isImporting }
				>
					{ __( 'Cancel', 'jcore-turva' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => handleImport( 'merge' ) }
					isBusy={ isImporting }
					disabled={ isEmpty || directives === null || isImporting }
				>
					{ __( 'Merge', 'jcore-turva' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => handleImport( 'replace' ) }
					isBusy={ isImporting }
					disabled={ isEmpty || directives === null || isImporting }
				>
					{ __( 'Replace', 'jcore-turva' ) }
				</Button>
			</div>
		</Modal>
	);
}
