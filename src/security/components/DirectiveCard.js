import { useState } from '@wordpress/element';
import {
	Button,
	Modal,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { trash, plus, pencil, check, closeSmall } from '@wordpress/icons';
import { __, _n, sprintf } from '@wordpress/i18n';
import { FLAG_DIRECTIVES } from '../constants';
import { describeSource, sourceValueClassName } from '../utils';

function SourceRow( { source, showMatchType, onToggle, onEdit, onDelete } ) {
	const [ draft, setDraft ] = useState( null );
	const isEditing = draft !== null;

	const cancel = () => setDraft( null );

	const save = () => {
		const trimmed = draft.trim();
		setDraft( null );
		if ( trimmed && trimmed !== source.source ) {
			onEdit( trimmed );
		}
	};

	if ( isEditing ) {
		return (
			<div className="jcore-turva__source-row is-editing">
				<TextControl
					__nextHasNoMarginBottom
					className="jcore-turva__source-edit"
					label={ __( 'Source', 'jcore-turva' ) }
					hideLabelFromVision
					value={ draft }
					onChange={ setDraft }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' ) {
							save();
						} else if ( e.key === 'Escape' ) {
							cancel();
						}
					} }
					// eslint-disable-next-line jsx-a11y/no-autofocus -- Focus follows the user's click on Edit.
					autoFocus
				/>
				<Button
					icon={ check }
					label={ __( 'Save', 'jcore-turva' ) }
					variant="tertiary"
					size="small"
					onClick={ save }
				/>
				<Button
					icon={ closeSmall }
					label={ __( 'Cancel', 'jcore-turva' ) }
					variant="tertiary"
					size="small"
					onClick={ cancel }
				/>
			</div>
		);
	}

	const isEmpty = source.source === '';

	return (
		<div className="jcore-turva__source-row">
			<code
				className={
					( showMatchType
						? sourceValueClassName( source.source )
						: 'jcore-turva__source-value' ) +
					( ! source.enabled ? ' is-disabled' : '' )
				}
				title={
					showMatchType
						? describeSource( source.source ).description
						: undefined
				}
			>
				{ isEmpty
					? __( '(empty — feature denied)', 'jcore-turva' )
					: source.source }
			</code>
			<ToggleControl
				__nextHasNoMarginBottom
				className="jcore-turva__source-toggle"
				label={ __( 'Enabled', 'jcore-turva' ) }
				checked={ source.enabled }
				onChange={ onToggle }
			/>
			{ ! isEmpty && (
				<Button
					icon={ pencil }
					label={ __( 'Edit source', 'jcore-turva' ) }
					variant="tertiary"
					size="small"
					onClick={ () => setDraft( source.source ) }
				/>
			) }
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
		if ( ! trimmed ) {
			return;
		}
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
					__(
						"e.g. 'self', 'unsafe-inline', https://cdn.example.com",
						'jcore-turva'
					)
				}
				value={ value }
				onChange={ setValue }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' ) {
						handleSubmit();
					}
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
	showMatchType,
	onAddSource,
	onToggleSource,
	onEditSource,
	onDeleteSource,
	onDeleteDirective,
} ) {
	const isFlag = FLAG_DIRECTIVES.includes( directive );
	const flagSource = isFlag ? sources[ 0 ] : null;
	const [ isConfirmingDelete, setIsConfirmingDelete ] = useState( false );

	return (
		<div
			className={
				'jcore-turva__directive-card' +
				( isFlag ? ' jcore-turva__directive-card--flag' : '' )
			}
		>
			<div className="jcore-turva__directive-header">
				<code>{ directive }</code>
				<Button
					icon={ trash }
					label={ __( 'Remove directive', 'jcore-turva' ) }
					isDestructive
					variant="tertiary"
					size="small"
					onClick={ () => setIsConfirmingDelete( true ) }
				/>
			</div>
			{ isConfirmingDelete && (
				<Modal
					title={ __( 'Remove directive?', 'jcore-turva' ) }
					size="small"
					onRequestClose={ () => setIsConfirmingDelete( false ) }
				>
					<p>
						{ isFlag
							? sprintf(
									/* translators: %s: CSP directive name */
									__(
										'Remove %s from the policy?',
										'jcore-turva'
									),
									directive
							  )
							: sprintf(
									/* translators: 1: directive name, 2: number of sources */
									_n(
										'Remove %1$s and its %2$d source? This cannot be undone.',
										'Remove %1$s and all %2$d of its sources? This cannot be undone.',
										sources.length,
										'jcore-turva'
									),
									directive,
									sources.length
							  ) }
					</p>
					<div className="jcore-turva__modal-actions">
						<Button
							variant="primary"
							isDestructive
							onClick={ () => {
								setIsConfirmingDelete( false );
								onDeleteDirective( directive );
							} }
						>
							{ __( 'Remove', 'jcore-turva' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setIsConfirmingDelete( false ) }
						>
							{ __( 'Cancel', 'jcore-turva' ) }
						</Button>
					</div>
				</Modal>
			) }
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
								showMatchType={ showMatchType }
								onToggle={ ( v ) =>
									onToggleSource( source.id, v )
								}
								onEdit={ ( v ) => onEditSource( source.id, v ) }
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
