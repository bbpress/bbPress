
const {
	TextControl
} = wp.components;

const {
	__
} = wp.i18n;

function TopicTagPicker( { value, onChange } ) {
	return (
		<TextControl
			label={ __( 'Topic Tag ID' ) }
			type="number"
			value={ value }
			onChange={ onChange }
		/>
	);
}

export default TopicTagPicker;
