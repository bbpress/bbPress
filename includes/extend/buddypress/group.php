<?php

/**
 * bbPress BuddyPress Group Extension Class
 *
 * @package bbPress
 * @subpackage BuddyPress
 * @todo maybe move to BuddyPress Forums once bbPress 1.1 can be removed
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BBP_Forums_Group_Extension' ) && class_exists( 'BP_Group_Extension' ) ) :
/**
 * Loads Group Extension for Forums Component
 *
 * @since bbPress (r3552)
 *
 * @package bbPress
 * @subpackage BuddyPress
 * @todo Everything
 */
class BBP_Forums_Group_Extension extends BP_Group_Extension {

	/**
	 * Setup bbPress group extension variables
	 *
	 * @since bbPress (r3552)
	 */
	public function __construct() {

		// Name and slug
		$this->name          = __( 'Forum', 'bbpress' );
		$this->nav_item_name = __( 'Forum', 'bbpress' );
		$this->slug          = 'forums';
		$this->topic_slug    = 'topic';
		$this->reply_slug    = 'reply';

		// Forum component is visible
		$this->visibility = 'public';

		// Set positions towards end
		$this->create_step_position = 15;
		$this->nav_item_position    = 10;

		// Allow create step and show in nav
		$this->enable_create_step   = true;
		$this->enable_nav_item      = true;
		$this->enable_edit_item     = true;

		// I forget what these do
		$this->display_hook         = 'bp_template_content';
		$this->template_file        = 'groups/single/plugins';

		// Add handlers to bp_actions
		add_action( 'bp_actions', 'bbp_new_forum_handler'  );
		add_action( 'bp_actions', 'bbp_new_topic_handler'  );
		add_action( 'bp_actions', 'bbp_new_reply_handler'  );
		add_action( 'bp_actions', 'bbp_edit_forum_handler' );
		add_action( 'bp_actions', 'bbp_edit_topic_handler' );
		add_action( 'bp_actions', 'bbp_edit_reply_handler' );

		// Tweak the redirect field
		add_filter( 'bbp_new_topic_redirect_to', array( $this, 'new_topic_redirect_to' ), 10, 3 );
		add_filter( 'bbp_new_reply_redirect_to', array( $this, 'new_reply_redirect_to' ), 10, 3 );
	}

