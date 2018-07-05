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

if ( ! class_exists( 'BBP_Converter_Base' ) ) :
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
	protected $map_topicid_to_forumid = array();

	/**
	 * @var array() Map of from old forum ids to new forum ids.  It is for optimization.
	 */
	protected $map_forumid = array();

	/**
	 * @var array() Map of from old topic ids to new topic ids.  It is for optimization.
	 */
	protected $map_topicid = array();

	/**
	 * @var array() Map of from old reply_to ids to new reply_to ids.  It is for optimization.
	 */
	protected $map_reply_to = array();

	/**
	 * @var array() Map of from old user ids to new user ids.  It is for optimization.
	 */
	protected $map_userid = array();

	/**
	 * @var str This is the charset for your wp database.
	 */
	public $charset = '';

	/**
	 * @var boolean Sync table available.
	 */
	public $sync_table = false;

	/**
	 * @var str Sync table name.
	 */
	public $sync_table_name = '';

	/**
	 * @var bool Whether users should be converted or not. Default false.
	 */
	public $convert_users = false;

	/**
	 * @var bool Whether to clean up any old converter data. Default false.
	 */
	public $clean = false;

	/**
	 * @var array Custom BBCode class properties in a key => value format
	 */
	public $bbcode_parser_properties = array();

	/** Methods ***************************************************************/

	/**
	 * This is the constructor and it connects to the platform databases.
	 */
	public function __construct() {
		$this->init();
		$this->setup_globals();
	}

	/**
	 * Initialize the converter
	 *
	 * @since 2.1.0
	 */
	private function init() {

		/** BBCode Parse Properties *******************************************/

		// Setup smiley URL & path
		$this->bbcode_parser_properties = array(
			'smiley_url' => includes_url( 'images/smilies' ),
			'smiley_dir' => '/' . WPINC . '/images/smilies'
		);

		/** Sanitize Options **************************************************/

		$this->clean         = ! empty( $_POST['_bbp_converter_clean'] );
		$this->convert_users = (bool) get_option( '_bbp_converter_convert_users', false );
		$this->halt          = (bool) get_option( '_bbp_converter_halt',          0     );
		$this->max_rows      = (int)  get_option( '_bbp_converter_rows',          100   );

		/** Sanitize Connection ***********************************************/

		$db_user   = get_option( '_bbp_converter_db_user',   DB_USER     );
		$db_pass   = get_option( '_bbp_converter_db_pass',   DB_PASSWORD );
		$db_name   = get_option( '_bbp_converter_db_name',   DB_NAME     );
		$db_host   = get_option( '_bbp_converter_db_server', DB_HOST     );
		$db_port   = get_option( '_bbp_converter_db_port',   ''          );
		$db_prefix = get_option( '_bbp_converter_db_prefix', ''          );

		// Maybe add port to server
		if ( ! empty( $db_port ) && ! empty( $db_host ) && ! strstr( $db_host, ':' ) ) {
			$db_host = "{$db_host}:{$db_port}";
		}

		/** Get database connections ******************************************/

		// Setup WordPress Database
		$this->wpdb = bbp_db();

		// Setup old forum Database
		$this->opdb = new BBP_Converter_DB( $db_user, $db_pass, $db_name, $db_host );

		// Connection failed
		if ( ! $this->opdb->db_connect( false ) ) {
			$error = new WP_Error( 'bbp_converter_db_connection_failed', esc_html__( 'Database connection failed.', 'bbpress' ) );
			wp_send_json_error( $error );
		}

		// Maybe setup the database prefix
		$this->opdb->prefix = $db_prefix;

		/**
		 * Don't wp_die() uncontrollably
		 */
		$this->wpdb->show_errors( false );
		$this->opdb->show_errors( false );

		/**
		 * Syncing
		 */
		$this->sync_table_name = $this->wpdb->prefix . 'bbp_converter_translator';
		$this->sync_table      = $this->sync_table_name === $this->wpdb->get_var( "SHOW TABLES LIKE '{$this->sync_table_name}'" )
			? true
			: false;

		/**
		 * Character set
		 */
		$this->charset = ! empty( $this->wpdb->charset )
			? $this->wpdb->charset
			: 'UTF8';

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
	 * Setup global values
	 */
	public function setup_globals() {}

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

			// Update rows
			$this->count_rows_by_table( "{$this->opdb->prefix}{$from_tablename}" );

			// Get some data from the old forums
			$field_list  = array_unique( $field_list );
			$fields      = implode( ',', $field_list );
			$forum_query = "SELECT {$fields} FROM {$this->opdb->prefix}{$from_tablename} LIMIT {$start}, {$this->max_rows}";

			// Set this query as the last one ran
			$this->update_query( $forum_query );

			// Get results as an array
			$forum_array = $this->opdb->get_results( $forum_query, ARRAY_A );

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

							case 'user' :
								if ( username_exists( $insert_post['user_login'] ) ) {
									$insert_post['user_login'] = "imported_{$insert_post['user_login']}";
								}

								if ( email_exists( $insert_post['user_email'] ) ) {
									$insert_post['user_email'] = "imported_{$insert_post['user_email']}";
								}

								if ( empty( $insert_post['user_pass'] ) ) {
									$insert_post['user_pass'] = '';
								}

								// Internally re-calls _exists() checks above.
								// Also checks for existing nicename.
								$post_id = wp_insert_user( $insert_post );

								if ( is_numeric( $post_id ) ) {
									foreach ( $insert_postmeta as $key => $value ) {
										add_user_meta( $post_id, $key, $value, true );

										if ( '_id' == substr( $key, -3 ) && ( true === $this->sync_table ) ) {
											$this->wpdb->insert( $this->sync_table_name, array(
												'value_type' => 'user',
												'value_id'   => $post_id,
												'meta_key'   => $key,
												'meta_value' => $value
											) );
										}
									}
								}
								break;

							/** New Topic-Tag *********************************/

							case 'tags' :
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

							case 'forum_subscriptions' :
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
											bbp_add_user_subscription( $user_id, $this->callback_forumid( $fav ) );
										}
									}
								}
								break;

							/** Subscriptions *********************************/

							case 'topic_subscriptions' :
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
											bbp_add_user_subscription( $user_id, $this->callback_topicid( $fav ) );
										}
									}
								}
								break;

							/** Favorites *************************************/

							case 'favorites' :
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

							default :
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
											$this->wpdb->insert( $this->sync_table_name, array(
												'value_type' => 'post',
												'value_id'   => $post_id,
												'meta_key'   => $key,
												'meta_value' => $value
											) );
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
		$query      = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}",           '_bbp_old_forum_parent_id' )
			: $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}", '_bbp_old_forum_parent_id' );

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
			$parent_id = $this->callback_forumid( $row->meta_value );
			$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->posts} SET post_parent = %d WHERE ID = %d LIMIT 1", $parent_id, $row->value_id ) );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts old topic stickies to new bbPress stickies.
	 *
	 * @since 2.5.0 bbPress (r5170)
	 */
	public function convert_topic_stickies( $start = 1 ) {
		$has_update = false;
		$query      = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}",           '_bbp_old_sticky_status_id', 'sticky' )
			: $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_sticky_status_id', 'sticky' );

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
			bbp_stick_topic( $row->value_id );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts old topic super stickies to new bbPress super stickies.
	 *
	 * @since 2.5.0 bbPress (r5170)
	 */
	public function convert_topic_super_stickies( $start = 1 ) {
		$has_update = false;
		$query      = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}",           '_bbp_old_sticky_status_id', 'super-sticky' )
			: $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_sticky_status_id', 'super-sticky' );

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
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
	 */
	public function convert_topic_closed_topics( $start = 1 ) {
		$has_update = false;
		$query      = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}",           '_bbp_old_closed_status_id', 'closed' )
			: $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT {$start}, {$this->max_rows}", '_bbp_old_closed_status_id', 'closed' );

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
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
		$query      = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}",           '_bbp_old_reply_to_id' )
			: $this->wpdb->prepare( "SELECT post_id AS value_id, meta_value FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value > 0 LIMIT {$start}, {$this->max_rows}", '_bbp_old_reply_to_id' );

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
			$reply_to = $this->callback_reply_to( $row->meta_value );
			$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND post_id = %d LIMIT 1", $reply_to, '_bbp_reply_to', $row->value_id ) );
			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts anonymous topics.
	 *
	 * @since 2.6.0 bbPress (r5538)
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

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
			$anonymous_topic_author_id = 0;
			$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->posts} SET post_author = %d WHERE ID = %d LIMIT 1", $anonymous_topic_author_id, $row->topic_id ) );

			add_post_meta( $row->topic_id, '_bbp_anonymous_name', $row->topic_author );

			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method converts anonymous replies.
	 *
	 * @since 2.6.0 bbPress (r5538)
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

		foreach ( $this->count_rows_by_results( $query ) as $row ) {
			$anonymous_reply_author_id = 0;
			$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->posts} SET post_author = %d WHERE ID = %d LIMIT 1", $anonymous_reply_author_id, $row->reply_id ) );

			add_post_meta( $row->reply_id, '_bbp_anonymous_name', $row->reply_author );

			$has_update = true;
		}

		return ! $has_update;
	}

	/**
	 * This method deletes data from the wp database.
	 *
	 * @since 2.6.0 bbPress (r6456)
	 */
	public function clean() {

		// Defaults
		$has_delete = false;

		/** Delete topics/forums/posts ****************************************/

		$esc_like = $this->wpdb->esc_like( '_bbp_' ) . '%';
		$query    = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id FROM {$this->sync_table_name} INNER JOIN {$this->wpdb->posts} ON(value_id = ID) WHERE meta_key LIKE %s AND value_type = %s GROUP BY value_id ORDER BY value_id DESC LIMIT {$this->max_rows}", $esc_like, 'post' )
			: $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key LIKE %s GROUP BY post_id ORDER BY post_id DESC LIMIT {$this->max_rows}", $esc_like );

		$posts = $this->get_results( $query, ARRAY_A );

		if ( isset( $posts[0] ) && ! empty( $posts[0]['value_id'] ) ) {
			foreach ( (array) $posts as $value ) {
				$deleted = wp_delete_post( $value['value_id'], true );

				// Only flag if not empty or error
				if ( ( false === $has_delete ) && ! empty( $deleted ) && ! is_wp_error( $deleted ) ) {
					$has_delete = true;
				}
			}
		}

		/** Delete users ******************************************************/

		$query = ! empty( $this->sync_table )
			? $this->wpdb->prepare( "SELECT value_id FROM {$this->sync_table_name} INNER JOIN {$this->wpdb->users} ON(value_id = ID) WHERE meta_key = %s AND value_type = %s LIMIT {$this->max_rows}", '_bbp_old_user_id', 'user' )
			: $this->wpdb->prepare( "SELECT user_id AS value_id FROM {$this->wpdb->usermeta} WHERE meta_key = %s LIMIT {$this->max_rows}", '_bbp_old_user_id' );

		$users = $this->get_results( $query, ARRAY_A );

		if ( ! empty( $users ) ) {
			foreach ( $users as $value ) {
				$deleted = wp_delete_user( $value['value_id'] );

				// Only flag if not empty or error
				if ( ( false === $has_delete ) && ! empty( $deleted ) && ! is_wp_error( $deleted ) ) {
					$has_delete = true;
				}
			}
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
		$converted  = $this->get_results( $query, ARRAY_A );

		if ( ! empty( $converted ) ) {
			foreach ( $converted as $value ) {
				if ( is_serialized( $value['meta_value'] ) ) {
					$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->users} SET user_pass = '' WHERE ID = %d", $value['user_id'] ) );
				} else {
					$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->users} SET user_pass = %s WHERE ID = %d", $value['meta_value'], $value['user_id'] ) );
					$this->query( $this->wpdb->prepare( "DELETE FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id = %d", '_bbp_password', $value['user_id'] ) );
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
	private function get_fields( $tablename = '' ) {
		$retval      = array();
		$field_array = $this->get_results( "DESCRIBE {$tablename}", ARRAY_A );

		// Bail if no fields
		if ( empty( $field_array ) ) {
			return $retval;
		}

		// Add fields to array
		foreach ( $field_array as $field ) {
			if ( ! empty( $field['Field'] ) ) {
				$retval[] = $field['Field'];
			}
		}

		// Add social fields for users table
		if ( $tablename === $this->wpdb->users ) {
			$retval[] = 'role';
			$retval[] = 'yim';
			$retval[] = 'aim';
			$retval[] = 'jabber';
		}

		return $retval;
	}

	/** Database Wrappers *****************************************************/

	/**
	 * Update the last query option and return results
	 *
	 * @param string $query
	 * @param string $output
	 */
	private function get_row( $query = '' ) {
		$this->update_query( $query );

		return $this->wpdb->get_row( $query );
	}

	/**
	 * Update the last query option and return results
	 *
	 * @param string $query
	 * @param string $output
	 */
	private function get_results( $query = '', $output = OBJECT ) {
		$this->update_query( $query );

		return (array) $this->wpdb->get_results( $query, $output );
	}

	/**
	 * Update the last query option and do a general query
	 *
	 * @param string $query
	 */
	private function query( $query = '' ) {
		$this->update_query( $query );

		return $this->wpdb->query( $query );
	}

	/**
	 * Update the last query ran
	 *
	 * @since 2.6.0 bbPress (r6637)
	 *
	 * @param string $query The literal MySQL query
	 * @return bool
	 */
	private function update_query( $query = '' ) {
		return update_option( '_bbp_converter_query', $query );
	}

	/**
	 * Update the number of rows in the current step
	 *
	 * @since 2.6.0 bbPress (r6637)
	 *
	 * @param string $query The literal MySQL query
	 * @return bool
	 */
	private function count_rows_by_results( $query = '' ) {
		$results = $this->get_results( $query );

		update_option( '_bbp_converter_rows_in_step', count( $results ) );

		return $results;
	}

	/**
	 * Update the number of rows in the current step
	 *
	 * @since 2.6.0 bbPress (r6637)
	 *
	 * @param string $table_name The literal MySQL query
	 * @return bool
	 */
	private function count_rows_by_table( $table_name = '' ) {
		$count = (int) $this->opdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		return update_option( '_bbp_converter_rows_in_step', $count );
	}

	/** Callbacks *************************************************************/

	/**
	 * Run password through wp_hash_password()
	 *
	 * @param string $username
	 * @param string $password
	 */
	public function callback_pass( $username, $password ) {
		$user = $this->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->wpdb->users} WHERE user_login = %s AND user_pass = '' LIMIT 1", $username ) );
		if ( ! empty( $user ) ) {
			$usermeta = $this->get_row( $this->wpdb->prepare( "SELECT * FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id = %d LIMIT 1", '_bbp_password', $user->ID ) );

			if ( ! empty( $usermeta ) ) {
				if ( $this->authenticate_pass( $password, $usermeta->meta_value ) ) {
					$this->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->users} SET user_pass = %s WHERE ID = %d", wp_hash_password( $password ), $user->ID ) );
					$this->query( $this->wpdb->prepare( "DELETE FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND user_id = %d", '_bbp_password', $user->ID ) );

					// Clean the cache for this user since their password was
					// upgraded from the old platform to the new.
					clean_user_cache( $user->ID );
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
			$row = ! empty( $this->sync_table )
				? $this->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_forum_id', $field ) )
				: $this->get_row( $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_forum_id', $field ) );

			$this->map_forumid[ $field ] = ! is_null( $row )
				? $row->value_id
				: 0;
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
			$row = ! empty( $this->sync_table )
				? $this->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_topic_id', $field ) )
				: $this->get_row( $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_topic_id', $field ) );

			$this->map_topicid[ $field ] = ! is_null( $row )
				? $row->value_id
				: 0;
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
			$row = ! empty( $this->sync_table )
				? $this->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_reply_id', $field ) )
				: $this->get_row( $this->wpdb->prepare( "SELECT post_id AS value_id FROM {$this->wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_reply_id', $field ) );

			$this->map_reply_to[ $field ] = ! is_null( $row )
				? $row->value_id
				: 0;
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
			$row = ! empty( $this->sync_table )
				? $this->get_row( $this->wpdb->prepare( "SELECT value_id, meta_value FROM {$this->sync_table_name} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_user_id', $field ) )
				: $this->get_row( $this->wpdb->prepare( "SELECT user_id AS value_id FROM {$this->wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", '_bbp_old_user_id', $field ) );

			if ( ! is_null( $row ) ) {
				$this->map_userid[ $field ] = $row->value_id;
			} else {
				$this->map_userid[ $field ] = ( true === $this->convert_users )
					? 0
					: $field;
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
		$field = ( $this->callback_userid( $field ) == 0 )
			? 'true'
			: 'false';

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
			$row = $this->get_row( $this->wpdb->prepare( "SELECT post_parent FROM {$this->wpdb->posts} WHERE ID = %d LIMIT 1", $topicid ) );

			$this->map_topicid_to_forumid[ $topicid ] = ! is_null( $row )
				? $row->post_parent
				: 0;
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
		require_once bbp_admin()->admin_dir . 'parser.php';

		// Setup the BBCode parser
		$bbcode = BBCode::getInstance();

		// Pass BBCode properties to the parser
		foreach ( $this->bbcode_parser_properties as $prop => $value ) {
			$bbcode->{$prop} = $value;
		}

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
endif;
