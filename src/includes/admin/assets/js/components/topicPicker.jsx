
const {
	TextControl
} = wp.components;

const {
	__
} = wp.i18n;

function TopicPicker( { value, onChange } ) {
	return (
		<TextControl
			label={ __( 'Topic ID' ) }
			type="number"
			value={ value }
			onChange={ onChange }
		/>
	);
}

export default TopicPicker;