	/**
	 * The primary display function for group forums
	 */
	public function display() {

		// Prevent Topic Parent from appearing
		add_action( 'bbp_theme_before_topic_form_forum', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_topic_form_forum',  array( $this, 'topic_parent' ) );

		// Prevent Forum Parent from appearing
		add_action( 'bbp_theme_before_forum_form_parent', array( $this, 'ob_start'     ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'ob_end_clean' ) );
		add_action( 'bbp_theme_after_forum_form_parent',  array( $this, 'forum_parent' ) );

		// Hide breadcrumb
		add_filter( 'bbp_no_breadcrumb', '__return_true' );

		$this->display_forums( 0 );
	}

	/** Edit ******************************************************************/

	/**
	 * Show forums and new forum form when editing a group
	 *
	 * @since bbPress (r3563)
	 * @uses bbp_get_template_part()
	 */
	public function edit_screen() {

		$checked = bp_get_new_group_enable_forum() || bp_group_is_forum_enabled( bp_get_group_id() ); ?>

		<h4><?php _e( 'Enable Group Forum', 'bbpress' ); ?></h4>

		<p><?php _e( 'Create a discussion forum to allow members of this group to communicate in a structured, bulletin-board style fashion.', 'bbpress' ); ?></p>

		<div class="checkbox">
			<label><input type="checkbox" name="bbp-edit-group-forum" id="bbp-edit-group-forum" value="1"<?php checked( $checked ); ?> /> <?php _e( 'Yes. I want this group to have a forum.', 'bbpress' ); ?></label>
		</div>

		<p class="description"><?php _e( 'Saying no will not delete existing forum content.', 'bbpress' ); ?></p>

		<input type="submit" value="<?php esc_attr_e( 'Save Settings', 'bbpress' ); ?>" />

		<?php

		// Verify intent
		wp_nonce_field( 'groups_edit_save_' . $this->slug );
	}

	/**
	 * Save the Group Forum data on edit
	 *
	 * @since bbPress (r3465)
	 * @uses bbp_new_forum_handler() To check for forum creation
	 * @uses bbp_edit_forum_handler() To check for forum edit
	 */
	public function edit_screen_save() {

		// Bail if not a POST action
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return;

		check_admin_referer( 'groups_edit_save_' . $this->slug );

		$edit_forum   = !empty( $_POST['bbp-edit-group-forum'] ) ? true : false;
		$forum_id     = 0;
		$group_id     = bp_get_current_group_id();
		$forum_ids    = bbp_get_group_forum_ids( $group_id );
		if ( !empty( $forum_ids ) )
			$forum_id = (int) is_array( $forum_ids ) ? $forum_ids[0] : $forum_ids;

		// Update the group forum setting
		$group               = new BP_Groups_Group( $group_id );
		$group->enable_forum = $edit_forum;
		$group->save();

		// Redirect after save
		bp_core_redirect( trailingslashit( bp_get_group_permalink( buddypress()->groups->current_group ) . '/admin/' . $this->slug ) );
	}

	/** Create ****************************************************************/

	/**
	 * Show forums and new forum form when creating a group
	 *
	 * @since bbPress (r3465)
	 */
	public function create_screen() {

		// Bail if not looking at this screen
		if ( !bp_is_group_creation_step( $this->slug ) )
			return false;

		$checked = bp_get_new_group_enable_forum() || groups_get_groupmeta( bp_get_new_group_id(), 'forum_id' ); ?>

		<h4><?php _e( 'Group Forum', 'bbpress' ); ?></h4>

		<p><?php _e( 'Create a discussion forum to allow members of this group to communicate in a structured, bulletin-board style fashion.', 'bbpress' ); ?></p>

		<div class="checkbox">
			<label><input type="checkbox" name="bbp-create-group-forum" id="bbp-create-group-forum" value="1"<?php checked( $checked ); ?> /> <?php _e( 'Yes. I want this group to have a forum.', 'bbpress' ); ?></label>
		</div>

		<?php

		// Verify intent
		wp_nonce_field( 'groups_create_save_' . $this->slug );
	}

	/**
	 * Save the Group Forum data on create
	 *
	 * @since bbPress (r3465)
	 */
	public function create_screen_save() {

		check_admin_referer( 'groups_create_save_' . $this->slug );

		$create_forum = !empty( $_POST['bbp-create-group-forum'] ) ? true : false;
		$forum_id     = 0;
		$forum_ids    = bbp_get_group_forum_ids( bp_get_new_group_id() );
		if ( !empty( $forum_ids ) )
			$forum_id = (int) is_array( $forum_ids ) ? $forum_ids[0] : $forum_ids;

		// Create a forum, or not
		switch ( $create_forum ) {
			case true  :

				// Bail if initial content was already created
				if ( !empty( $forum_id ) )
					return;

				// Set the default forum status
				switch ( bp_get_new_group_status() ) {
					case 'hidden'  :
						$status = bbp_get_hidden_status_id();
						break;
					case 'private' :
						$status = bbp_get_private_status_id();
						break;
					case 'public'  :
					default        :
						$status = bbp_get_public_status_id();
						break;
				}

				// Create the initial forum
				$forum_id = bbp_insert_forum( array(
					'post_parent'  => bbp_get_group_forums_root_id(),
					'post_title'   => bp_get_new_group_name(),
					'post_content' => bp_get_new_group_description(),
					'post_status'  => $status
				) );

				// Run the BP-specific functions for new groups
				$this->new_forum( array( 'forum_id' => $forum_id ) );

				// Update forum active
				groups_update_groupmeta( bp_get_new_group_id(), '_bbp_forum_enabled_' . $forum_id, true );

				break;
			case false :

				// Forum was created but is now being undone
				if ( !empty( $forum_id ) ) {
					wp_delete_post( $forum_id, true );
					groups_delete_groupmeta( bp_get_new_group_id(), 'forum_id' );
					groups_delete_groupmeta( bp_get_new_group_id(), '_bbp_forum_enabled_' . $forum_id );
				}

				break;
		}
	}

	/**
	 * Used to start an output buffer
	 */
	public function ob_start() {
		ob_start();
	}

	/**
	 * Used to end an output buffer
	 */
	public function ob_end_clean() {
		ob_end_clean();
	}

	/**
	 * Creating a group forum or category (including root for group)
	 *
	 * @since bbPress (r3653)
	 * @param type $forum_args
	 * @uses bbp_get_forum_id()
	 * @uses bp_get_current_group_id()
	 * @uses bbp_add_forum_id_to_group()
	 * @uses bbp_add_group_id_to_forum()
	 * @return if no forum_id is available
	 */
	public function new_forum( $forum_args = array() ) {

		// Bail if no forum_id was passed
		if ( empty( $forum_args['forum_id'] ) )
			return;

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_args['forum_id'] );
		$group_id = bp_get_current_group_id();

		bbp_add_forum_id_to_group( $group_id, $forum_id );
		bbp_add_group_id_to_forum( $forum_id, $group_id );
	}

