<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName
/**
 * Blocks loader for bbPress.
 *
 * @package bbPress
 * @subpackage Blocks
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Blocks' ) ) :
	/**
	 * BbPress shortcode class.
	 *
	 * @since 2.0.0 bbPress (r3031)
	 */
	class BBP_Blocks {

		/** Vars ******************************************************************/

		/**
		 * Map of block names to shortcode callbacks.
		 *
		 * @var array Block => function
		 */
		public $blocks = array();

		/** Functions *************************************************************/

		/**
		 * Add the register_blocks action to bbp_init.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 */
		public function __construct() {
			$this->setup_globals();
			$this->register_blocks();
			$this->setup_hooks();
		}

		/**
		 * Block globals.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 */
		private function setup_globals() {

			// Initialize blocks array (will be populated during registration).
			$this->blocks = array();
		}

		/**
		 * Register blocks from block.json files.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 */
		public function register_blocks() {

			// Bail if no block type functionality.
			if ( ! function_exists( 'register_block_type' ) ) {
				return;
			}

			$blocks_dir = __DIR__ . '/blocks';

			// Bail if blocks directory doesn't exist.
			if ( ! is_dir( $blocks_dir ) ) {
				return;
			}

			// Scan the blocks directory for block.json files.
			$block_folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );

			// Bail if no block folders found.
			if ( empty( $block_folders ) ) {
				return;
			}

			// Loop through the folders.
			foreach ( $block_folders as $block_folder ) {
				$block_json = $block_folder . '/block.json';

				// Skip if block.json doesn't exist.
				if ( ! file_exists( $block_json ) ) {
					continue;
				}

				// Register the block from block.json.
				$registered = register_block_type(
					$block_folder,
					array(
						'render_callback' => array( $this, 'render_block' ),
					)
				);

				// Add to blocks map for render callback lookup.
				if ( empty( $registered->name ) ) {
					continue;
				}

				// Derive shortcode slug from block name.
				$shortcode_slug                    = str_replace( 'bbpress/', 'bbp-', $registered->name );
				$this->blocks[ $registered->name ] = $shortcode_slug;
			}
		}

		/**
		 * Setup actions & filters.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 */
		private function setup_hooks() {

			// Enqueue and localize block editor scripts.
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

			// Register custom block category.
			add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );

			// Allow plugins to modify these actions
			do_action_ref_array( 'bbp_blocks_loaded', array( &$this ) );
		}

		/**
		 * Enqueue block editor assets and localize script data.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 */
		public function enqueue_block_editor_assets() {

			// Localize script data for the block editor (script is loaded via block.json).
			wp_localize_script(
				'bbp-admin-blocks',
				'bbpBlocksJS',
				array(

					// Nonce.
					'ajax_nonce' => wp_create_nonce( 'bbp_blocks' ),

					// Block metadata for registration.
					'blocks'     => self::get_block_metadata(),

					// Block data.
					'data'       => array(
						'forums' => self::get_localize_script_data( 'forums' ),
						'views'  => self::get_localize_script_data( 'views' ),
						'tags'   => self::get_localize_script_data( 'topic_tags' ),
					),

					// Strings.
					'strings'    => array(

						// Select text.
						'forum_select' => esc_html__( 'Select a Forum', 'bbpress' ),
					),
				)
			);
		}

		/**
		 * Get block metadata from block.json files for JavaScript registration.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 *
		 * @return array Array of block metadata objects.
		 */
		public static function get_block_metadata() {
			$blocks     = array();
			$blocks_dir = __DIR__ . '/blocks';

			if ( ! is_dir( $blocks_dir ) ) {
				return $blocks;
			}

			$block_folders = glob( $blocks_dir . '/*', GLOB_ONLYDIR );

			foreach ( $block_folders as $block_folder ) {
				$block_json_file = $block_folder . '/block.json';

				if ( ! file_exists( $block_json_file ) ) {
					continue;
				}

				$json_content = file_get_contents( $block_json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local block metadata.
				$block_data   = json_decode( $json_content, true );

				if ( $block_data && ! empty( $block_data['name'] ) ) {
					$blocks[] = $block_data;
				}
			}

			return $blocks;
		}

		/**
		 * Reuse shortcode callbacks to render equivalent block output.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 *
		 * @param array       $attributes Block attributes.
		 * @param string|null $content    Optional block content.
		 * @param WP_Block    $block      Parsed block instance, used to find mapping.
		 *
		 * @return string Block output or empty string on failure.
		 */
		public function render_block( $attributes = array(), $content = '', $block = null ) {

			// Ensure a valid block is provided and mapped to a shortcode.
			if ( empty( $block ) || empty( $block->name ) || empty( $this->blocks[ $block->name ] ) ) {
				return '';
			}

			$shortcode  = $this->blocks[ $block->name ];
			$shortcodes = bbpress()->shortcodes;

			// Bail if the shortcode callable is missing.
			if ( empty( $shortcodes ) || empty( $shortcodes->codes[ $shortcode ] ) ) {
				return '';
			}

			$callback = $shortcodes->codes[ $shortcode ];

			// Invoke the shortcode callback directly for server-side rendering.
			return call_user_func( $callback, $attributes, $content );
		}

		/**
		 * Register custom "Forums" block category.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 *
		 * @param array                   $categories Array of block categories.
		 * @param WP_Block_Editor_Context $context Block editor context.
		 *
		 * @return array Modified categories.
		 */
		public function register_block_category( $categories, $context ) {

			// Custom bbPress category.
			$new = array(
				array(
					'slug'  => 'bbpress',
					'title' => esc_html__( 'Forums', 'bbpress' ),
					'icon'  => 'buddicons-bbpress-logo',
				),
			);

			// Merge our category with the existing categories.
			return array_merge( $categories, $new );
		}

		/**
		 * Get data for localizing to block scripts.
		 *
		 * @since 2.7.0 bbPress (r7382)
		 *
		 * @param string $data Data key to retrieve.
		 * @return array|null Localized data or null when unavailable.
		 */
		public static function get_localize_script_data( $data = '' ) {
			switch ( $data ) {
				case 'forums':
					$forums = get_pages(
						array(
							'post_type'   => bbp_get_forum_post_type(),
							'numberposts' => -1,
							'post_status' => array(
								'publish',
								'private',
							),
						)
					);

					$return = array(
						array(
							'value' => 0,
							'label' => __( 'Select a Forum', 'bbpress' ),
						),
					);

					foreach ( $forums as $forum ) {
						$return[] = array(
							'value' => (int) $forum->ID,
							'label' => $forum->post_title,
						);
					}

					return $return;

				case 'views':
					$views  = bbp_get_views();
					$return = array(
						array(
							'value' => '',
							'label' => __( 'Select a View', 'bbpress' ),
						),
					);

					if ( ! empty( $views ) ) {
						foreach ( $views as $view_id => $view ) {
							$return[] = array(
								'value' => $view_id,
								'label' => isset( $view['title'] )
									? $view['title']
									: ucwords( str_replace( '-', ' ', $view_id ) ),
							);
						}
					}

					return $return;

				case 'topic_tags':
					$terms = get_terms(
						array(
							'taxonomy'   => bbp_get_topic_tag_tax_id(),
							'hide_empty' => false,
						)
					);

					$return = array(
						array(
							'value' => 0,
							'label' => __( 'Select a Topic Tag', 'bbpress' ),
						),
					);

					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						foreach ( $terms as $term ) {
							$return[] = array(
								'value' => (int) $term->term_id,
								'label' => $term->name,
							);
						}
					}

					return $return;

				default:
					return null;
			}
		}
	}
endif;
