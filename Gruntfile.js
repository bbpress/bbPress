module.exports = function( grunt ) {
	var path = require( 'path' ),
		SOURCE_DIR = 'src/',
		BUILD_DIR = 'build/',

		BBP_RTL_CSS = [
			'includes/admin/assets/css/admin-rtl.css',
			'includes/admin/styles/*/colors-rtl.css',
			'templates/default/css/bbpress-rtl.css'
		],

		BBP_LTR_CSS = [
			'includes/admin/assets/css/admin.css',
			'includes/admin/styles/*/colors.css',
			'templates/default/css/bbpress.css'
		],

		BBP_JS = [
			'includes/admin/assets/js/*.js',
			'templates/default/js/*.js'
		],

		BBP_EXCLUDED_FILES = [

			// Ignore these
			'!**/.{svn,git}/**',
			'!.editorconfig',
			'!.gitignore',
			'!.travis.yml',
			'!build/**',
			'!Gruntfile.js',
			'!node_modules/**',
			'!npm-debug.log',
			'!package.json',

			// bbPress SCSS CSS source files
			'!**/*.scss',

			// bbPress PHPUnit tests
			'!tests/**',
			'!phpunit.xml',
			'!phpunit.xml.dist'
		],

		// PostCSS
		autoprefixer = require('autoprefixer');

	// Load tasks.
	require( 'matchdep' ).filterDev([ 'grunt-*', '!grunt-legacy-util' ]).forEach( grunt.loadNpmTasks );

	// Load legacy utils
	grunt.util = require( 'grunt-legacy-util' );

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON( 'package.json' ),
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
		clean: {
			all: [ BUILD_DIR ],
			dynamic: {
				cwd: BUILD_DIR,
				dot: true,
				expand: true,
				src: []
			}
		},
		checkDependencies: {
			options: {
				packageManager: 'npm'
			},
			src: {}
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
		cssmin: {
			ltr: {
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.css',
				src: BBP_LTR_CSS
			},
			rtl: {
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.css',
				src: BBP_RTL_CSS
			}
		},
		eslint: {
			options: {
				overrideConfigFile: '.eslintrc.json'
			},
			grunt: {
				src: [ 'Gruntfile.js' ]
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				src: BBP_JS,

				/**
				 * Limit ESLint's run to a single specified file:
				 *
				 * grunt eslint:core --file=filename.js
				 *
				 * Optionally, include the file path:
				 *
				 * grunt eslint:core --file=path/to/filename.js
				 *
				 * @param {String} filepath
				 * @returns {Bool}
				 */
				filter: function( filepath ) {
					var index, file = grunt.option( 'file' );

					if ( ! file ) {
						return true;
					}

					filepath = filepath.replace( /\\/g, '/' );
					index = filepath.lastIndexOf( '/' + file );

					if ( filepath === file || ( -1 !== index && index === filepath.length - ( file.length + 1 ) ) ) {
						return true;
					}

					return false;
				}
			}
		},
		makepot: {
			target: {
				options: {
					cwd: BUILD_DIR,
					domainPath: '.',
					mainFile: 'bbpress.php',
					potFilename: 'bbpress.pot',
					processPot: function( pot ) {
						pot.headers['report-msgid-bugs-to'] = 'https://bbpress.trac.wordpress.org';
						pot.headers['last-translator'] = 'JOHN JAMES JACOBY <jjj@bbpress.org>';
						pot.headers['language-team'] = 'ENGLISH <jjj@bbpress.org>';
						return pot;
					},
					type: 'wp-plugin'
				}
			}
		},
		patch: {
			options: {
				tracUrl: 'bbpress.trac.wordpress.org'
			}
		},
		upload_patch: {
			options: {
				tracUrl: 'bbpress.trac.wordpress.org'
			}
		},
		phpcs: {
			'default': {
				cmd: 'vendor/bin/phpcs',
				args: [ '--report-summary', '--report-source', '--cache=.phpcscache' ]
			}
		},
		phpunit: {
			'default': {
				cmd: 'vendor/bin/phpunit',
				args: [ '-c', 'phpunit.xml.dist' ]
			},
			buddypress: {
				cmd: 'vendor/bin/phpunit',
				args: [ '-c', 'tests/phpunit/buddypress.xml' ]
			},
			multisite: {
				cmd: 'vendor/bin/phpunit',
				args: [ '-c', 'tests/phpunit/multisite.xml' ]
			}
		},
		postcss: {
			options: {
				map: false,
				processors: [
					autoprefixer({
						browsers: ['extends @wordpress/browserslist-config'],
						cascade: false
					})
				],
				failOnError: false
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: SOURCE_DIR,
				src: [ [ BBP_LTR_CSS ], 'includes/admin/styles/*/colors.scss' ]
			},
			colors: {
				expand: true,
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				src: [ 'includes/admin/styles/*/colors.css' ]
			}
		},
		rtlcss: {
			options: {
				opts: {
					processUrls: false,
					autoRename: false,
					clean: true
				},
				saveUnmodified: false
			},
			core: {
				expand: true,
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				ext: '-rtl.css',
				src: BBP_LTR_CSS
			},
			dynamic: {
				expand: true,
				cwd: BUILD_DIR,
				dest: BUILD_DIR,
				ext: '-rtl.css',
				src: []
			}
		},
		sass: {
			colors: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				ext: '.css',
				src: [ 'includes/admin/styles/*/colors.scss' ],
				options: {
					implementation: require('node-sass'),
					outputStyle: 'expanded'
				}
			}
		},
		stylelint: {
			css: {
				options: {
					configFile: '.stylelint.css.json',
					format: 'css',
					quietDeprecationWarnings: true
				},
				expand: true,
				cwd: SOURCE_DIR,
				src: BBP_LTR_CSS
			},
			scss: {
				options: {
					configFile: '.stylelint.scss.json',
					format: 'scss',
					quietDeprecationWarnings: true
				},
				expand: true,
				cwd: SOURCE_DIR,
				src: [ 'includes/admin/styles/*/colors.scss' ]
			}
		},
		terser: {
			core: {
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.js',
				src: BBP_JS
			},
			dynamic: {
				cwd: SOURCE_DIR,
				dest: BUILD_DIR,
				expand: true,
				ext: '.min.js',
				src: []
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
				files: [ SOURCE_DIR + 'includes/admin/styles/*/colors.scss' ],
				tasks: [ 'sass:colors', 'rtlcss:core', 'cssmin:ltr', 'cssmin:rtl' ]
			},
			config: {
				files: 'Gruntfile.js'
			},
			js: {
				files: BBP_JS.map( function( path ) {
					return SOURCE_DIR + path;
				} ),
				tasks: [ 'terser:dynamic' ],
				options: {
					interval: 2000,
					spawn: false
				}
			},
			rtl: {
				files: BBP_LTR_CSS.map( function( path ) {
					return SOURCE_DIR + path;
				} ),
				tasks: [ 'rtlcss:dynamic', 'cssmin:ltr', 'cssmin:rtl' ],
				options: {
					interval: 2000,
					spawn: false
				}
			}
		}
	});

	// Register tasks.

	// Color schemes task.
	grunt.registerTask( 'colors', [ 'sass:colors', 'postcss:colors' ] );

	// Build tasks.
	grunt.registerTask( 'src',     [ 'checkDependencies', 'eslint:grunt', 'eslint:core', 'stylelint' ] );
	grunt.registerTask( 'commit',  [ 'src', 'checktextdomain', 'postcss:core' ] );
	grunt.registerTask( 'build',   [ 'commit', 'clean:all', 'copy:files', 'colors', 'rtlcss:core', 'cssmin:ltr', 'cssmin:rtl', 'terser:core', 'makepot' ] );
	grunt.registerTask( 'release', [ 'build' ] );

	// PHPCS test task.
	grunt.registerMultiTask( 'phpcs', 'Runs PHPCS tests.', function() {
		grunt.util.spawn( {
			cmd:  this.data.cmd,
			args: this.data.args,
			opts: { stdio: 'inherit' }
		}, this.async() );
	} );

	// PHPUnit test task.
	grunt.registerMultiTask( 'phpunit', 'Runs PHPUnit tests, including the BuddyPress and multisite tests.', function() {
		grunt.util.spawn( {
			cmd:  this.data.cmd,
			args: this.data.args,
			opts: { stdio: 'inherit' }
		}, this.async() );
	} );

	// JavaScript test task.
	grunt.registerTask( 'jstest', 'Runs all JavaScript tasks.', [ 'eslint:grunt', 'eslint:core' ] );

	// Travis CI Task
	grunt.registerTask( 'travis', [ 'eslint:grunt', 'eslint:core', 'checktextdomain', 'phpunit' ] );

	// Patch task.
	grunt.renameTask( 'patch_wordpress', 'patch' );

	// Default task.
	grunt.registerTask( 'default', [ 'src' ] );

	// Add a listener to the watch task.
	//
	// On `watch:all`, automatically updates the `copy:dynamic` and `clean:dynamic` configurations so that only the changed files are updated.
	// On `watch:rtl`, automatically updates the `rtlcss:dynamic` configuration.
	grunt.event.on( 'watch', function( action, filepath, target ) {
		if ( target !== 'all' && target !== 'rtl' ) {
			return;
		}

		var relativePath = path.relative( SOURCE_DIR, filepath ),
		cleanSrc = ( action === 'deleted' ) ? [ relativePath ] : [],
		copySrc  = ( action === 'deleted' ) ? [] : [ relativePath ];

		grunt.config( [ 'clean', 'dynamic', 'src' ], cleanSrc );
		grunt.config( [ 'copy', 'dynamic', 'src' ], copySrc );
		grunt.config( [ 'rtlcss', 'dynamic', 'src' ], copySrc );
		grunt.config( [ 'terser', 'dynamic', 'src' ], copySrc );
	});
};