	/**
	 * Removing a group forum or category (including root for group)
	 *
	 * @since bbPress (r3653)
	 * @param type $forum_args
	 * @uses bbp_get_forum_id()
	 * @uses bp_get_current_group_id()
	 * @uses bbp_add_forum_id_to_group()
	 * @uses bbp_add_group_id_to_forum()
	 * @return if no forum_id is available
	 */
	public function remove_forum( $forum_args = array() ) {

		// Bail if no forum_id was passed
		if ( empty( $forum_args['forum_id'] ) )
			return;

		// Validate forum_id
		$forum_id = bbp_get_forum_id( $forum_args['forum_id'] );
		$group_id = bp_get_current_group_id();

		bbp_remove_forum_id_from_group( $group_id, $forum_id );
		bbp_remove_group_id_from_forum( $forum_id, $group_id );
	}

	/** Display Methods *******************************************************/

	/**
	 * Output the forums for a group in the edit screens
	 *
	 * @since bbPress (r3653)
	 * @uses bp_get_current_group_id()
	 * @uses bbp_get_group_forum_ids()
	 * @uses bbp_has_forums()
	 * @uses bbp_get_template_part()
	 */
	public function display_forums( $offset = 0 ) {
		$bbp = bbpress();

		// Forum data
		$forum_ids  = bbp_get_group_forum_ids( bp_get_current_group_id() );
		$forum_args = array( 'post__in' => $forum_ids, 'post_parent' => null );

		// Unset global queries
		$bbp->forum_query = new stdClass;
		$bbp->topic_query = new stdClass;
		$bbp->reply_query = new stdClass;

		// Unset global ID's
		$bbp->current_forum_id     = 0;
		$bbp->current_topic_id     = 0;
		$bbp->current_reply_id     = 0;
		$bbp->current_topic_tag_id = 0;

		// Reset the post data
		wp_reset_postdata();

		// Allow admins special views
		$post_status = array( bbp_get_closed_status_id(), bbp_get_public_status_id() );
		if ( is_super_admin() || current_user_can( 'moderate' ) || bp_is_item_admin() || bp_is_item_mod() )
			$post_status = array_merge( $post_status, array( bbp_get_spam_status_id(), bbp_get_trash_status_id() ) ); ?>

		<div id="bbpress-forums">

			<?php

			// Looking at the group forum root
			if ( !bp_action_variable( $offset ) ) :

				// Query forums and show them if they exist
				if ( !empty( $forum_ids ) && bbp_has_forums( $forum_args ) ) :

					// Only one forum found
					if ( 1 == $bbp->forum_query->post_count ) :

						// Get forum data
						$forum_slug = bp_action_variable( $offset );
						$forum_args = array( 'name' => $forum_slug, 'post_type' => bbp_get_forum_post_type() );
						$forums     = get_posts( $forum_args );

						bbp_the_forum();

						// Forum exists
						if ( !empty( $forums ) ) :
							$forum = $forums[0];

							// Set up forum data
							$forum_id = bbpress()->current_forum_id = $forum->ID;
							bbp_set_query_name( 'bbp_single_forum' ); ?>

							<h3><?php bbp_forum_title(); ?></h3>

							<?php bbp_get_template_part( 'content', 'single-forum' ); ?>

						<?php else : ?>

							<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php endif;

					// More than 1 forum found or group forum admin screen
					elseif ( 1 < $bbp->forum_query->post_count ) : ?>

						<h3><?php _e( 'Forums', 'bbpress' ); ?></h3>

						<?php bbp_get_template_part( 'loop', 'forums' ); ?>

						<h3><?php _e( 'Topics', 'bbpress' ); ?></h3>

						<?php if ( bbp_has_topics( array( 'post_parent__in' => $forum_ids ) ) ) : ?>

							<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

							<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

							<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php else : ?>

							<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

							<?php bbp_get_template_part( 'form',       'topic'     ); ?>

						<?php endif;

					// No forums found
					else : ?>

						<div id="message" class="info">
							<p><?php _e( 'This group does not currently have any forums.', 'bbpress' ); ?></p>
						</div>

					<?php endif;

				// No forums found
				else : ?>

					<div id="message" class="info">
						<p><?php _e( 'This group does not currently have any forums.', 'bbpress' ); ?></p>
					</div>

				<?php endif;

			// Single forum
			elseif ( ( bp_action_variable( $offset ) != $this->slug ) && ( bp_action_variable( $offset ) != $this->topic_slug ) && ( bp_action_variable( $offset ) != $this->reply_slug ) ) :

				// Get forum data
				$forum_slug = bp_action_variable( $offset );
				$forum_args = array( 'name' => $forum_slug, 'post_type' => bbp_get_forum_post_type() );
				$forums     = get_posts( $forum_args );

				// Forum exists
				if ( !empty( $forums ) ) :
					$forum = $forums[0];

					// Set up forum data
					$forum_id = bbpress()->current_forum_id = $forum->ID;

					// Reset necessary forum_query attributes for forums loop to function
					$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
					$bbp->forum_query->in_the_loop             = true;
					$bbp->forum_query->post                    = get_post( $forum_id );

					// Forum edit
					if ( bp_action_variable( $offset + 1 ) == $bbp->edit_id ) :
						global $wp_query, $post;

						$wp_query->bbp_is_edit       = true;
						$wp_query->bbp_is_forum_edit = true;
						$post                        = $forum;

						bbp_set_query_name( 'bbp_forum_form' );

						bbp_get_template_part( 'form', 'forum' );

					else :
						bbp_set_query_name( 'bbp_single_forum' ); ?>

						<h3><?php bbp_forum_title(); ?></h3>

						<?php bbp_get_template_part( 'content', 'single-forum' );

					endif;

				else :
					bbp_get_template_part( 'feedback', 'no-topics' );
					bbp_get_template_part( 'form',     'topic'     );

				endif;

			// Single topic
			elseif ( bp_action_variable( $offset ) == $this->topic_slug ) :

				// Get topic data
				$topic_slug = bp_action_variable( $offset + 1 );
				$topic_args = array( 'name' => $topic_slug, 'post_type' => bbp_get_topic_post_type(), 'post_status' => $post_status );
				$topics     = get_posts( $topic_args );

				// Topic exists
				if ( !empty( $topics ) ) :
					$topic = $topics[0];

					// Set up topic data
					$topic_id = bbpress()->current_topic_id = $topic->ID;
					$forum_id = bbp_get_topic_forum_id( $topic_id );

					// Reset necessary forum_query attributes for topics loop to function
					$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
					$bbp->forum_query->in_the_loop             = true;
					$bbp->forum_query->post                    = get_post( $forum_id );

					// Reset necessary topic_query attributes for topics loop to function
					$bbp->topic_query->query_vars['post_type'] = bbp_get_topic_post_type();
					$bbp->topic_query->in_the_loop             = true;
					$bbp->topic_query->post                    = $topic;

					// Topic edit
					if ( bp_action_variable( $offset + 2 ) == $bbp->edit_id ) :
						global $wp_query, $post;
						$wp_query->bbp_is_edit       = true;
						$wp_query->bbp_is_topic_edit = true;
						$post                        = $topic;

						// Merge
						if ( !empty( $_GET['action'] ) && 'merge' == $_GET['action'] ) :
							bbp_set_query_name( 'bbp_topic_merge' );

							bbp_get_template_part( 'form', 'topic-merge' );

						// Split
						elseif ( !empty( $_GET['action'] ) && 'split' == $_GET['action'] ) :
							bbp_set_query_name( 'bbp_topic_split' );

							bbp_get_template_part( 'form', 'topic-split' );

						// Edit
						else :
							bbp_set_query_name( 'bbp_topic_form' );
							bbp_get_template_part( 'form', 'topic' );

						endif;

					// Single Topic
					else:

						bbp_set_query_name( 'bbp_single_topic' ); ?>

						<h3><?php bbp_topic_title(); ?></h3>

						<?php bbp_get_template_part( 'content', 'single-topic' );

					endif;

				// No Topics
				else :
					bbp_get_template_part( 'feedback', 'no-topics'   );
					bbp_get_template_part( 'form',     'topic'       );

				endif;

			// Single reply
			elseif ( bp_action_variable( $offset ) == $this->reply_slug ) :

				// Get reply data
				$reply_slug = bp_action_variable( $offset + 1 );
				$reply_args = array( 'name' => $reply_slug, 'post_type' => bbp_get_reply_post_type() );
				$replies    = get_posts( $reply_args );

				if ( empty( $replies ) )
					return;

				// Get the first reply
				$reply = $replies[0];

				// Set up reply data
				$reply_id = bbpress()->current_reply_id = $reply->ID;
				$topic_id = bbp_get_reply_topic_id( $reply_id );
				$forum_id = bbp_get_reply_forum_id( $reply_id );

				// Reset necessary forum_query attributes for reply to function
				$bbp->forum_query->query_vars['post_type'] = bbp_get_forum_post_type();
				$bbp->forum_query->in_the_loop             = true;
				$bbp->forum_query->post                    = get_post( $forum_id );

				// Reset necessary topic_query attributes for reply to function
				$bbp->topic_query->query_vars['post_type'] = bbp_get_topic_post_type();
				$bbp->topic_query->in_the_loop             = true;
				$bbp->topic_query->post                    = get_post( $topic_id );

				// Reset necessary reply_query attributes for reply to function
				$bbp->reply_query->query_vars['post_type'] = bbp_get_reply_post_type();
				$bbp->reply_query->in_the_loop             = true;
				$bbp->reply_query->post                    = $reply;

				if ( bp_action_variable( $offset + 2 ) == $bbp->edit_id ) :
					global $wp_query, $post;

					$wp_query->bbp_is_edit       = true;
					$wp_query->bbp_is_reply_edit = true;
					$post                        = $reply;

					bbp_set_query_name( 'bbp_reply_form' );
					bbp_get_template_part( 'form', 'reply' );

				endif;

			endif; ?>

		</div>

		<?php
	}

