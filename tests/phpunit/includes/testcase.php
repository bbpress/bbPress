<?php

class BBP_UnitTestCase extends WP_UnitTestCase {

	protected static $cached_SERVER_NAME = null;

	/**
	 * Fake WP mail globals, to avoid errors
	 */
	public static function setUpBeforeClass() {
		add_filter( 'wp_mail',      array( 'BBP_UnitTestCase', 'setUp_wp_mail'    ) );
		add_filter( 'wp_mail_from', array( 'BBP_UnitTestCase', 'tearDown_wp_mail' ) );
	}

	public function setUp() {
		parent::setUp();

		// There's a bug in the multisite tests that causes the
		// transaction rollback to fail for the first user created,
		// which busts every other attempt to create users. This is a
		// hack workaround.
		global $wpdb;
		if ( is_multisite() ) {
			$user_1 = get_user_by( 'login', 'user 1' );
			if ( $user_1 ) {
				$wpdb->delete( $wpdb->users, array( 'ID' => $user_1->ID ) );
				clean_user_cache( $user_1 );
			}
		}

		$this->factory = new BBP_UnitTest_Factory;
	}

	function clean_up_global_scope() {
		parent::clean_up_global_scope();
	}

	function tearDown() {
		parent::tearDown();

		if ( is_multisite() ) {
			$blogs = wp_get_sites();
			foreach ( $blogs as $blog ) {
				if ( ( int ) $blog[ 'blog_id' ] !== 1 ) {
					wpmu_delete_blog( $blog[ 'blog_id' ], true );
				}
			}
		}
	}

	function assertPreConditions() {
		parent::assertPreConditions();
	}

