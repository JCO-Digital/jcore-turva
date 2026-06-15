import { useState } from '@wordpress/element';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { plus } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

export default function AddDirectivePanel( {
	availableDirectives,
	usedDirectives,
	onAdd,
} ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ selected, setSelected ] = useState( '' );
	const [ custom, setCustom ] = useState( '' );
	const [ isAdding, setIsAdding ] = useState( false );

	const remaining = availableDirectives.filter(
		( d ) => ! usedDirectives.includes( d )
	);

	const options = [
		{ value: '', label: __( '— Select directive —', 'jcore-turva' ) },
		...remaining.map( ( d ) => ( { value: d, label: d } ) ),
		{ value: '__custom', label: __( 'Custom…', 'jcore-turva' ) },
	];

	const handleAdd = async () => {
		const directive = selected === '__custom' ? custom.trim() : selected;
		if ( ! directive ) {
			return;
		}
		setIsAdding( true );
		await onAdd( directive );
		setSelected( '' );
		setCustom( '' );
		setIsOpen( false );
		setIsAdding( false );
	};

	const handleCancel = () => {
		setIsOpen( false );
		setSelected( '' );
		setCustom( '' );
	};

	if ( ! isOpen ) {
		return (
			<Button
				icon={ plus }
				variant="secondary"
				onClick={ () => setIsOpen( true ) }
			>
				{ __( 'Add directive', 'jcore-turva' ) }
			</Button>
		);
	}

	const isCustom = selected === '__custom';
	const canSubmit = selected && ( ! isCustom || custom.trim() ) && ! isAdding;

	return (
		<div className="jcore-turva__add-directive">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Directive', 'jcore-turva' ) }
				value={ selected }
				options={ options }
				onChange={ setSelected }
			/>
			{ isCustom && (
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Directive name', 'jcore-turva' ) }
					placeholder="e.g. script-src-elem"
					value={ custom }
					onChange={ setCustom }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' && canSubmit ) {
							handleAdd();
						}
					} }
				/>
			) }
			<div className="jcore-turva__add-directive-actions">
				<Button
					variant="primary"
					onClick={ handleAdd }
					isBusy={ isAdding }
					disabled={ ! canSubmit }
				>
					{ __( 'Add directive', 'jcore-turva' ) }
				</Button>
				<Button variant="tertiary" onClick={ handleCancel }>
					{ __( 'Cancel', 'jcore-turva' ) }
				</Button>
			</div>
		</div>
	);
}
