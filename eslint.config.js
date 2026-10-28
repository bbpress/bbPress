var js      = require( '@eslint/js' ),
	globals = require( 'globals' );

module.exports = [
	{
		files: [ 'Gruntfile.js', 'src/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2018,
			sourceType: 'script',
			globals: Object.assign( {}, globals.browser, globals.es2015, globals.jquery, {
				_: 'readonly',
				Backbone: 'readonly',
				BBP_Converter: 'readonly',
				QTags: 'readonly',
				ajaxurl: 'readonly',
				bbpBlocksJS: 'readonly',
				bbpEngagementJS: 'readonly',
				edButtons: 'readonly',
				switchEditors: 'readonly',
				tinymce: 'readonly',
				tinyMCE: 'readonly',
				wp: 'readonly'
			} )
		},
		rules: Object.assign( {}, js.configs.recommended.rules, {
			'no-unused-vars': [ 'error', { caughtErrors: 'none' } ],
			'no-useless-assignment': 'off'
		} )
	},
	{
		files: [ 'Gruntfile.js' ],
		languageOptions: {
			globals: globals.node
		}
	}
];
