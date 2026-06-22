/* eslint-disable no-alert */
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import {
	Button,
	Modal,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { check } from '@wordpress/icons';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { CSP_DIRECTIVES, PERMISSIONS_DIRECTIVES } from '../constants';
import { normalizeBlockedUri } from '../utils';

const extractDirective = ( violatedDirective ) =>
	violatedDirective?.split( ' ' )[ 0 ] ?? '';

const formatDate = ( dateString ) => {
	if ( ! dateString ) {
		return '-';
	}
	return new Intl.DateTimeFormat( undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	} ).format( new Date( dateString ) );
};

function ReviewModal( { report, onClose, onReviewed } ) {
	const [ headerType, setHeaderType ] = useState( 'csp' );
	const [ directive, setDirective ] = useState( () =>
		extractDirective( report.violated_directive )
	);
	const [ source, setSource ] = useState( () =>
		normalizeBlockedUri( report.blocked_uri )
	);
	const [ isSubmitting, setIsSubmitting ] = useState( false );
	const [ error, setError ] = useState( null );

	const directives =
		headerType === 'csp' ? CSP_DIRECTIVES : PERMISSIONS_DIRECTIVES;

	const directiveOptions = [
		{ value: '', label: __( '- Select directive -', 'jcore-turva' ) },
		...directives.map( ( d ) => ( { value: d, label: d } ) ),
	];

	// Reset directive when header type changes if it's no longer in the list.
	useEffect( () => {
		if ( directive && ! directives.includes( directive ) ) {
			setDirective( '' );
		}
	}, [ headerType ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Adjust directive based on existing parent directives for CSP.
	useEffect( () => {
		if ( headerType !== 'csp' ) {
			return;
		}
		const initialDirective = extractDirective( report.violated_directive );
		const match = initialDirective.match( /^([a-z]+-src)-(elem|attr)$/ );
		if ( ! match ) {
			return;
		}

		apiFetch( {
			path: addQueryArgs( '/jcore-turva/v1/sources', {
				header_type: 'csp',
			} ),
		} )
			.then( ( existingSources ) => {
				const parentDirective = match[ 1 ];
				const hasParent = existingSources.some(
					( s ) => s.directive === parentDirective
				);
				const hasSpecific = existingSources.some(
					( s ) => s.directive === initialDirective
				);
				if ( hasParent && ! hasSpecific ) {
					setDirective( parentDirective );
				}
			} )
			.catch( () => {} );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleSubmit = async () => {
		if ( ! directive || ! source.trim() ) {
			return;
		}
		setIsSubmitting( true );
		setError( null );
		try {
			const trimmedSource = source.trim();

			// Fetch existing sources to determine whether this directive already exists.
			const existingSources = await apiFetch( {
				path: addQueryArgs( '/jcore-turva/v1/sources', {
					header_type: headerType,
				} ),
			} );

			let targetDirective = directive;
			if ( headerType === 'csp' ) {
				const match = directive.match( /^([a-z]+-src)-(elem|attr)$/ );
				if ( match ) {
					const parentDirective = match[ 1 ];
					const hasParent = existingSources.some(
						( s ) => s.directive === parentDirective
					);
					const hasSpecific = existingSources.some(
						( s ) => s.directive === directive
					);
					if ( hasParent && ! hasSpecific ) {
						targetDirective = parentDirective;
					}
				}
			}

			const sourceExists = existingSources.some(
				( s ) =>
					s.directive === targetDirective &&
					s.source === trimmedSource
			);

			if ( sourceExists ) {
				await apiFetch( {
					path: `/jcore-turva/v1/reports/${ report.id }`,
					method: 'PUT',
					data: { processed: true },
				} );
				onReviewed( report.id );
				return;
			}

			const directiveExists = existingSources.some(
				( s ) => s.directive === targetDirective
			);

			// Seed a new CSP directive with the current default-src sources so it
			// starts no more restrictive than the fallback policy.
			if ( ! directiveExists && headerType === 'csp' ) {
				const defaultSrcSources = existingSources
					.filter(
						( s ) => s.directive === 'default-src' && s.enabled
					)
					.map( ( s ) => s.source );
				const seedValues =
					defaultSrcSources.length > 0
						? defaultSrcSources
						: [ "'self'" ];
				// Exclude the source we're about to add explicitly to avoid duplicates.
				const seedsToAdd = seedValues.filter(
					( v ) => v !== trimmedSource
				);
				await Promise.all(
					seedsToAdd.map( ( value ) =>
						apiFetch( {
							path: '/jcore-turva/v1/sources',
							method: 'POST',
							data: {
								header_type: headerType,
								directive: targetDirective,
								source: value,
								enabled: true,
							},
						} )
					)
				);
			}

			await apiFetch( {
				path: '/jcore-turva/v1/sources',
				method: 'POST',
				data: {
					header_type: headerType,
					directive: targetDirective,
					source: trimmedSource,
					enabled: true,
				},
			} );
			await apiFetch( {
				path: `/jcore-turva/v1/reports/${ report.id }`,
				method: 'PUT',
				data: { processed: true },
			} );
			onReviewed( report.id );
		} catch {
			setError(
				__( 'Failed to add source. Please try again.', 'jcore-turva' )
			);
			setIsSubmitting( false );
		}
	};

	return (
		<Modal
			title={ __( 'Add as allowed source', 'jcore-turva' ) }
			onRequestClose={ onClose }
			className="jcore-turva__review-modal"
		>
			<div className="jcore-turva__review-form">
				<table className="jcore-turva__report-detail">
					<tbody>
						<tr>
							<th>
								{ __( 'Violated directive', 'jcore-turva' ) }
							</th>
							<td>
								<code>{ report.violated_directive }</code>
							</td>
						</tr>
						<tr>
							<th>{ __( 'Blocked URI', 'jcore-turva' ) }</th>
							<td>
								<code>{ report.blocked_uri }</code>
							</td>
						</tr>
						<tr>
							<th>{ __( 'Document URIs', 'jcore-turva' ) }</th>
							<td>
								{ report.uris?.length > 0 ? (
									<ul className="jcore-turva__uri-list">
										{ report.uris.map( ( uri ) => (
											<li key={ uri }>
												<code>{ uri }</code>
											</li>
										) ) }
									</ul>
								) : (
									<code>{ report.document_uri }</code>
								) }
							</td>
						</tr>
						<tr>
							<th>{ __( 'Occurrences', 'jcore-turva' ) }</th>
							<td>{ report.report_count }</td>
						</tr>
					</tbody>
				</table>
				<hr className="jcore-turva__review-divider" />
				<p className="description">
					{ __(
						'Edit the directive and source value below as needed before adding to your policy.',
						'jcore-turva'
					) }
				</p>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Header', 'jcore-turva' ) }
					value={ headerType }
					options={ [
						{ value: 'csp', label: 'Content-Security-Policy' },
						{ value: 'permissions', label: 'Permissions-Policy' },
					] }
					onChange={ setHeaderType }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Directive', 'jcore-turva' ) }
					value={ directive }
					options={ directiveOptions }
					onChange={ setDirective }
				/>
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Source value', 'jcore-turva' ) }
					value={ source }
					onChange={ setSource }
					help={ __(
						"Edit the value to allow, e.g. 'self', 'unsafe-inline', or https://example.com",
						'jcore-turva'
					) }
				/>
				{ ( () => {
					try {
						const originalUri = normalizeBlockedUri(
							report.blocked_uri
						);
						if ( ! originalUri.startsWith( 'http' ) ) {
							return null;
						}
						const url = new URL( originalUri );
						const parts = url.pathname
							.split( '/' )
							.filter( Boolean );

						const buttons = [];
						// Domain button
						buttons.push( {
							label: url.host,
							value: `${ url.origin }/`,
						} );

						let pathAccumulator = '';
						parts.forEach( ( part, index ) => {
							pathAccumulator += `/${ part }`;
							// If it's the last part and the original didn't have a trailing slash, don't add one.
							const isLast = index === parts.length - 1;
							const trailingSlash =
								isLast && ! originalUri.endsWith( '/' )
									? ''
									: '/';
							buttons.push( {
								label: part,
								value: `${ url.origin }${ pathAccumulator }${ trailingSlash }`,
							} );
						} );

						return (
							<div className="jcore-turva__uri-buttons">
								{ buttons.map( ( btn ) => (
									<Button
										key={ btn.value }
										variant="secondary"
										size="small"
										isPressed={ source === btn.value }
										onClick={ () => setSource( btn.value ) }
									>
										{ btn.label }
									</Button>
								) ) }
							</div>
						);
					} catch {
						return null;
					}
				} )() }
				{ error && <p className="jcore-turva__error">{ error }</p> }
				<div className="jcore-turva__modal-actions">
					<Button
						variant="primary"
						onClick={ handleSubmit }
						isBusy={ isSubmitting }
						disabled={
							! directive || ! source.trim() || isSubmitting
						}
					>
						{ __( 'Add source', 'jcore-turva' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'jcore-turva' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
}

export default function ReportsTab() {
	const [ statusFilter, setStatusFilter ] = useState( 'new' );
	const [ reports, setReports ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ reviewReport, setReviewReport ] = useState( null );
	const [ isRefreshing, setIsRefreshing ] = useState( false );

	const reportsRef = useRef( reports );
	useEffect( () => {
		reportsRef.current = reports;
	}, [ reports ] );

	const loadReports = useCallback(
		( silent = false ) => {
			if ( ! silent ) {
				setReports( null );
			}
			setError( null );
			apiFetch( {
				path: addQueryArgs( '/jcore-turva/v1/reports', {
					status: statusFilter,
				} ),
			} )
				.then( ( newData ) => {
					if ( ! silent || ! reportsRef.current ) {
						setReports( newData );
						return;
					}

					const prevIds = reportsRef.current
						.map( ( r ) => r.id )
						.join( ',' );
					const newIds = newData.map( ( r ) => r.id ).join( ',' );

					if ( prevIds === newIds ) {
						// Data is stable (same reports in same order), update silently.
						setReports( newData );
					} else {
						// Data has shifted, update gracefully.
						setIsRefreshing( true );
						setTimeout( () => {
							setReports( newData );
							setTimeout( () => {
								setIsRefreshing( false );
							}, 500 );
						}, 500 );
					}
				} )
				.catch( () => {
					if ( ! silent ) {
						setError(
							__( 'Failed to load reports.', 'jcore-turva' )
						);
					}
				} );
		},
		[ statusFilter ]
	);

	useEffect( () => {
		loadReports();
	}, [ loadReports ] );

	useEffect( () => {
		const interval = setInterval( () => {
			// Only poll if we're not currently doing something else (like having a modal open).
			if ( ! reviewReport ) {
				loadReports( true );
			}
		}, 30000 );
		return () => clearInterval( interval );
	}, [ loadReports, reviewReport ] );

	const handleToggleProcessed = async ( id, processed ) => {
		setReports( ( prev ) =>
			prev.map( ( r ) => ( r.id === id ? { ...r, processed } : r ) )
		);
		try {
			await apiFetch( {
				path: `/jcore-turva/v1/reports/${ id }`,
				method: 'PUT',
				data: { processed },
			} );
		} catch {
			setReports( ( prev ) =>
				prev.map( ( r ) =>
					r.id === id ? { ...r, processed: ! processed } : r
				)
			);
		}
	};

	const handleMarkAllProcessed = async () => {
		const backup = reports;
		setReports( ( prev ) =>
			prev.map( ( r ) => ( { ...r, processed: true } ) )
		);
		try {
			await apiFetch( {
				path: '/jcore-turva/v1/reports/mark-processed',
				method: 'POST',
				data: { status: statusFilter },
			} );
		} catch {
			setReports( backup );
		}
	};

	const handleArchive = async ( id ) => {
		const backup = reports;
		setReports( ( prev ) => prev.filter( ( r ) => r.id !== id ) );
		try {
			await apiFetch( {
				path: `/jcore-turva/v1/reports/${ id }/archive`,
				method: 'POST',
			} );
		} catch {
			setReports( backup );
		}
	};

	const handleUnarchive = async ( id ) => {
		const backup = reports;
		setReports( ( prev ) => prev.filter( ( r ) => r.id !== id ) );
		try {
			await apiFetch( {
				path: `/jcore-turva/v1/reports/${ id }/unarchive`,
				method: 'POST',
			} );
		} catch {
			setReports( backup );
		}
	};

	const handleDelete = async ( id ) => {
		const backup = reports;
		setReports( ( prev ) => prev.filter( ( r ) => r.id !== id ) );
		try {
			await apiFetch( {
				path: `/jcore-turva/v1/reports/${ id }`,
				method: 'DELETE',
			} );
		} catch {
			setReports( backup );
		}
	};

	const handleReviewed = ( id ) => {
		setReports( ( prev ) =>
			prev.map( ( r ) => ( r.id === id ? { ...r, processed: true } : r ) )
		);
		setReviewReport( null );
	};

	const handleArchiveAll = async () => {
		if (
			! window.confirm(
				__(
					'Are you sure you want to archive all new reports?',
					'jcore-turva'
				)
			)
		) {
			return;
		}
		const backup = reports;
		setReports( [] );
		try {
			await apiFetch( {
				path: '/jcore-turva/v1/reports/archive',
				method: 'POST',
			} );
		} catch {
			setReports( backup );
		}
	};

	const handleArchiveProcessed = async () => {
		const backup = reports;
		setReports( ( prev ) => prev.filter( ( r ) => ! r.processed ) );
		try {
			await apiFetch( {
				path: '/jcore-turva/v1/reports/archive',
				method: 'POST',
				data: { processed_only: true },
			} );
		} catch {
			setReports( backup );
		}
	};

	const handleDeleteAll = async () => {
		if (
			! window.confirm(
				__(
					'Are you sure you want to delete all reports in this view? This action cannot be undone.',
					'jcore-turva'
				)
			)
		) {
			return;
		}
		const backup = reports;
		setReports( [] );
		try {
			await apiFetch( {
				path: '/jcore-turva/v1/reports/delete',
				method: 'POST',
				data: { status: statusFilter },
			} );
		} catch {
			setReports( backup );
		}
	};

	return (
		<div className="jcore-turva__tab-content">
			<div className="jcore-turva__filter-bar">
				<div className="jcore-turva__status-filters">
					<Button
						variant={
							statusFilter === 'new' ? 'primary' : 'secondary'
						}
						onClick={ () => setStatusFilter( 'new' ) }
					>
						{ __( 'New', 'jcore-turva' ) }
					</Button>
					<Button
						variant={
							statusFilter === 'archived'
								? 'primary'
								: 'secondary'
						}
						onClick={ () => setStatusFilter( 'archived' ) }
					>
						{ __( 'Archived', 'jcore-turva' ) }
					</Button>
				</div>
				<div className="jcore-turva__bulk-actions">
					{ statusFilter === 'new' && reports?.length > 0 && (
						<>
							{ reports.some( ( r ) => r.processed ) && (
								<Button
									variant="secondary"
									onClick={ handleArchiveProcessed }
								>
									{ __( 'Archive Processed', 'jcore-turva' ) }
								</Button>
							) }
							<Button
								variant="secondary"
								onClick={ handleArchiveAll }
							>
								{ __( 'Archive All', 'jcore-turva' ) }
							</Button>
						</>
					) }
					{ reports?.length > 0 && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ handleDeleteAll }
						>
							{ __( 'Delete All', 'jcore-turva' ) }
						</Button>
					) }
				</div>
			</div>

			{ error && <p className="jcore-turva__error">{ error }</p> }

			{ reports === null && ! error && <Spinner /> }

			{ reports !== null && reports.length === 0 && (
				<p className="jcore-turva__empty">
					{ statusFilter === 'new'
						? __( 'No new violation reports.', 'jcore-turva' )
						: __( 'No archived reports.', 'jcore-turva' ) }
				</p>
			) }

			{ reports !== null && reports.length > 0 && (
				<table
					className={
						isRefreshing
							? 'jcore-turva__reports-table widefat striped is-refreshing'
							: 'jcore-turva__reports-table widefat striped'
					}
				>
					<thead>
						<tr>
							<th className="jcore-turva__col-processed">
								<Button
									icon={ check }
									label={ __(
										'Mark all as processed',
										'jcore-turva'
									) }
									size="small"
									variant="tertiary"
									onClick={ handleMarkAllProcessed }
								/>
							</th>
							<th>{ __( 'Violation Report', 'jcore-turva' ) }</th>
							<th className="jcore-turva__col-actions">
								{ __( 'Actions', 'jcore-turva' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ reports.map( ( report ) => (
							<tr
								key={ report.id }
								className={
									report.processed
										? 'jcore-turva__report-row is-processed'
										: 'jcore-turva__report-row'
								}
							>
								<td className="jcore-turva__col-processed">
									<Button
										icon={ check }
										isPressed={ report.processed }
										label={
											report.processed
												? __(
														'Unmark as processed',
														'jcore-turva'
												  )
												: __(
														'Mark as processed',
														'jcore-turva'
												  )
										}
										size="small"
										variant="tertiary"
										onClick={ () =>
											handleToggleProcessed(
												report.id,
												! report.processed
											)
										}
									/>
								</td>
								<td>
									<div className="jcore-turva__report-main">
										<div className="jcore-turva__report-header">
											<code className="jcore-turva__directive">
												{ report.violated_directive }
											</code>
											<code
												className="jcore-turva__blocked-uri"
												title={ report.blocked_uri }
											>
												{ report.blocked_uri }
											</code>
										</div>
										<div className="jcore-turva__report-details">
											<span>
												{ __(
													'In document:',
													'jcore-turva'
												) }{ ' ' }
												<code
													title={
														report.document_uri
													}
												>
													{ report.document_uri }
												</code>
											</span>
											{ report.uris?.length > 1 && (
												<span className="jcore-turva__uri-count">
													{ ' ' }
													(
													{ sprintf(
														/* translators: %d: number of more URIs */
														__(
															'+%d more',
															'jcore-turva'
														),
														report.uris.length - 1
													) }
													)
												</span>
											) }
										</div>
										<div className="jcore-turva__report-meta">
											<span>
												{ sprintf(
													/* translators: %d: number of occurrences */
													_n(
														'%d occurrence',
														'%d occurrences',
														report.report_count,
														'jcore-turva'
													),
													report.report_count
												) }
											</span>
											<span className="jcore-turva__meta-sep">
												|
											</span>
											<span>
												{ __(
													'Last seen:',
													'jcore-turva'
												) }{ ' ' }
												{ formatDate(
													report.last_seen
												) }
											</span>
										</div>
									</div>
								</td>
								<td className="jcore-turva__report-actions">
									{ statusFilter === 'new' ? (
										<>
											<Button
												variant="secondary"
												size="small"
												onClick={ () =>
													setReviewReport( report )
												}
											>
												{ __(
													'Review',
													'jcore-turva'
												) }
											</Button>
											<Button
												variant="tertiary"
												size="small"
												isDestructive
												onClick={ () =>
													handleArchive( report.id )
												}
											>
												{ __(
													'Archive',
													'jcore-turva'
												) }
											</Button>
										</>
									) : (
										<>
											<Button
												variant="secondary"
												size="small"
												onClick={ () =>
													handleUnarchive( report.id )
												}
											>
												{ __(
													'Restore',
													'jcore-turva'
												) }
											</Button>
											<Button
												variant="tertiary"
												size="small"
												isDestructive
												onClick={ () =>
													handleDelete( report.id )
												}
											>
												{ __(
													'Delete',
													'jcore-turva'
												) }
											</Button>
										</>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ reviewReport && (
				<ReviewModal
					report={ reviewReport }
					onClose={ () => setReviewReport( null ) }
					onReviewed={ handleReviewed }
				/>
			) }
		</div>
	);
}
