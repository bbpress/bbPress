<?php

/**
 * Main bbPress Genesis Extender Class
 *
 * @package bbPress
 * @subpackage Genesis
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Genesis' ) ) :
/**
 * Loads Genesis extension
 *
 * @since bbPress (r3485)
 *
 * @package bbPress
 * @subpackage Genesis
 */
class BBP_Genesis {

	/** Variables *************************************************************/

	/**
	 * @var bool Should Genesis use the full width template layout
	 */
	public $bbp_genesis_width = 'full-width-content';

	/** Functions *************************************************************/

	/**
	 * The main bbPress Genesis loader
	 *
	 * @since bbPress (r3485)
	 *
	 * @uses BBP_Genesis::setup_actions()
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Setup the Genesis actions
	 *
	 * @since bbPress (r3485)
	 * @access private
	 *
	 * @uses add_action() To add 'the_content' back
	 */
	private function setup_actions() {

		/**
		 * We hook into 'genesis_before' because t is the most reliable hook
		 * available to bbPress in the Genesis page load process.
		 */
		add_action( 'genesis_before',                     array( $this, 'genesis_post_actions'     ) );

		// Force Genesis into full-width-content mode
		add_filter( 'genesis_pre_get_option_site_layout', array( $this, 'force_full_content_width' ) );
	}

	/**
	 * Tweak problematic Genesis post actions
	 *
	 * @since bbPress (r3485)
	 * @access private
	 *
	 * @uses remove_action() To remove various Genesis actions
	 * @uses add_filter() To add a filter for fuss-width mode
	 * @uses add_action() To add 'the_content' back
	 */
	public function genesis_post_actions() {

		/**
		 * If the current theme is a child theme of Genesis that also includes
		 * the template files bbPress needs, we can leave things how they are.
		 */		
		if ( is_bbpress() ) {

			/** Remove Actions ************************************************/

			// Remove genesis breadcrumbs
			remove_action( 'genesis_before_loop', 'genesis_do_breadcrumbs' );

			/**
			 * Remove post info & meta
			 * 
			 * If you moved the info/meta from their default locations, you are
			 * on your own.
			 */
			remove_action( 'genesis_before_post_content', 'genesis_post_info' );
			remove_action( 'genesis_after_post_content',  'genesis_post_meta' );

			/**
			 * Remove Genesis post image and content
			 *
			 * bbPress heavily relies on the_content() so if Genesis is
			 * modifying it unexpectedly, we need to un-unexpect it.
			 */
			remove_action( 'genesis_post_content', 'genesis_do_post_image'   );
			remove_action( 'genesis_post_content', 'genesis_do_post_content' );

			/**
			 * Remove authorbox
			 * 
			 * In some odd cases the Genesis authorbox could appear
			 */
			remove_action( 'genesis_after_post', 'genesis_do_author_box_single' );

			/** Add Actions ***************************************************/

			// Re add 'the_content' back onto 'genesis_post_content'
			add_action( 'genesis_post_content', 'the_content' );
		}
	}
	
	/**
	 * bbPress will display itself in Genesis in full-width mode by default
	 *
	 * Available default layouts (assuming theme supports them):
	 *
	 * full-width-content
	 * content-sidebar
	 * sidebar-content
	 * content-sidebar-sidebar
	 * sidebar-sidebar-content
	 * sidebar-content-sidebar
	 *
	 * @since bbPress (r3485)
	 * @param type $full_width
	 * @return bool To do, or not to do... full-width
	 */
	public function force_full_content_width( $bbp_genesis_width = 'full-width-content' ) {

		// Only override the content width while in bbPress
		if ( is_bbpress() ) {
			$bbp_genesis_width = $this->bbp_genesis_width;
		}

		// Allow the override to be overridden - a dream within a dream
		return apply_filters( 'bbp_genesis_force_full_content_width', $bbp_genesis_width );
	}
}
endif;

/**
 * Loads Genesis helper inside bbPress global class
 *
 * @since bbPress (r3485)
 *
 * @global bbPress $bbp
 * @return If Genesis is not the active theme
 */
function bbp_setup_genesis() {
	global $bbp;

	// Bail if no genesis
	if ( basename( TEMPLATEPATH ) !== 'genesis' ) return;

	// Instantiate Genesis for bbPress
	$bbp->extend->genesis = new BBP_Genesis();
}

?>
