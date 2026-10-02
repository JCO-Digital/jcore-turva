/* eslint-disable no-alert */
import { useState, useMemo, useEffect } from '@wordpress/element';
import { Modal, Button, Notice, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { CSP_DIRECTIVES } from '../constants';

const parseCsp = ( str ) => {
	// Remove "Content-Security-Policy:" prefix if present
	let cleanStr = str.trim();
	if ( cleanStr.toLowerCase().startsWith( 'content-security-policy:' ) ) {
		cleanStr = cleanStr
			.substring( 'content-security-policy:'.length )
			.trim();
	}

	const directives = cleanStr.split( ';' );
	const result = [];
	directives.forEach( ( directiveStr ) => {
		const parts = directiveStr.trim().split( /\s+/ );
		if ( parts.length > 0 && parts[ 0 ] ) {
			const directive = parts[ 0 ].toLowerCase();
			const sources = parts.slice( 1 );
			if ( sources.length > 0 ) {
				sources.forEach( ( source ) => {
					result.push( { directive, source } );
				} );
			} else {
				// It could be a flag directive or just an empty directive
				// For jcore-turva, we use '1' as placeholder for flag directives
				result.push( { directive, source: '1' } );
			}
		}
	} );
	return result;
};

// The endpoint maps each skipped source to the one covering it; only the
// covering ones are worth showing.
const coveringSources = ( redundant ) => [
	...new Set(
		Object.entries( redundant || {} ).flatMap( ( [ directive, sources ] ) =>
			Object.values( sources ).map(
				( source ) => `${ directive } ${ source }`
			)
		)
	),
];

const previewImport = ( directives, action ) =>
	apiFetch( {
		path: '/jcore-turva/v1/sources/import',
		method: 'POST',
		data: { header_type: 'csp', directives, action, dry_run: true },
	} ).then( ( res ) => coveringSources( res.redundant ) );

export default function ImportCspModal( { onClose, onImport } ) {
	const [ cspString, setCspString ] = useState( '' );
	const [ isImporting, setIsImporting ] = useState( false );

	// Generated policies often include report-uri, report-to, sandbox and the
	// like, which Turva either adds itself or doesn't manage.
	const { valid, skipped } = useMemo( () => {
		const parsed = parseCsp( cspString );
		return {
			valid: parsed.filter( ( item ) =>
				CSP_DIRECTIVES.includes( item.directive )
			),
			skipped: [
				...new Set(
					parsed
						.map( ( item ) => item.directive )
						.filter( ( d ) => ! CSP_DIRECTIVES.includes( d ) )
				),
			],
		};
	}, [ cspString ] );

	// Asks the server which Google sources multi-domain support already
	// covers. Replace only compares the pasted sources with each other; merge
	// also drops ones the existing sources cover.
	const [ redundant, setRedundant ] = useState( { replace: [], merge: [] } );
	useEffect( () => {
		if ( ! valid.length ) {
			setRedundant( { replace: [], merge: [] } );
			return;
		}

		let cancelled = false;
		const timer = setTimeout( () => {
			Promise.all( [
				previewImport( valid, 'replace' ),
				previewImport( valid, 'merge' ),
			] )
				.then( ( [ replace, merge ] ) => {
					if ( ! cancelled ) {
						setRedundant( {
							replace,
							merge: merge.filter(
								( s ) => ! replace.includes( s )
							),
						} );
					}
				} )
				.catch( () => {} );
		}, 400 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ valid ] );

	const handleImport = async ( action ) => {
		setIsImporting( true );

		try {
			await apiFetch( {
				path: '/jcore-turva/v1/sources/import',
				method: 'POST',
				data: {
					header_type: 'csp',
					directives: valid,
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

	return (
		<Modal
			title={ __( 'Import CSP', 'jcore-turva' ) }
			onRequestClose={ onClose }
		>
			<TextareaControl
				label={ __( 'Paste CSP string here', 'jcore-turva' ) }
				value={ cspString }
				onChange={ setCspString }
				rows={ 10 }
			/>
			{ skipped.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: comma-separated list of CSP directives */
						__(
							'These directives are not managed here and will be skipped: %s',
							'jcore-turva'
						),
						skipped.join( ', ' )
					) }
				</Notice>
			) }
			{ ( redundant.replace.length > 0 ||
				redundant.merge.length > 0 ) && (
				<Notice status="info" isDismissible={ false }>
					{ redundant.replace.length > 0 && (
						<p>
							{ sprintf(
								/* translators: %s: comma-separated list of CSP sources */
								__(
									'Other regional Google domains will be skipped, as Google Multi-Domain Support adds them for: %s',
									'jcore-turva'
								),
								redundant.replace.join( ', ' )
							) }
						</p>
					) }
					{ redundant.merge.length > 0 && (
						<p>
							{ sprintf(
								/* translators: %s: comma-separated list of CSP sources */
								__(
									'When merging, regional Google domains already covered by these existing sources are skipped too: %s',
									'jcore-turva'
								),
								redundant.merge.join( ', ' )
							) }
						</p>
					) }
				</Notice>
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
					disabled={ ! valid.length || isImporting }
				>
					{ __( 'Merge CSP', 'jcore-turva' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ () => handleImport( 'replace' ) }
					isBusy={ isImporting }
					disabled={ ! valid.length || isImporting }
				>
					{ __( 'Replace CSP', 'jcore-turva' ) }
				</Button>
			</div>
		</Modal>
	);
}
