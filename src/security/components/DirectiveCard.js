import { useState } from '@wordpress/element';
import { Button, TextControl, ToggleControl } from '@wordpress/components';
import { trash, plus } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { FLAG_DIRECTIVES } from '../constants';

function SourceRow( { source, onToggle, onDelete } ) {
	return (
		<div className="jcore-turva__source-row">
			<code
				className={
					'jcore-turva__source-value' +
					( ! source.enabled ? ' is-disabled' : '' )
				}
			>
				{ source.source }
			</code>
			<ToggleControl
				__nextHasNoMarginBottom
				className="jcore-turva__source-toggle"
				label={ __( 'Enabled', 'jcore-turva' ) }
				checked={ source.enabled }
				onChange={ onToggle }
			/>
			<Button
				icon={ trash }
				label={ __( 'Remove source', 'jcore-turva' ) }
				isDestructive
				variant="tertiary"
				size="small"
				onClick={ onDelete }
			/>
		</div>
	);
}

function AddSourceForm( { directive, onAdd, placeholder } ) {
	const [ value, setValue ] = useState( '' );
	const [ isAdding, setIsAdding ] = useState( false );

	const handleSubmit = async () => {
		const trimmed = value.trim();
		if ( ! trimmed ) return;
		setIsAdding( true );
		await onAdd( directive, trimmed );
		setValue( '' );
		setIsAdding( false );
	};

	return (
		<div className="jcore-turva__add-source">
			<TextControl
				__nextHasNoMarginBottom
				placeholder={
					placeholder ??
					__( "e.g. 'self', 'unsafe-inline', https://cdn.example.com", 'jcore-turva' )
				}
				value={ value }
				onChange={ setValue }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) handleSubmit();
				} }
			/>
			<Button
				icon={ plus }
				label={ __( 'Add source', 'jcore-turva' ) }
				variant="secondary"
				onClick={ handleSubmit }
				isBusy={ isAdding }
				disabled={ ! value.trim() || isAdding }
			>
				{ __( 'Add', 'jcore-turva' ) }
			</Button>
		</div>
	);
}

export default function DirectiveCard( {
	directive,
	sources,
	sourcePlaceholder,
	onAddSource,
	onToggleSource,
	onDeleteSource,
	onDeleteDirective,
} ) {
	const isFlag = FLAG_DIRECTIVES.includes( directive );
	const flagSource = isFlag ? sources[ 0 ] : null;

	return (
		<div className={ 'jcore-turva__directive-card' + ( isFlag ? ' jcore-turva__directive-card--flag' : '' ) }>
			<div className="jcore-turva__directive-header">
				<code>{ directive }</code>
				<Button
					icon={ trash }
					label={ __( 'Remove directive', 'jcore-turva' ) }
					isDestructive
					variant="tertiary"
					size="small"
					onClick={ () => onDeleteDirective( directive ) }
				/>
			</div>
			<div className="jcore-turva__directive-body">
				{ isFlag ? (
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Enabled', 'jcore-turva' ) }
						checked={ flagSource?.enabled ?? false }
						onChange={ ( v ) =>
							flagSource && onToggleSource( flagSource.id, v )
						}
					/>
				) : (
					<>
						{ sources.map( ( source ) => (
							<SourceRow
								key={ source.id }
								source={ source }
								onToggle={ ( v ) => onToggleSource( source.id, v ) }
								onDelete={ () => onDeleteSource( source.id ) }
							/>
						) ) }
						<AddSourceForm
							directive={ directive }
							onAdd={ onAddSource }
							placeholder={ sourcePlaceholder }
						/>
					</>
				) }
			</div>
		</div>
	);
}
