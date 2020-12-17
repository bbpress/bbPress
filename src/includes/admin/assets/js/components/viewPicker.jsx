
const {
	TextControl
} = wp.components;

const {
	__
} = wp.i18n;

function ViewPicker( { value, onChange } ) {
	return (
		<TextControl
			label={ __( 'View' ) }
			value={ value }
			onChange={ onChange }
		/>
	);
}

export default ViewPicker;
