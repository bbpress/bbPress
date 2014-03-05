/* jshint node:true */
module.exports = function( grunt ) {
	var path = require( 'path' ),
	SOURCE_DIR = 'src/',
	BUILD_DIR = 'build/',

	BBP_RTL_CSS = [
		'includes/admin/css/admin-rtl.css',
		'includes/admin/styles/*/colors-rtl.css',
		'templates/default/css/bbpress-rtl.css'
	],

	BBP_LTR_CSS = [
		'includes/admin/css/admin.css',
		'includes/admin/styles/*/colors.css',
		'templates/default/css/bbpress.css'
	],

	BBP_JS = [
		'includes/admin/js/*.js',
		'templates/default/js/*.js'
	],

	BBP_EXCLUDED_FILES = [
		// Ignore these
		'!tests/**',  // unit tests
		'!Gruntfile.js',
		'!package.json',
		'!.gitignore',
		'!.jshintrc',
		'!.travis.yml',
		'node_modules/**',
		'!**/*.scss',

		// And these from .gitignore
		'!**/.{svn,git}/**',
		'!lib-cov/**',
		'!*.seed',
		'!*.log',
		'!*.csv',
		'!*.dat',
		'!*.out',
		'!*.pid',
		'!*.gz',
		'!pids/**',
		'!logs/**',
		'!results/**',
		'!.DS_Store',
		'!node_modules/**',
		'!npm-debug.log',
		'!build/**'
	];

	// Load tasks.
	require( 'matchdep' ).filterDev( 'grunt-*' ).forEach( grunt.loadNpmTasks );

	// Project configuration.
	grunt.initConfig({
		clean: {
			all: [ BUILD_DIR ],
			dynamic: {
				cwd: BUILD_DIR,
				dot: true,
				expand: true,
				src: []
			}
		},
		copy: {
			files: {
				files: [
					{
						cwd: SOURCE_DIR,
						dest: BUILD_DIR,
						dot: true,
						expand: true,
						src: [ '!**/.{svn,git}/**', '**', [ BBP_EXCLUDED_FILES ] ]
					}
				]
			},
			dynamic: {
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				dot: true,
				expand: true,
				src: []
			}
		},
		sass: {
			colors: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '.css',
				src: ['includes/admin/styles/*/colors.scss'],
				options: {
					outputStyle: 'expanded'
				}
			}
		},
		cssmin: {
			ltr: {
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.css',
				src: BBP_LTR_CSS,
				options: { banner: '/*! https://wordpress.org/plugins/bbpress/ */' }
			},
			rtl: {
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.css',
				src: BBP_RTL_CSS,
				options: { banner: '/*! https://wordpress.org/plugins/bbpress/ */' }
			}
		},
		cssjanus: {
			core: {
				expand: true,
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				ext: '-rtl.css',
				src: BBP_LTR_CSS,
				options: { generateExactDuplicates: true }
			},
			dynamic: {
				expand: true,
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				ext: '-rtl.css',
				src: []
			}
		},
		jshint: {
			options: grunt.file.readJSON( '.jshintrc' ),
			grunt: {
				src: [ 'Gruntfile.js' ]
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				src: BBP_JS,

				/**
				 * Limit JSHint's run to a single specified file: grunt jshint:core --file=filename.js
				 *
				 * @param {String} filepath
				 * @returns {Bool}
				 */
				filter: function( filepath ) {
					var index, file = grunt.option( 'file' );

					// Don't filter when no target file is specified
					if ( ! file ) {
						return true;
					}

					// Normalise filepath for Windows
					filepath = filepath.replace( /\\/g, '/' );
					index = filepath.lastIndexOf( '/' + file );

					// Match only the filename passed from cli
					if ( filepath === file || ( -1 !== index && index === filepath.length - ( file.length + 1 ) ) ) {
						return true;
					}

					return false;
				}
			}
		},
		uglify: {
			core: {
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.js',
				src: BBP_JS
			},
			options: {
				banner: '/*! https://wordpress.org/plugins/bbpress/ */\n'
			}
		},
		phpunit: {
			'default': {
				cmd: 'phpunit',
				args: ['-c', 'phpunit.xml']
			},
			multisite: {
				cmd: 'phpunit',
				args: ['-c', 'tests/phpunit/multisite.xml']
			}
		},
		makepot: {
			target: {
				options: {
					cwd: BUILD_DIR,
					domainPath: '.',
					mainFile: 'bbpress.php',
					potFilename: 'bbpress.pot',
					type: 'wp-plugin'
				}
			}
		},
		checktextdomain: {
			options: {
				text_domain: 'bbpress',
				correct_domain: false,
				keywords: [
				'__:1,2d',
				'_e:1,2d',
				'_x:1,2c,3d',
				'_n:1,2,4d',
				'_ex:1,2c,3d',
				'_nx:1,2,4c,5d',
				'esc_attr__:1,2d',
				'esc_attr_e:1,2d',
				'esc_attr_x:1,2c,3d',
				'esc_html__:1,2d',
				'esc_html_e:1,2d',
				'esc_html_x:1,2c,3d',
				'_n_noop:1,2,3d',
				'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src: SOURCE_DIR + '**/*.php',
				expand: true
			}
		},
		jsvalidate:{
			options:{
				globals: {},
				esprimaOptions:{},
				verbose: false
			},
			build: {
				files: {
					src: BUILD_DIR + '/**/*.js'
				}
			}
		},
		watch: {
			all: {
				files: [
					SOURCE_DIR + '**',
					// Ignore version control directories.
					'!' + SOURCE_DIR + '**/.{svn,git}/**'
				],
				tasks: [ 'clean:dynamic', 'copy:dynamic' ],
				options: {
					dot: true,
					interval: 2000,
					spawn: false
				}
			},
			colors: {
				files: [SOURCE_DIR + 'includes/admin/styles/*/colors.scss'],
				tasks: ['sass:colors', 'cssjanus:core', 'cssmin:ltr', 'cssmin:rtl']
			},
			rtl: {
				files: BBP_LTR_CSS.map( function( path ) {
					return SOURCE_DIR + path;
				} ),
				tasks: [ 'cssjanus:dynamic', 'cssmin:ltr', 'cssmin:rtl' ],
				options: {
					interval: 2000,
					spawn: false
				}
			}
		}
	});

	// Register tasks.

	// Color schemes task.
	grunt.registerTask('colors', ['sass:colors']);

	// Build tasks.
	grunt.registerTask( 'build',         [ 'clean:all', 'copy:files', 'colors', 'cssjanus:core', 'cssmin:ltr', 'cssmin:rtl', 'uglify:core', 'jsvalidate:build', 'makepot' ] );
	grunt.registerTask( 'build-release', [ 'clean:all', 'copy:files', 'colors', 'cssjanus:core', 'cssmin:ltr', 'cssmin:rtl', 'uglify:core', 'jsvalidate:build', 'checktextdomain', 'makepot', 'phpunit' ] );

	// Testing tasks.
	grunt.registerMultiTask( 'phpunit', 'Runs PHPUnit tests, including the ajax and multisite tests.', function() {
		grunt.util.spawn( {
			cmd:  this.data.cmd,
			args: this.data.args,
			opts: { stdio: 'inherit' }
		}, this.async() );
	});

	grunt.registerTask( 'jstest', 'Runs all javascript tasks.', [ 'jsvalidate', 'jshint' ] );

	// Default task.
	grunt.registerTask( 'default', [ 'build' ] );

	// Add a listener to the watch task.
	//
	// On `watch:all`, automatically updates the `copy:dynamic` and `clean:dynamic` configurations so that only the changed files are updated.
	// On `watch:rtl`, automatically updates the `cssjanus:dynamic` configuration.
	grunt.event.on( 'watch', function( action, filepath, target ) {
		if ( target !== 'all' && target !== 'rtl' ) {
			return;
		}

		var relativePath = path.relative( SOURCE_DIR, filepath ),
		cleanSrc = ( action === 'deleted' ) ? [ relativePath ] : [],
		copySrc  = ( action === 'deleted' ) ? [] : [ relativePath ];

		grunt.config( [ 'clean', 'dynamic', 'src' ], cleanSrc );
		grunt.config( [ 'copy', 'dynamic', 'src' ], copySrc );
		grunt.config( [ 'cssjanus', 'dynamic', 'src' ], copySrc );
	});
};