	/** Redirect Helpers ******************************************************/

	/**
	 * Redirect to the group forum screen
	 *
	 * @since bbPress (r3653)
	 * @param str $redirect_url
	 * @param str $redirect_to
	 */
	public function new_topic_redirect_to( $redirect_url = '', $redirect_to = '', $topic_id = 0 ) {
		if ( bp_is_group() ) {
			$topic        = bbp_get_topic( $topic_id );
			$topic_hash   = '#post-' . $topic_id;
			$redirect_url = trailingslashit( bp_get_group_permalink( groups_get_current_group() ) ) . trailingslashit( $this->slug ) . trailingslashit( $this->topic_slug ) . trailingslashit( $topic->post_name ) . $topic_hash;
		}

		return $redirect_url;
	}

	/**
	 * Redirect to the group forum screen
	 *
	 * @since bbPress (r3653)
	 */
	public function new_reply_redirect_to( $redirect_url = '', $redirect_to = '', $reply_id = 0 ) {
		global $wp_rewrite;

		if ( bp_is_group() ) {
			$topic_id       = bbp_get_reply_topic_id( $reply_id );
			$topic          = bbp_get_topic( $topic_id );
			$reply_position = bbp_get_reply_position( $reply_id, $topic_id );
			$reply_page     = ceil( (int) $reply_position / (int) bbp_get_replies_per_page() );
			$reply_hash     = '#post-' . $reply_id;
			$topic_url      = trailingslashit( bp_get_group_permalink( groups_get_current_group() ) ) . trailingslashit( $this->slug ) . trailingslashit( $this->topic_slug ) . trailingslashit( $topic->post_name );

			// Don't include pagination if on first page
			if ( 1 >= $reply_page ) {
				$redirect_url = trailingslashit( $topic_url ) . $reply_hash;

			// Include pagination
			} else {
				$redirect_url = trailingslashit( $topic_url ) . trailingslashit( $wp_rewrite->pagination_base ) . trailingslashit( $reply_page ) . $reply_hash;
			}

			// Add topic view query arg back to end if it is set
			if ( bbp_get_view_all() ) {
				$redirect_url = bbp_add_view_all( $redirect_url );
			}
		}

		return $redirect_url;
	}

