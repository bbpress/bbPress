/**
 * Verify that color normalization preserves non-color CSS values.
 */
var assert    = require( 'assert' ),
	postcss   = require( 'postcss' ),
	normalize = require( '../../bin/normalize-colors' ),
	input     = 'a {color: rgb(0, 149.5, 221);background: hsl(140, 10%, 80%);content:"rgb(0, 149.5, 221)";border-color:rgba(1,2,3,.5);outline-color:rgb(var(--channels))}',
	expected  = 'a {color: #0096dd;background: #c7d1ca;content:"rgb(0, 149.5, 221)";border-color:rgba(1,2,3,.5);outline-color:rgb(var(--channels))}';

assert.strictEqual( postcss( [ normalize ] ).process( input, { from: undefined } ).css, expected );
console.log( 'Color normalization preserves quoted text, transparency, and variables.' );
