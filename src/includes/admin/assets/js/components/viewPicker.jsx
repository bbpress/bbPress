
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
			placeholder={ __( 'e.g. `popular` or `no-replies`' ) }
			value={ value }
			onChange={ onChange }
		/>
	);
}

export default ViewPicker;