	/**
	 * Redirect to the group admin forum edit screen
	 *
	 * @since bbPress (r3653)
	 * @uses groups_get_current_group()
	 * @uses bp_is_group_admin_screen()
	 * @uses trailingslashit()
	 * @uses bp_get_root_domain()
	 * @uses bp_get_groups_root_slug()
	 */
	public function edit_redirect_to( $redirect_url = '' ) {

		// Get the current group, if there is one
		$group = groups_get_current_group();

		// If this is a group of any kind, empty out the redirect URL
		if ( bp_is_group_admin_screen( $this->slug ) )
			$redirect_url = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $group->slug . '/admin/' . $this->slug );

		return $redirect_url;
	}

	/** Form Helpers **********************************************************/

	public function forum_parent() {
	?>

		<input type="hidden" name="bbp_forum_parent_id" id="bbp_forum_parent_id" value="<?php bbp_group_forums_root_id(); ?>" />

	<?php
	}

	public function topic_parent() {

		$forum_ids = bbp_get_group_forum_ids( bp_get_current_group_id() ); ?>

		<p>
			<label for="bbp_forum_id"><?php _e( 'Forum:', 'bbpress' ); ?></label><br />
			<?php bbp_dropdown( array( 'include' => $forum_ids, 'selected' => bbp_get_form_topic_forum() ) ); ?>
		</p>

	<?php
	}
}
endif;
