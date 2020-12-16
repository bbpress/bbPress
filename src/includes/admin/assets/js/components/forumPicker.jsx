
const {
	SelectControl
} = wp.components;

const {
	__
} = wp.i18n;

function ForumPicker( { value, options, onChange } ) {
	console.log( { value, options, onChange } );
	return (
		<SelectControl
			label={ __( 'Forum' ) }
			labelPosition="top"
			value={ value }
			options={ options }
			onChange={ onChange }
		/>
	);
}

export default ForumPicker;
