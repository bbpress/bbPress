/**
 * Keep generated admin colors in the hexadecimal format used by bbPress.
 */
var colord     = require( 'colord' ).colord,
	valueParser = require( 'postcss-value-parser' );

module.exports = function( root ) {
	root.walkDecls( function( declaration ) {
		var parsed = valueParser( declaration.value );

		parsed.walk( function( node ) {
			if ( node.type !== 'function' || ! /^(rgb|hsl)a?$/i.test( node.value ) ) {
				return;
			}

			var color = colord( valueParser.stringify( node ) );

			// Preserve transparency and expressions that cannot be resolved here.
			if ( color.isValid() && color.alpha() === 1 ) {
				node.type  = 'word';
				node.value = color.toHex();
				delete node.nodes;
			}
		} );

		declaration.value = parsed.toString();
	} );
};
