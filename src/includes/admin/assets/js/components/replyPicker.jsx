
const {
	TextControl
} = wp.components;

const {
	__
} = wp.i18n;

function ReplyPicker( { value, onChange } ) {
	return (
		<TextControl
			label={ __( 'Reply ID' ) }
			type="number"
			value={ value }
			onChange={ onChange }
		/>
	);
}

export default ReplyPicker;