	function go_to( $url ) {
		global $wpdb;
		global $current_site, $current_blog, $blog_id, $switched, $_wp_switched_stack, $public, $table_prefix, $current_user, $wp_roles;

		// note: the WP and WP_Query classes like to silently fetch parameters
		// from all over the place (globals, GET, etc), which makes it tricky
		// to run them more than once without very carefully clearing everything
		$_GET	 = $_POST	 = array();
		foreach ( array( 'query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow' ) as $v ) {
			if ( isset( $GLOBALS[ $v ] ) ) {
				unset( $GLOBALS[ $v ] );
			}
		}

		$parts = parse_url( $url );
		if ( isset( $parts[ 'scheme' ] ) ) {
			// set the HTTP_HOST
			$GLOBALS[ '_SERVER' ][ 'HTTP_HOST' ] = $parts[ 'host' ];

			$req = $parts[ 'path' ];
			if ( isset( $parts[ 'query' ] ) ) {
				$req .= '?' . $parts[ 'query' ];
				// parse the url query vars into $_GET
				parse_str( $parts[ 'query' ], $_GET );
			}
		} else {
			$req = $url;
		}

		if ( !isset( $parts[ 'query' ] ) ) {
			$parts[ 'query' ] = '';
		}

		// Scheme
		if ( 0 === strpos( $req, '/wp-admin' ) && force_ssl_admin() ) {
			$_SERVER[ 'HTTPS' ] = 'on';
		} else {
			unset( $_SERVER[ 'HTTPS' ] );
		}

		// Set this for bp_core_set_uri_globals()
		$GLOBALS[ '_SERVER' ][ 'REQUEST_URI' ] = $req;
		unset( $_SERVER[ 'PATH_INFO' ] );

		// setup $current_site and $current_blog globals for multisite based on
		// REQUEST_URI; mostly copied from /wp-includes/ms-settings.php
		if ( is_multisite() ) {
			$current_blog	 = $current_site	 = $blog_id		 = null;

			$domain = addslashes( $_SERVER[ 'HTTP_HOST' ] );
			if ( false !== strpos( $domain, ':' ) ) {
				if ( substr( $domain, -3 ) == ':80' ) {
					$domain	= substr( $domain, 0, -3 );
					$_SERVER[ 'HTTP_HOST' ] = substr( $_SERVER[ 'HTTP_HOST' ], 0, -3 );
				} elseif ( substr( $domain, -4 ) == ':443' ) {
					$domain	= substr( $domain, 0, -4 );
					$_SERVER[ 'HTTP_HOST' ] = substr( $_SERVER[ 'HTTP_HOST' ], 0, -4 );
				}
			}
			$path = stripslashes( $_SERVER[ 'REQUEST_URI' ] );

			// Get a cleaned-up version of the wp_version string
			// (strip -src, -alpha, etc which may trip up version_compare())
			$wp_version = ( float ) $GLOBALS[ 'wp_version' ];
			if ( version_compare( $wp_version, '3.9', '>=' ) ) {

				if ( is_admin() ) {
					$path = preg_replace( '#(.*)/wp-admin/.*#', '$1/', $path );
				}

				list( $path ) = explode( '?', $path );

				// Are there even two networks installed?
				$one_network = $wpdb->get_row( "SELECT * FROM $wpdb->site LIMIT 2" ); // [sic]
				if ( 1 === $wpdb->num_rows ) {
					$current_site = wp_get_network( $one_network );
				} elseif ( 0 === $wpdb->num_rows ) {
					ms_not_installed();
				}

				if ( empty( $current_site ) ) {
					$current_site = get_network_by_path( $domain, $path, 1 );
				}

				if ( empty( $current_site ) ) {
					ms_not_installed();
				} elseif ( $path === $current_site->path ) {
					$current_blog = get_site_by_path( $domain, $path );

				// Search the network path + one more path segment (on top of the network path).
				} else {
					$current_blog = get_site_by_path( $domain, $path, substr_count( $current_site->path, '/' ) );
				}

				// The network declared by the site trumps any constants.
				if ( $current_blog && $current_blog->site_id != $current_site->id ) {
					$current_site = wp_get_network( $current_blog->site_id );
				}

				// If we don't have a network by now, we have a problem.
				if ( empty( $current_site ) ) {
					ms_not_installed();
				}

				// @todo What if the domain of the network doesn't match the current site?
				$current_site->cookie_domain = $current_site->domain;
				if ( 'www.' === substr( $current_site->cookie_domain, 0, 4 ) ) {
					$current_site->cookie_domain = substr( $current_site->cookie_domain, 4 );
				}

				// Figure out the current network's main site.
				if ( !isset( $current_site->blog_id ) ) {
					if ( $current_blog && $current_blog->domain === $current_site->domain && $current_blog->path === $current_site->path ) {
						$current_site->blog_id = $current_blog->blog_id;

					// @todo we should be able to cache the blog ID of a network's main site easily.
					} else {
						$current_site->blog_id = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s", $current_site->domain, $current_site->path ) );
					}
				}

				$blog_id = $current_blog->blog_id;
				$public	 = $current_blog->public;

				// This dates to [MU134] and shouldn't be relevant anymore,
				// but it could be possible for arguments passed to insert_blog() etc.
				if ( empty( $current_blog->site_id ) ) {
					$current_blog->site_id = 1;
				}

				$site_id = $current_blog->site_id;
				wp_load_core_site_options( $site_id );


			// Pre WP 3.9
			} else {

				$domain        = rtrim( $domain, '.' );
				$cookie_domain = $domain;
				if ( substr( $cookie_domain, 0, 4 ) == 'www.' ) {
					$cookie_domain	 = substr( $cookie_domain, 4 );
				}

				$path = preg_replace( '|([a-z0-9-]+.php.*)|', '', $GLOBALS[ '_SERVER' ][ 'REQUEST_URI' ] );
				$path = str_replace( '/wp-admin/', '/', $path );
				$path = preg_replace( '|(/[a-z0-9-]+?/).*|', '$1', $path );

				$GLOBALS[ 'current_site' ] = wpmu_current_site();
				if ( !isset( $GLOBALS[ 'current_site' ]->blog_id ) && !empty( $GLOBALS[ 'current_site' ] ) ) {
					$GLOBALS[ 'current_site' ]->blog_id	 = $wpdb->get_var( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s", $GLOBALS[ 'current_site' ]->domain, $GLOBALS[ 'current_site' ]->path ) );
				}

				$blogname = htmlspecialchars( substr( $GLOBALS[ '_SERVER' ][ 'REQUEST_URI' ], strlen( $path ) ) );
				if ( false !== strpos( $blogname, '/' ) ) {
					$blogname			 = substr( $blogname, 0, strpos( $blogname, '/' ) );
				}

				if ( false !== strpos( $blogname, '?' ) ) {
					$blogname = substr( $blogname, 0, strpos( $blogname, '?' ) );
				}

				$reserved_blognames	 = array( 'page', 'comments', 'blog', 'wp-admin', 'wp-includes', 'wp-content', 'files', 'feed' );
				if ( $blogname != '' && !in_array( $blogname, $reserved_blognames ) && !is_file( $blogname ) ) {
					$path .= $blogname . '/';
				}

				$GLOBALS[ 'current_blog' ] = get_blog_details( array( 'domain' => $domain, 'path' => $path ), false );

				unset( $reserved_blognames );

				if ( $GLOBALS[ 'current_site' ] && !$GLOBALS[ 'current_blog' ] ) {
					$GLOBALS[ 'current_blog' ] = get_blog_details( array( 'domain' => $GLOBALS[ 'current_site' ]->domain, 'path' => $GLOBALS[ 'current_site' ]->path ), false );
				}

				$GLOBALS[ 'blog_id' ] = $GLOBALS[ 'current_blog' ]->blog_id;
			}

			// Emulate a switch_to_blog()
			$table_prefix = $wpdb->get_blog_prefix( $current_blog->blog_id );
			$wpdb->set_blog_id( $current_blog->blog_id, $current_blog->site_id );
			$_wp_switched_stack = array();
			$switched = false;

			if ( !isset( $current_site->site_name ) ) {
				$current_site->site_name = get_site_option( 'site_name' );
				if ( !$current_site->site_name ) {
					$current_site->site_name = ucfirst( $current_site->domain );
				}
			}
		}

		$this->flush_cache();
		unset( $GLOBALS[ 'wp_query' ], $GLOBALS[ 'wp_the_query' ] );
		$GLOBALS[ 'wp_the_query' ] = new WP_Query();
		$GLOBALS[ 'wp_query' ] = $GLOBALS[ 'wp_the_query' ];
		$GLOBALS[ 'wp' ] = new WP();

		// clean out globals to stop them polluting wp and wp_query
		foreach ( $GLOBALS[ 'wp' ]->public_query_vars as $v ) {
			unset( $GLOBALS[ $v ] );
		}

		foreach ( $GLOBALS[ 'wp' ]->private_query_vars as $v ) {
			unset( $GLOBALS[ $v ] );
		}

		$GLOBALS[ 'wp' ]->main( $parts[ 'query' ] );

		$wp_roles->reinit();
		$current_user = wp_get_current_user();
		$current_user->for_blog( $blog_id );

		$this->clean_up_global_scope();
		do_action( 'bbp_init' );
	}

	protected function checkRequirements() {

		if ( WP_TESTS_FORCE_KNOWN_BUGS ) {
			return;
		}

		parent::checkRequirements();

		$tickets = PHPUnit_Util_Test::getTickets( get_class( $this ), $this->getName( false ) );
		foreach ( $tickets as $ticket ) {
			if ( 'BBP' == substr( $ticket, 0, 2 ) ) {
				$ticket = substr( $ticket, 2 );
				if ( $ticket && is_numeric( $ticket ) ) {
					$this->knownBPBug( $ticket );
				}
			}
		}
	}

	/**
	 * Skips the current test if there is an open BuddyPress ticket with id $ticket_id
	 */
	function knownBBPBug( $ticket_id ) {
		if ( WP_TESTS_FORCE_KNOWN_BUGS || in_array( $ticket_id, self::$forced_tickets ) ) {
			return;
		}

		$trac_url = 'https://bbpress.trac.wordpress.org';

		// When SSL is not available, use non-SSL mirror
		// Primarily for travis-ci PHP 5.2 build
		if ( !extension_loaded( 'openssl' ) ) {
			$trac_url = '';
			return;
		}

		if ( !TracTickets::isTracTicketClosed( $trac_url, $ticket_id ) ) {
			$this->markTestSkipped( sprintf( 'bbPress Ticket #%d is not fixed', $ticket_id ) );
		}
	}

	/**
	 * WP's core tests use wp_set_current_user() to change the current
	 * user during tests. BP caches the current user differently, so we
	 * have to do a bit more work to change it
	 */
	public static function set_current_user( $user_id ) {
		wp_set_current_user( $user_id );
	}

	/**
	 * We can't use grant_super_admin() because we will need to modify
	 * the list more than once, and grant_super_admin() can only be run
	 * once because of its global check
	 */
	public function grant_super_admin( $user_id ) {
		global $super_admins;
		if ( !is_multisite() ) {
			return;
		}

		$user = get_userdata( $user_id );
		$super_admins[] = $user->user_login;
	}

	/**
	 * We assume that the global can be wiped out
	 *
	 * @see grant_super_admin()
	 */
	public function restore_admins() {
		unset( $GLOBALS[ 'super_admins' ] );
	}

	/**
	 * Set up globals necessary to avoid errors when using wp_mail()
	 */
	public static function setUp_wp_mail( $args ) {
		if ( isset( $_SERVER[ 'SERVER_NAME' ] ) ) {
			self::$cached_SERVER_NAME = $_SERVER[ 'SERVER_NAME' ];
		}

		$_SERVER[ 'SERVER_NAME' ] = 'example.com';

		// passthrough
		return $args;
	}

	/**
	 * Tear down globals set up in setUp_wp_mail()
	 */
	public static function tearDown_wp_mail( $args ) {
		if ( !empty( self::$cached_SERVER_NAME ) ) {
			$_SERVER[ 'SERVER_NAME' ] = self::$cached_SERVER_NAME;
			unset( $this->cached_SERVER_NAME );
		} else {
			unset( $_SERVER[ 'SERVER_NAME' ] );
		}

		// passthrough
		return $args;
	}

	/**
	 * Commit a MySQL transaction.
	 */
	public static function commit_transaction() {
		global $wpdb;
		$wpdb->query( 'COMMIT;' );
	}
}
