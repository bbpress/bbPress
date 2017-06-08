<?php

/**
 * bbPress Converter Base Class
 *
 * Based on the hard work of Adam Ellis
 *
 * @package bbPress
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Base class to be extended by specific individual importers
 *
 * @since 2.1.0 bbPress (r3813)
 */
abstract class BBP_Converter_Base {

	/**
	 * @var array() This is the field mapping array to process.
	 */
	protected $field_map = array();

	/**
	 * @var object This is the connection to the WordPress database.
	 */
	protected $wpdb;

	/**
	 * @var object This is the connection to the other platforms database.
	 */
	protected $opdb;

	/**
	 * @var int Maximum number of rows to convert at 1 time. Default 100.
	 */
	protected $max_rows = 100;

	/**
	 * @var array() Map of topic to forum.  It is for optimization.
	 */
	private $map_topicid_to_forumid = array();

	/**
	 * @var array() Map of from old forum ids to new forum ids.  It is for optimization.
	 */
	private $map_forumid = array();

	/**
	 * @var array() Map of from old topic ids to new topic ids.  It is for optimization.
	 */
	private $map_topicid = array();

	/**
	 * @var array() Map of from old reply_to ids to new reply_to ids.  It is for optimization.
	 */
	private $map_reply_to = array();

	/**
	 * @var array() Map of from old user ids to new user ids.  It is for optimization.
	 */
	private $map_userid = array();

	/**
	 * @var str This is the charset for your wp database.
	 */
	public $charset;

	/**
	 * @var boolean Sync table available.
	 */
	public $sync_table = false;

	/**
	 * @var str Sync table name.
	 */
	public $sync_table_name;

	/**
	 * @var bool Whether users should be converted or not. Default false.
	 */
	public $convert_users = false;

	/** Methods ***************************************************************/

	/**
	 * This is the constructor and it connects to the platform databases.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the converter
	 *
	 * @since 2.1.0
	 */
	private function init() {

		/** Sanitize Options **************************************************/

		$this->convert_users = ! empty( $_POST['_bbp_converter_convert_users'] )
			? true
			: false;

		/** Sanitize Connection ***********************************************/

		$db_user = ! empty( $_POST['_bbp_converter_db_user'] )
			? sanitize_text_field( $_POST['_bbp_converter_db_user'] )
			: DB_USER;

		$db_pass = ! empty( $_POST['_bbp_converter_db_pass'] )
			? sanitize_text_field( $_POST['_bbp_converter_db_pass'] )
			: DB_PASSWORD;

		$db_name = ! empty( $_POST['_bbp_converter_db_name'] )
			? sanitize_text_field( $_POST['_bbp_converter_db_name'] )
			: DB_NAME;

		$db_port = ! empty( $_POST['_bbp_converter_db_port'] )
			? (int) sanitize_text_field( $_POST['_bbp_converter_db_port'] )
			: '';

		$db_server = ! empty( $_POST['_bbp_converter_db_server'] )
			? sanitize_text_field( $_POST['_bbp_converter_db_server'] )
			: DB_HOST;

		$db_prefix = ! empty( $_POST['_bbp_converter_db_prefix'] )
			? sanitize_text_field( $_POST['_bbp_converter_db_prefix'] )
			: '';

		$db_rows = ! empty( $_POST['_bbp_converter_rows'] )
			? (int) $_POST['_bbp_converter_rows']
			: 100;

		// Maybe add port to server
		if ( ! empty( $db_port ) && ! empty( $db_server ) && ! strstr( $db_server, ':' ) ) {
			$db_server = $db_server . ':' . $db_port;
		}

		/** Get database connections ******************************************/

		// Setup WordPress Database
		$this->wpdb     = bbp_db();
		$this->max_rows = $db_rows;

		// Control WPDB db_connect() bailing
		define( 'WP_SETUP_CONFIG', true );

		// Setup old forum Database
		$this->opdb = new wpdb( $db_user, $db_pass, $db_name, $db_server );

		// Connection failed
		if ( ! $this->opdb->db_connect( false ) ) {
			wp_die( 'bbp_converter_db_connection_failed', esc_html__( 'Database connection failed.', 'bbpress' ) );
		}

		// Maybe setup the database prefix
		$this->opdb->prefix = $db_prefix;

		/**
		 * Error Reporting
		 */
		$this->wpdb->show_errors();
		$this->opdb->show_errors();

		/**
		 * Syncing
		 */
		$this->sync_table_name = $this->wpdb->prefix . 'bbp_converter_translator';
		if ( $this->wpdb->get_var( "SHOW TABLES LIKE '" . $this->sync_table_name . "'" ) === $this->sync_table_name ) {
			$this->sync_table = true;
		} else {
			$this->sync_table = false;
		}

		/**
		 * Character set
		 */
		if ( empty( $this->wpdb->charset ) ) {
			$this->charset = 'UTF8';
		} else {
			$this->charset = $this->wpdb->charset;
		}

		/**
		 * Default mapping.
		 */

		/** Forum Section *****************************************************/

		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'comment_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'ping_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'forum',
			'to_fieldname' => 'post_type',
			'default'      => 'forum'
		);

