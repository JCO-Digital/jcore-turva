import { useState, useEffect, useMemo } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import DirectiveCard from './DirectiveCard';
import AddDirectivePanel from './AddDirectivePanel';
import { FLAG_DIRECTIVES } from '../constants';

export default function DirectivesManager( { headerType, availableDirectives, sourcePlaceholder } ) {
	const [ sources, setSources ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		apiFetch( { path: addQueryArgs( '/jcore-turva/v1/sources', { header_type: headerType } ) } )
			.then( setSources )
			.catch( () => setError( __( 'Failed to load directives.', 'jcore-turva' ) ) );
	}, [ headerType ] );

	const grouped = useMemo( () => {
		if ( ! sources ) return {};
		return sources.reduce( ( acc, source ) => {
			if ( ! acc[ source.directive ] ) acc[ source.directive ] = [];
			acc[ source.directive ].push( source );
			return acc;
		}, {} );
	}, [ sources ] );

	const usedDirectives = Object.keys( grouped );

	const handleAddSource = async ( directive, value ) => {
		try {
			const newSource = await apiFetch( {
				path: '/jcore-turva/v1/sources',
				method: 'POST',
				data: {
					header_type: headerType,
					directive,
					source: value,
					enabled: true,
				},
			} );
			setSources( ( prev ) => [ ...prev, newSource ] );
		} catch {
			// Source card will simply not appear; user can retry.
		}
	};

	const handleToggleSource = async ( id, enabled ) => {
		// Optimistic update.
		setSources( ( prev ) =>
			prev.map( ( s ) => ( s.id === id ? { ...s, enabled } : s ) )
		);
		try {
			await apiFetch( {
				path: `/jcore-turva/v1/sources/${ id }`,
				method: 'PUT',
				data: { enabled },
			} );
		} catch {
			// Revert on failure.
			setSources( ( prev ) =>
				prev.map( ( s ) => ( s.id === id ? { ...s, enabled: ! enabled } : s ) )
			);
		}
	};

	const handleDeleteSource = async ( id ) => {
		const backup = sources;
		setSources( ( prev ) => prev.filter( ( s ) => s.id !== id ) );
		try {
			await apiFetch( {
				path: `/jcore-turva/v1/sources/${ id }`,
				method: 'DELETE',
			} );
		} catch {
			setSources( backup );
		}
	};

	const handleDeleteDirective = async ( directive ) => {
		const toDelete = sources.filter( ( s ) => s.directive === directive );
		const backup = sources;
		setSources( ( prev ) => prev.filter( ( s ) => s.directive !== directive ) );
		try {
			await Promise.all(
				toDelete.map( ( s ) =>
					apiFetch( { path: `/jcore-turva/v1/sources/${ s.id }`, method: 'DELETE' } )
				)
			);
		} catch {
			setSources( backup );
		}
	};

	const handleAddDirective = async ( directive ) => {
		// Flag directives use a placeholder source value; the PHP builder ignores it.
		if ( FLAG_DIRECTIVES.includes( directive ) ) {
			await handleAddSource( directive, '1' );
			return;
		}

		// Seed the new directive with the enabled default-src sources so it starts
		// no more restrictive than the fallback. If default-src has no sources yet,
		// fall back to 'self'.
		const defaultSrcSources = sources
			.filter( ( s ) => s.directive === 'default-src' && s.enabled )
			.map( ( s ) => s.source );

		const seedValues = defaultSrcSources.length > 0 ? defaultSrcSources : [ "'self'" ];

		try {
			const newSources = await Promise.all(
				seedValues.map( ( value ) =>
					apiFetch( {
						path: '/jcore-turva/v1/sources',
						method: 'POST',
						data: {
							header_type: headerType,
							directive,
							source: value,
							enabled: true,
						},
					} )
				)
			);
			setSources( ( prev ) => [ ...prev, ...newSources ] );
		} catch {
			// Directive card will not appear; user can retry.
		}
	};

	if ( error ) {
		return <p className="jcore-turva__error">{ error }</p>;
	}

	if ( sources === null ) {
		return <Spinner />;
	}

	return (
		<div className="jcore-turva__directives">
			{ Object.entries( grouped ).map( ( [ directive, directiveSources ] ) => (
				<DirectiveCard
					key={ directive }
					directive={ directive }
					sources={ directiveSources }
					sourcePlaceholder={ sourcePlaceholder }
					onAddSource={ handleAddSource }
					onToggleSource={ handleToggleSource }
					onDeleteSource={ handleDeleteSource }
					onDeleteDirective={ handleDeleteDirective }
				/>
			) ) }
			<AddDirectivePanel
				availableDirectives={ availableDirectives }
				usedDirectives={ usedDirectives }
				onAdd={ handleAddDirective }
			/>
		</div>
	);
}
