
const {
	TextControl
} = wp.components;

const {
	__
} = wp.i18n;

function ReplyPicker( { value, onChange } ) {
	return (
		<TextControl
			label={ __( 'Reply' ) }
			value={ value }
			onChange={ onChange }
		/>
	);
}

export default ReplyPicker;