		/** Topic Section *****************************************************/

		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'comment_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'ping_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'topic',
			'to_fieldname' => 'post_type',
			'default'      => 'topic'
		);

		/** Post Section ******************************************************/

		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'post_status',
			'default'      => 'publish'
		);
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'comment_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'ping_status',
			'default'      => 'closed'
		);
		$this->field_map[] = array(
			'to_type'      => 'reply',
			'to_fieldname' => 'post_type',
			'default'      => 'reply'
		);

		/** User Section ******************************************************/

		$this->field_map[] = array(
			'to_type'      => 'user',
			'to_fieldname' => 'role',
			'default'      => get_option( 'default_role' )
		);
	}

	/**
	 * Convert Forums
	 */
	public function convert_forums( $start = 1 ) {
		return $this->convert_table( 'forum', $start );
	}

	/**
	 * Convert Topics / Threads
	 */
	public function convert_topics( $start = 1 ) {
		return $this->convert_table( 'topic', $start );
	}

	/**
	 * Convert Posts
	 */
	public function convert_replies( $start = 1 ) {
		return $this->convert_table( 'reply', $start );
	}

	/**
	 * Convert Users
	 */
	public function convert_users( $start = 1 ) {
		return $this->convert_table( 'user', $start );
	}

	/**
	 * Convert Topic Tags
	 */
	public function convert_tags( $start = 1 ) {
		return $this->convert_table( 'tags', $start );
	}

	/**
	 * Convert Forum Subscriptions
	 */
	public function convert_forum_subscriptions( $start = 1 ) {
		return $this->convert_table( 'forum_subscriptions', $start );
	}

	/**
	 * Convert Topic Subscriptions
	 */
	public function convert_topic_subscriptions( $start = 1 ) {
		return $this->convert_table( 'topic_subscriptions', $start );
	}

	/**
	 * Convert Favorites
	 */
	public function convert_favorites( $start = 1 ) {
		return $this->convert_table( 'favorites', $start );
	}

	/**
	 * Convert Table
	 *
	 * @param string to type
	 * @param int Start row
	 */
	public function convert_table( $to_type, $start ) {

		// Set some defaults
		$has_insert     = false;
		$from_tablename = '';
		$field_list     = $from_tables = $tablefield_array = array();

		// Toggle Table Name based on $to_type (destination)
		switch ( $to_type ) {
			case 'user' :
				$tablename = $this->wpdb->users;
				break;

			case 'tags' :
				$tablename = '';
				break;

			case 'forum_subscriptions' :
				$tablename = $this->wpdb->postmeta;
				break;

			case 'topic_subscriptions' :
				$tablename = $this->wpdb->postmeta;
				break;

			case 'favorites' :
				$tablename = $this->wpdb->postmeta;
				break;

			default :
				$tablename = $this->wpdb->posts;
		}

		// Get the fields from the destination table
		if ( ! empty( $tablename ) ) {
			$tablefield_array = $this->get_fields( $tablename );
		}

		/** Step 1 ************************************************************/

		// Loop through the field maps, and look for to_type matches
		foreach ( $this->field_map as $item ) {

			// Yay a match, and we have a from table, too
			if ( ( $item['to_type'] === $to_type ) && ! empty( $item['from_tablename'] ) ) {

				// $from_tablename was set from a previous loop iteration
				if ( ! empty( $from_tablename ) ) {

					// Doing some joining
					if ( ! in_array( $item['from_tablename'], $from_tables, true ) && in_array( $item['join_tablename'], $from_tables, true ) ) {
						$from_tablename .= ' ' . $item['join_type'] . ' JOIN ' . $this->opdb->prefix . $item['from_tablename'] . ' AS ' . $item['from_tablename'] . ' ' . $item['join_expression'];
					}

				// $from_tablename needs to be set
				} else {
					$from_tablename = $item['from_tablename'] . ' AS ' . $item['from_tablename'];
				}

				// Specific FROM expression data used
				if ( ! empty( $item['from_expression'] ) ) {

					// No 'WHERE' in expression
					if ( stripos( $from_tablename, "WHERE" ) === false ) {
						$from_tablename .= ' ' . $item['from_expression'];

					// 'WHERE' in expression, so replace with 'AND'
					} else {
						$from_tablename .= ' ' . str_replace( "WHERE", "AND", $item['from_expression'] );
					}
				}

				// Add tablename and fieldname to arrays, formatted for querying
				$from_tables[] = $item['from_tablename'];
				$field_list[]  = 'convert(' . $item['from_tablename'] . '.' . $item['from_fieldname'] . ' USING "' . $this->charset . '") AS ' . $item['from_fieldname'];
			}
		}

		/** Step 2 ************************************************************/

		// We have a $from_tablename, so we want to get some data to convert
		if ( ! empty( $from_tablename ) ) {

			// Get some data from the old forums
			$field_list  = array_unique( $field_list );
			$fields      = implode( ',', $field_list );
			$forum_query = "SELECT {$fields} FROM {$this->opdb->prefix}{$from_tablename} LIMIT {$start}, {$this->max_rows}";
			$forum_array = $this->opdb->get_results( $forum_query, ARRAY_A );

			// Set this query as the last one ran
			update_option( '_bbp_converter_query', $forum_query );

			// Query returned some results
			if ( ! empty( $forum_array ) ) {

				// Loop through results
				foreach ( (array) $forum_array as $forum ) {

					// Reset some defaults
					$insert_post = $insert_postmeta = $insert_data = array();

					// Loop through field map, again...
					foreach ( $this->field_map as $row ) {

						// Types match and to_fieldname is present. This means
						// we have some work to do here.
						if ( ( $row['to_type'] === $to_type ) && isset( $row['to_fieldname'] ) ) {

							// This row has a destination that matches one of the
							// columns in this table.
							if ( in_array( $row['to_fieldname'], $tablefield_array, true ) ) {

								// Allows us to set default fields.
								if ( isset( $row['default'] ) ) {
									$insert_post[ $row['to_fieldname'] ] = $row['default'];

								// Translates a field from the old forum.
								} elseif ( isset( $row['callback_method'] ) ) {
									if ( ( 'callback_userid' === $row['callback_method'] ) && ( false === $this->convert_users ) ) {
										$insert_post[ $row['to_fieldname'] ] = $forum[ $row['from_fieldname'] ];
									} else {
										$insert_post[ $row['to_fieldname'] ] = call_user_func_array( array( $this, $row['callback_method'] ), array( $forum[ $row['from_fieldname'] ], $forum ) );
									}

								// Maps the field from the old forum.
								} else {
									$insert_post[ $row['to_fieldname'] ] = $forum[ $row['from_fieldname'] ];
								}

							// Destination field is not empty, so we might need
							// to do some extra work or set a default.
							} elseif ( ! empty( $row['to_fieldname'] ) ) {

								// Allows us to set default fields.
								if ( isset( $row['default'] ) ) {
									$insert_postmeta[ $row['to_fieldname'] ] = $row['default'];

								// Translates a field from the old forum.
								} elseif ( isset( $row['callback_method'] ) ) {
									if ( ( $row['callback_method'] === 'callback_userid' ) && ( false === $this->convert_users ) ) {
										$insert_postmeta[ $row['to_fieldname'] ] = $forum[ $row['from_fieldname'] ];
									} else {
										$insert_postmeta[ $row['to_fieldname'] ] = call_user_func_array( array( $this, $row['callback_method'] ), array( $forum[ $row['from_fieldname'] ], $forum ) );
									}

								// Maps the field from the old forum.
								} else {
									$insert_postmeta[ $row['to_fieldname'] ] = $forum[ $row['from_fieldname'] ];
								}
							}
						}
					}

					/** Step 3 ************************************************/

					// Something to insert into the destination field
					if ( count( $insert_post ) > 0 || ( $to_type == 'tags' && count( $insert_postmeta ) > 0 ) ) {

						switch ( $to_type ) {

							/** New user **************************************/

							case 'user':
								if ( username_exists( $insert_post['user_login'] ) ) {
									$insert_post['user_login'] = 'imported_' . $insert_post['user_login'];
								}

								if ( email_exists( $insert_post['user_email'] ) ) {
									$insert_post['user_email'] = 'imported_' . $insert_post['user_email'];
								}

								$post_id = wp_insert_user( $insert_post );

								if ( is_numeric( $post_id ) ) {

									foreach ( $insert_postmeta as $key => $value ) {

										add_user_meta( $post_id, $key, $value, true );

										if ( '_id' == substr( $key, -3 ) && ( true === $this->sync_table ) ) {
											$this->wpdb->insert( $this->sync_table_name, array( 'value_type' => 'user', 'value_id' => $post_id, 'meta_key' => $key, 'meta_value' => $value ) );
										}
									}
								}
								break;

							/** New Topic-Tag *********************************/

							case 'tags':
								$post_id = wp_set_object_terms( $insert_postmeta['objectid'], $insert_postmeta['name'], 'topic-tag', true );
								$term = get_term_by( 'name', $insert_postmeta['name'], 'topic-tag');
								if ( false !== $term ) {
									wp_update_term( $term->term_id, 'topic-tag', array(
										'description' => $insert_postmeta['description'],
										'slug'        => $insert_postmeta['slug']
									) );
								}
								break;

							/** Forum Subscriptions ***************************/

							case 'forum_subscriptions':
								$user_id = $insert_post['user_id'];
								$items   = wp_list_pluck( $insert_postmeta, '_bbp_forum_subscriptions' );
								if ( is_numeric( $user_id ) && ! empty( $items ) ) {
									foreach ( $items as $value ) {

										// Maybe string with commas
										$value = is_string( $value )
											? explode( ',', $value )
											: (array) $value;

										// Add user ID to forums subscribed users
										foreach ( $value as $fav ) {
											bbp_add_user_forum_subscription( $user_id, $this->callback_forumid( $fav ) );
										}
									}
								}
								break;

							/** Subscriptions *********************************/

							case 'topic_subscriptions':
								$user_id = $insert_post['user_id'];
								$items   = wp_list_pluck( $insert_postmeta, '_bbp_subscriptions' );
								if ( is_numeric( $user_id ) && ! empty( $items ) ) {
									foreach ( $items as $value ) {

										// Maybe string with commas
										$value = is_string( $value )
											? explode( ',', $value )
											: (array) $value;

										// Add user ID to topics subscribed users
										foreach ( $value as $fav ) {
											bbp_add_user_topic_subscription( $user_id, $this->callback_topicid( $fav ) );
										}
									}
								}
								break;

							/** Favorites *************************************/

							case 'favorites':
								$user_id = $insert_post['user_id'];
								$items   = wp_list_pluck( $insert_postmeta, '_bbp_favorites' );
								if ( is_numeric( $user_id ) && ! empty( $items ) ) {
									foreach ( $items as $value ) {

										// Maybe string with commas
										$value = is_string( $value )
											? explode( ',', $value )
											: (array) $value;

										// Add user ID to topics favorited users
										foreach ( $value as $fav ) {
											bbp_add_user_favorite( $user_id, $this->callback_topicid( $fav ) );
										}
									}
								}
								break;

							/** Forum, Topic, Reply ***************************/

							default:
								$post_id = wp_insert_post( $insert_post, true );

								if ( is_numeric( $post_id ) ) {

									foreach ( $insert_postmeta as $key => $value ) {

										add_post_meta( $post_id, $key, $value, true );

										/**
										 * If we are using the sync_table add
										 * the meta '_id' keys to the table
										 *
										 * Forums:  _bbp_old_forum_id         // The old forum ID
										 *          _bbp_old_forum_parent_id  // The old forum parent ID
										 *
										 * Topics:  _bbp_forum_id             // The new forum ID
										 *          _bbp_old_topic_id         // The old topic ID
										 *          _bbp_old_closed_status_id // The old topic open/closed status
										 *          _bbp_old_sticky_status_id // The old topic sticky status
										 *
										 * Replies: _bbp_forum_id             // The new forum ID
										 *          _bbp_topic_id             // The new topic ID
										 *          _bbp_old_reply_id         // The old reply ID
										 *          _bbp_old_reply_to_id      // The old reply to ID
										 */
										if ( '_id' === substr( $key, -3 ) && ( true === $this->sync_table ) ) {
											$this->wpdb->insert( $this->sync_table_name, array( 'value_type' => 'post', 'value_id' => $post_id, 'meta_key' => $key, 'meta_value' => $value ) );
										}

										/**
										 * Replies need to save their old reply_to ID for
										 * hierarchical replies association. Later we update
										 * the _bbp_reply_to value with the new bbPress
										 * value using convert_reply_to_parents()
										 */
										if ( ( 'reply' === $to_type ) && ( '_bbp_old_reply_to_id' === $key ) ) {
											add_post_meta( $post_id, '_bbp_reply_to', $value );
										}
									}
								}
								break;
						}
						$has_insert = true;
					}
				}
			}
		}

		return ! $has_insert;
	}

	/**
	 * This method converts old forum hierarchy to new bbPress hierarchy.
	 */
	public function convert_forum_parents( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}", '_bbp_old_forum_parent_id' );
		} else {
			$query = $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}", '_bbp_old_forum_parent_id' );
		}

		update_option( '_bbp_converter_query', $query );

		$forum_array = $this->wpdb->get_results( $query );

		foreach ( (array) $forum_array as $row ) {
			$parent_id = $this->callback_forumid( $row->meta_value );
			$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->posts} SET post_parent = %d WHERE ID = %d LIMIT 1", $parent_id, $row->value_id ) );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts old topic stickies to new bbPress stickies.
	 *
	 * @since 2.5.0 bbPress (r5170)
	 *
	 * @uses WPDB $wpdb
	 * @uses bbp_stick_topic() to set the imported topic as sticky
	 *
	 */
	public function convert_topic_stickies( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_sticky_status_id', 'sticky' );
		} else {
			$query = $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_sticky_status_id', 'sticky' );
		}

		update_option( '_bbp_converter_query', $query );

		$sticky_array = $this->wpdb->get_results( $query );

		foreach ( (array) $sticky_array as $row ) {
			bbp_stick_topic( $row->value_id );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts old topic super stickies to new bbPress super stickies.
	 *
	 * @since 2.5.0 bbPress (r5170)
	 *
	 * @uses WPDB $wpdb
	 * @uses bbp_stick_topic() to set the imported topic as super sticky
	 *
	 */
	public function convert_topic_super_stickies( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_sticky_status_id', 'super-sticky' );
		} else {
			$query = $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_sticky_status_id', 'super-sticky' );
		}

		update_option( '_bbp_converter_query', $query );

		$sticky_array = $this->wpdb->get_results( $query );

		foreach ( (array) $sticky_array as $row ) {
			$super = true;
			bbp_stick_topic( $row->value_id, $super );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts old closed topics to bbPress closed topics.
	 *
	 * @since 2.6.0 bbPress (r5425)
	 *
	 * @uses bbp_close_topic() to close topics properly
	 *
	 */
	public function convert_topic_closed_topics( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_closed_status_id', 'closed' );
		} else {
			$query = $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_closed_status_id', 'closed' );
		}

		update_option( '_bbp_converter_query', $query );

		$closed_topic = $this->wpdb->get_results( $query );

		foreach ( (array) $closed_topic as $row ) {
			bbp_close_topic( $row->value_id );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts old reply_to post id to new bbPress reply_to post id.
	 *
	 * @since 2.4.0 bbPress (r5093)
	 */
	public function convert_reply_to_parents( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}", '_bbp_old_reply_to_id' );
		} else {
			$query = $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}", '_bbp_old_reply_to_id' );
		}

		update_option( '_bbp_converter_query', $query );

		$reply_to_array = $this->wpdb->get_results( $query );

		foreach ( (array) $reply_to_array as $row ) {
			$reply_to = $this->callback_reply_to( $row->meta_value );
			$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND post_id = %d LIMIT 1", $reply_to, '_bbp_reply_to', $row->value_id ) );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts anonymous topics.
	 *
	 * @since 2.6.0 bbPress (r5538)
	 *
	 * @uses add_post_meta() To add _bbp_anonymous_name topic meta key
	 */
	public function convert_anonymous_topic_authors( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT sync_table1.value_id AS topic_id, sync_table1.meta_value AS topic_is_anonymous, sync_table2.meta_value AS topic_author
							FROM {$this->sync_table_name} AS sync_table1
							INNER JOIN {$this->sync_table_name} AS sync_table2
							ON ( sync_table1.value_id = sync_table2.value_id )
							WHERE sync_table1.meta_value = %s
							AND sync_table2.meta_key = %s
							LIMIT {$start}, {$this->max_rows}", 'true', '_bbp_old_topic_author_name_id' );
		} else {
			$query = $this->wpdb->prepare( "SELECT wp_postmeta1.post_id AS topic_id, wp_postmeta1.meta_value AS topic_is_anonymous, wp_postmeta2.meta_value AS topic_author
							FROM {$this->wpdb->postmeta} AS wp_postmeta1
							INNER JOIN {$this->wpdb->postmeta} AS wp_postmeta2
							ON ( wp_postmeta1.post_id = wp_postmeta2.post_id )
							WHERE wp_postmeta1.meta_value = %s
							AND wp_postmeta2.meta_key = %s
							LIMIT {$start}, {$this->max_rows}", 'true', '_bbp_old_topic_author_name_id' );

		}

		update_option( '_bbp_converter_query', $query );

		$anonymous_topics = $this->wpdb->get_results( $query );

		foreach ( (array) $anonymous_topics as $row ) {
			$anonymous_topic_author_id = 0;
			$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->posts} SET post_author = %d WHERE ID = %d LIMIT 1", $anonymous_topic_author_id, $row->topic_id ) );

			add_post_meta( $row->topic_id, '_bbp_anonymous_name', $row->topic_author );

			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts anonymous replies.
	 *
	 * @since 2.6.0 bbPress (r5538)
	 *
	 * @uses add_post_meta() To add _bbp_anonymous_name reply meta key
	 */
	public function convert_anonymous_reply_authors( $start = 1 ) {

		$has_update = false;

		if ( ! empty( $this->sync_table ) ) {
			$query = $this->wpdb->prepare( "SELECT sync_table1.value_id AS reply_id, sync_table1.meta_value AS reply_is_anonymous, sync_table2.meta_value AS reply_author
							FROM {$this->sync_table_name} AS sync_table1
							INNER JOIN {$this->sync_table_name} AS sync_table2
							ON ( sync_table1.value_id = sync_table2.value_id )
							WHERE sync_table1.meta_value = %s
							AND sync_table2.meta_key = %s
							LIMIT {$start}, {$this->max_rows}", 'true', '_bbp_old_reply_author_name_id' );
		} else {
			$query = $this->wpdb->prepare( "SELECT wp_postmeta1.post_id AS reply_id, wp_postmeta1.meta_value AS reply_is_anonymous, wp_postmeta2.meta_value AS reply_author
							FROM {$this->wpdb->postmeta} AS wp_postmeta1
							INNER JOIN {$this->wpdb->postmeta} AS wp_postmeta2
							ON ( wp_postmeta1.post_id = wp_postmeta2.post_id )
							WHERE wp_postmeta1.meta_value = %s
							AND wp_postmeta2.meta_key = %s
							LIMIT {$start}, {$this->max_rows}", 'true', '_bbp_old_reply_author_name_id' );
		}

		update_option( '_bbp_converter_query', $query );

		$anonymous_replies = $this->wpdb->get_results( $query );

		foreach ( (array) $anonymous_replies as $row ) {
			$anonymous_reply_author_id = 0;
			$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->posts} SET post_author = %d WHERE ID = %d LIMIT 1", $anonymous_reply_author_id, $row->reply_id ) );

			add_post_meta( $row->reply_id, '_bbp_anonymous_name', $row->reply_author );

			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method deletes data from the wp database.
	 */
	public function clean( $start = 1 ) {

		// Defaults
		$has_delete = false;

		/** Delete topics/forums/posts ****************************************/

		if ( true === $this->sync_table ) {
			$query = $this->wpdb->prepare( "SELECT value_id FROM {$this->sync_table_name} INNER JOIN {$this->wpdb->posts} ON(value_id = ID) WHERE meta_key LIKE '_bbp_%' AND value_type = %s GROUP BY value_id ORDER BY value_id DESC LIMIT {$this->max_rows}", 'post' );
		} else {
			$query = $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key LIKE '_bbp_%' GROUP BY post_id ORDER BY post_id DESC LIMIT {$this->max_rows}" );
		}

		update_option( '_bbp_converter_query', $query );

		$posts = $this->wpdb->get_results( $query, ARRAY_A );

		if ( isset( $posts[0] ) && ! empty( $posts[0]['value_id'] ) ) {
			foreach ( (array) $posts as $value ) {
				wp_delete_post( $value['value_id'], true );
			}
			$has_delete = true;
		}

		/** Delete users ******************************************************/

		if ( true === $this->sync_table ) {
			$query = $this->wpdb->prepare( "SELECT value_id FROM {$this->sync_table_name} INNER JOIN {$this->wpdb->users} ON(value_id = ID) WHERE meta_key = %s AND value_type = %s LIMIT {$this->max_rows}", '_bbp_old_user_id', 'user' );
		} else {
			$query = $this->wpdb->prepare( "SELECT user_id AS value_id FROM {$this->wpdb->usermeta} WHERE meta_key = %s LIMIT {$this->max_rows}", '_bbp_old_user_id' );
		}

		update_option( '_bbp_converter_query', $query );

		$users = $this->wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $users ) ) {
			foreach ( $users as $value ) {
				wp_delete_user( $value['value_id'] );
			}
			$has_delete = true;
		}

		unset( $posts );
		unset( $users );

		return ! $has_delete;
	}

	/**
	 * This method deletes passwords from the wp database.
	 *
	 * @param int Start row
	 */
	public function clean_passwords( $start = 1 ) {

		$has_delete = false;
		$query      = $this->wpdb->prepare( "SELECT user_id, meta_value FROM {$this->wpdb->usermeta} WHERE meta_key = %s LIMIT {$start}, {$this->max_rows}", '_bbp_password' );

		update_option( '_bbp_converter_query', $query );

		$converted = $this->wpdb->get_results( $query, ARRAY_A );

		if ( ! empty( $converted ) ) {

			foreach ( $converted as $value ) {
				if ( is_serialized( $value['meta_value'] ) ) {
					$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->users} SET user_pass = '' WHERE ID = %d", $value['user_id'] ) );
				} else {
					$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->users} SET user_pass = %s WHERE ID = %d", $value['meta_value'], $value['user_id'] ) );
					$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id = %d", '_bbp_password', $value['user_id'] ) );
				}
			}
			$has_delete = true;
		}

		return ! $has_delete;
	}

	/**
	 * This method implements the authentication for the different forums.
	 *
	 * @param string Un-encoded password.
	 */
	abstract protected function authenticate_pass( $password, $hash );

	/**
	 * Info
	 */
	abstract protected function info();

	/**
	 * This method grabs appropriate fields from the table specified
	 *
	 * @param string The table name to grab fields from
	 */
	private function get_fields( $tablename ) {
		$rval        = array();
		$field_array = $this->wpdb->get_results( 'DESCRIBE ' . $tablename, ARRAY_A );

		foreach ( $field_array as $field ) {
			$rval[] = $field['Field'];
		}

		if ( $tablename === $this->wpdb->users ) {
			$rval[] = 'role';
			$rval[] = 'yim';
			$rval[] = 'aim';
			$rval[] = 'jabber';
		}

		return $rval;
	}

	/** Callbacks *************************************************************/

	/**
	 * Run password through wp_hash_password()
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function callback_pass( $username, $password ) {
		$user = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->wpdb->users} WHERE user_login = %s AND user_pass = '' LIMIT 1", $username ) );
		if ( ! empty( $user ) ) {
			$usermeta = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id = %d LIMIT 1", '_bbp_password', $user->ID ) );

			if ( ! empty( $usermeta ) ) {
				if ( $this->authenticate_pass( $password, $usermeta->meta_value ) ) {
					$this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->users} SET user_pass = %s WHERE ID = %d", wp_hash_password( $password ), $user->ID ) );
					$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id = %d", '_bbp_password', $user->ID ) );
				}
			}
		}
	}

	/**
	 * A mini cache system to reduce database calls to forum ID's
	 *
	 * @param string $field
	 * @return string
	 */
	private function callback_forumid( $field ) {
		if ( ! isset( $this->map_forumid[ $field ] ) ) {
			if ( ! empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_forum_id', $field ) );
			} else {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_forum_id', $field ) );
			}

			if ( ! is_null( $row ) ) {
				$this->map_forumid[ $field ] = $row->value_id;
			} else {
				$this->map_forumid[ $field ] = 0;
			}
		}
		return $this->map_forumid[ $field ];
	}

	/**
	 * A mini cache system to reduce database calls to topic ID's
	 *
	 * @param string $field
	 * @return string
	 */
	private function callback_topicid( $field ) {
		if ( ! isset( $this->map_topicid[ $field ] ) ) {
			if ( ! empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_topic_id', $field ) );
			} else {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_topic_id', $field ) );
			}

			if ( ! is_null( $row ) ) {
				$this->map_topicid[ $field ] = $row->value_id;
			} else {
				$this->map_topicid[ $field ] = 0;
			}
		}
		return $this->map_topicid[ $field ];
	}

	/**
	 * A mini cache system to reduce database calls to reply_to post id.
	 *
	 * @since 2.4.0 bbPress (r5093)
	 *
	 * @param string $field
	 * @return string
	 */
	private function callback_reply_to( $field ) {
		if ( ! isset( $this->map_reply_to[ $field ] ) ) {
			if ( ! empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_reply_id', $field ) );
			} else {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_reply_id', $field ) );
			}

			if ( ! is_null( $row ) ) {
				$this->map_reply_to[ $field ] = $row->value_id;
			} else {
				$this->map_reply_to[ $field ] = 0;
			}
		}
		return $this->map_reply_to[ $field ];
	}

	/**
	 * A mini cache system to reduce database calls to user ID's
	 *
	 * @param string $field
	 * @return string
	 */
	private function callback_userid( $field ) {
		if ( ! isset( $this->map_userid[ $field ] ) ) {
			if ( ! empty( $this->sync_table ) ) {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_user_id', $field ) );
			} else {
				$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT user_id AS value_id FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_user_id', $field ) );
			}

			if ( ! is_null( $row ) ) {
				$this->map_userid[ $field ] = $row->value_id;
			} else {
				if ( true === $this->convert_users ) {
					$this->map_userid[ $field ] = 0;
				} else {
					$this->map_userid[ $field ] = $field;
				}
			}
		}
		return $this->map_userid[ $field ];
	}

	/**
	 * Check if the topic or reply author is anonymous
	 *
	 * @since 2.6.0 bbPress (r5544)
	 *
	 * @param  string $field
	 * @return string
	 */
	private function callback_check_anonymous( $field ) {

		if ( $this->callback_userid( $field ) == 0 ) {
			$field = 'true';
		} else {
			$field = 'false';
		}

		return $field;
	}

	/**
	 * A mini cache system to reduce database calls map topics ID's to forum ID's
	 *
	 * @param string $field
	 * @return string
	 */
	private function callback_topicid_to_forumid( $field ) {
		$topicid = $this->callback_topicid( $field );
		if ( empty( $topicid ) ) {
			$this->map_topicid_to_forumid[ $topicid ] = 0;
		} elseif ( ! isset( $this->map_topicid_to_forumid[ $topicid ] ) ) {
			$row = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT post_parent FROM {$this->wpdb->posts} WHERE ID = %d LIMIT 1", $topicid ) );

			if ( ! is_null( $row ) ) {
				$this->map_topicid_to_forumid[ $topicid ] = $row->post_parent;
			} else {
				$this->map_topicid_to_forumid[ $topicid ] = 0;
			}
		}

		return $this->map_topicid_to_forumid[ $topicid ];
	}

	protected function callback_slug( $field ) {
		return sanitize_title( $field );
	}

	protected function callback_negative( $field ) {
		return ( $field < 0 )
			? 0
			: $field;
	}

	protected function callback_html( $field ) {
		require_once bbpress()->admin->admin_dir . 'parser.php';
		$bbcode = BBCode::getInstance();
		return html_entity_decode( $bbcode->Parse( $field ) );
	}

	protected function callback_null( $field ) {
		return is_null( $field )
			? ''
			: $field;
	}

	protected function callback_datetime( $field ) {
		return is_numeric( $field )
			? date( 'Y-m-d H:i:s', $field )
			: date( 'Y-m-d H:i:s', strtotime( $field ) );
	}
}
