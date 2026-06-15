/* eslint-disable no-alert */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { Modal, Button, Spinner, CheckboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const parse = ( s ) => {
	const parts = s.match(
		/^(([^:/?#]+):)?(\/\/([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?/
	);
	const scheme = parts[ 2 ] || '';
	let host = parts[ 4 ] || '';
	let path = parts[ 5 ] || '';

	if ( ! host && ! scheme && s.includes( '.' ) ) {
		host = s.split( '/' )[ 0 ];
		path = s.substring( host.length );
	}

	return { scheme, host, path };
};

const isCovered = ( subSource, superSource ) => {
	if ( subSource === superSource ) {
		return true;
	}
	if ( superSource === '*' ) {
		return true;
	}

	const sub = parse( subSource );
	const sup = parse( superSource );

	// Scheme check
	if ( sup.scheme && sub.scheme && sup.scheme !== sub.scheme ) {
		return false;
	}
	if ( sup.scheme && ! sub.scheme ) {
		return false;
	}

	// Host check
	if ( sup.host ) {
		if ( ! sub.host ) {
			return false;
		}
		if ( sup.host.startsWith( '*.' ) ) {
			const supDomain = sup.host.slice( 2 );
			if ( ! sub.host.endsWith( '.' + supDomain ) ) {
				return false;
			}
		} else if ( sup.host !== sub.host ) {
			return false;
		}
	}

	// Path check
	if ( sup.path ) {
		if ( ! sub.path.startsWith( sup.path ) ) {
			return false;
		}
	}

	return true;
};

export default function CleanCspModal( { onClose, onClean } ) {
	const [ sources, setSources ] = useState( null );
	const [ isCleaning, setIsCleaning ] = useState( false );
	const [ selectedIds, setSelectedIds ] = useState( [] );

	useEffect( () => {
		apiFetch( {
			path: addQueryArgs( '/jcore-turva/v1/sources', {
				header_type: 'csp',
			} ),
		} )
			.then( setSources )
			.catch( () => onClose() );
	}, [ onClose ] );

	const redundancies = useMemo( () => {
		if ( ! sources ) {
			return [];
		}

		const result = [];
		const grouped = sources.reduce( ( acc, s ) => {
			if ( ! acc[ s.directive ] ) {
				acc[ s.directive ] = [];
			}
			acc[ s.directive ].push( s );
			return acc;
		}, {} );

		Object.keys( grouped ).forEach( ( directive ) => {
			const dirSources = grouped[ directive ];
			dirSources.forEach( ( s1 ) => {
				dirSources.forEach( ( s2 ) => {
					if ( s1.id === s2.id ) {
						return;
					}
					if ( isCovered( s2.source, s1.source ) ) {
						// s2 is covered by s1
						result.push( {
							redundant: s2,
							coveredBy: s1,
						} );
					}
				} );
			} );
		} );

		// Filter out cases where both are mutually redundant (identical)
		// and ensure we only keep one version of the redundancy.
		const filtered = [];
		const seenRedundant = new Set();
		result.forEach( ( r ) => {
			if ( seenRedundant.has( r.redundant.id ) ) {
				return;
			}
			// If we already have r.coveredBy as redundant and r.redundant as covering it, skip.
			const reverse = filtered.find(
				( f ) =>
					f.redundant.id === r.coveredBy.id &&
					f.coveredBy.id === r.redundant.id
			);
			if ( ! reverse ) {
				filtered.push( r );
				seenRedundant.add( r.redundant.id );
			}
		} );

		return filtered;
	}, [ sources ] );

	useEffect( () => {
		if ( redundancies.length > 0 && selectedIds.length === 0 ) {
			setSelectedIds( redundancies.map( ( r ) => r.redundant.id ) );
		}
	}, [ redundancies, selectedIds.length ] );

	const handleClean = async () => {
		if ( selectedIds.length === 0 ) {
			return;
		}
		setIsCleaning( true );
		try {
			await apiFetch( {
				path: '/jcore-turva/v1/sources',
				method: 'DELETE',
				data: { ids: selectedIds },
			} );
			onClean();
		} catch ( e ) {
			window.alert( __( 'Cleanup failed.', 'jcore-turva' ) );
		} finally {
			setIsCleaning( false );
		}
	};

	const toggleSelection = ( id ) => {
		setSelectedIds( ( prev ) =>
			prev.includes( id )
				? prev.filter( ( x ) => x !== id )
				: [ ...prev, id ]
		);
	};

	let content;
	if ( sources === null ) {
		content = <Spinner />;
	} else if ( redundancies.length === 0 ) {
		content = <p>{ __( 'No redundant records found.', 'jcore-turva' ) }</p>;
	} else {
		content = (
			<>
				<p>
					{ __(
						'The following records are redundant because they are covered by broader records:',
						'jcore-turva'
					) }
				</p>
				<div className="jcore-turva__redundancy-list">
					{ redundancies.map( ( r ) => (
						<div
							key={ r.redundant.id }
							className="jcore-turva__redundancy-item"
						>
							<CheckboxControl
								label={
									<span>
										<code>{ r.redundant.source }</code>{ ' ' }
										<span className="description">
											{ sprintf(
												/* translators: %s: directive name */
												__( 'in %s', 'jcore-turva' ),
												r.redundant.directive
											) }
										</span>
										<br />
										<small>
											{ sprintf(
												/* translators: %s: covering source */
												__(
													'Covered by: %s',
													'jcore-turva'
												),
												r.coveredBy.source
											) }
										</small>
									</span>
								}
								checked={ selectedIds.includes(
									r.redundant.id
								) }
								onChange={ () =>
									toggleSelection( r.redundant.id )
								}
							/>
						</div>
					) ) }
				</div>
			</>
		);
	}

	return (
		<Modal
			title={ __( 'Clean CSP', 'jcore-turva' ) }
			onRequestClose={ onClose }
			className="jcore-turva__clean-modal"
		>
			{ content }

			<div className="jcore-turva__modal-actions">
				<Button
					variant="secondary"
					onClick={ onClose }
					disabled={ isCleaning }
				>
					{ __( 'Cancel', 'jcore-turva' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleClean }
					isBusy={ isCleaning }
					disabled={
						isCleaning ||
						sources === null ||
						selectedIds.length === 0
					}
				>
					{ __( 'Clean', 'jcore-turva' ) }
				</Button>
			</div>
		</Modal>
	);
}
