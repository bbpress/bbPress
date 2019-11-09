<?php

/**
 * bbPress Admin Metaboxes
 *
 * @package bbPress
 * @subpackage Administration
 */

/** Dashboard *****************************************************************/

/**
 * Filter the Dashboard "at a glance" items and append bbPress elements to it.
 *
 * @since 2.6.0 bbPress (r5268)
 *
 * @param array $elements
 * @return array
 */
function bbp_filter_dashboard_glance_items( $elements = array() ) {

	// Bail if user cannot spectate
	if ( ! current_user_can( 'spectate' ) ) {
		return $elements;
	}

	// Get the statistics
	$r = bbp_get_statistics( array(
		'count_pending_topics'  => false,
		'count_private_topics'  => false,
		'count_spammed_topics'  => false,
		'count_trashed_topics'  => false,
		'count_pending_replies' => false,
		'count_private_replies' => false,
		'count_spammed_replies' => false,
		'count_trashed_replies' => false,
		'count_empty_tags'      => false
	) );

	// Users
	if ( isset( $r['user_count'] ) ) {
		$link       = admin_url( 'users.php' );
		$text       = sprintf( _n( '%s User', '%s Users', $r['user_count_int'], 'bbpress' ), $r['user_count'] );
		$elements[] = current_user_can( 'edit_users' )
			? '<a href="' . esc_url( $link ) . '" class="bbp-glance-users">' . esc_html( $text ) . '</a>'
			: esc_html( $text );
	}

	// Forums
	if ( isset( $r['forum_count'] ) ) {
		$link       = add_query_arg( array( 'post_type' => bbp_get_forum_post_type() ), admin_url( 'edit.php' ) );
		$text       = sprintf( _n( '%s Forum', '%s Forums', $r['forum_count_int'], 'bbpress' ), $r['forum_count'] );
		$elements[] = current_user_can( 'publish_forums' )
			? '<a href="' . esc_url( $link ) . '" class="bbp-glance-forums">' . esc_html( $text ) . '</a>'
			: esc_html( $text );
	}

	// Topics
	if ( isset( $r['topic_count'] ) ) {
		$link       = add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) );
		$text       = sprintf( _n( '%s Topic', '%s Topics', $r['topic_count_int'], 'bbpress' ), $r['topic_count'] );
		$elements[] = current_user_can( 'publish_topics' )
			? '<a href="' . esc_url( $link ) . '" class="bbp-glance-topics">' . esc_html( $text ) . '</a>'
			: esc_html( $text );
	}

	// Replies
	if ( isset( $r['reply_count'] ) ) {
		$link       = add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), admin_url( 'edit.php' ) );
		$text       = sprintf( _n( '%s Reply', '%s Replies', $r['reply_count_int'], 'bbpress' ), $r['reply_count'] );
		$elements[] = current_user_can( 'publish_replies' )
			? '<a href="' . esc_url( $link ) . '" class="bbp-glance-replies">' . esc_html( $text ) . '</a>'
			: esc_html( $text );
	}

	// Topic Tags
	if ( bbp_allow_topic_tags() && isset( $r['topic_tag_count'] ) ) {
		$link       = add_query_arg( array( 'taxonomy' => bbp_get_topic_tag_tax_id(), 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit-tags.php' ) );
		$text       = sprintf( _n( '%s Topic Tag', '%s Topic Tags', $r['topic_tag_count_int'], 'bbpress' ), $r['topic_tag_count'] );
		$elements[] = current_user_can( 'manage_topic_tags' )
			? '<a href="' . esc_url( $link ) . '" class="bbp-glance-topic-tags">' . esc_html( $text ) . '</a>'
			: esc_html( $text );
	}

	// Filter & return
	return apply_filters( 'bbp_dashboard_at_a_glance', $elements, $r );
}

/**
 * bbPress Dashboard Right Now Widget
 *
 * Adds a dashboard widget with forum statistics
 *
 * @since 2.0.0 bbPress (r2770)
 *
 * @deprecated 2.6.0 bbPress (r5268)
 */
function bbp_dashboard_widget_right_now() {

	// Get the statistics
	$r = bbp_get_statistics(); ?>

	<div class="table table_content">

		<p class="sub"><?php esc_html_e( 'Discussion', 'bbpress' ); ?></p>

		<table>

			<tr class="first">

				<?php
					$num  = $r['forum_count'];
					$text = _n( 'Forum', 'Forums', $r['forum_count_int'], 'bbpress' );
					if ( current_user_can( 'publish_forums' ) ) {
						$link = add_query_arg( array( 'post_type' => bbp_get_forum_post_type() ), admin_url( 'edit.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-forums"><?php echo $num; ?></td>
				<td class="t forums"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $r['topic_count'];
					$text = _n( 'Topic', 'Topics', $r['topic_count_int'], 'bbpress' );
					if ( current_user_can( 'publish_topics' ) ) {
						$link = add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-topics"><?php echo $num; ?></td>
				<td class="t topics"><?php echo $text; ?></td>

			</tr>

			<tr>

				<?php
					$num  = $r['reply_count'];
					$text = _n( 'Reply', 'Replies', $r['reply_count_int'], 'bbpress' );
					if ( current_user_can( 'publish_replies' ) ) {
						$link = add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), admin_url( 'edit.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
					}
				?>

				<td class="first b b-replies"><?php echo $num; ?></td>
				<td class="t replies"><?php echo $text; ?></td>

			</tr>

			<?php if ( bbp_allow_topic_tags() ) : ?>

				<tr>

					<?php
						$num  = $r['topic_tag_count'];
						$text = _n( 'Topic Tag', 'Topic Tags', $r['topic_tag_count_int'], 'bbpress' );
						if ( current_user_can( 'manage_topic_tags' ) ) {
							$link = add_query_arg( array( 'taxonomy' => bbp_get_topic_tag_tax_id(), 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit-tags.php' ) );
							$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
							$text = '<a href="' . esc_url( $link ) . '">' . $text . '</a>';
						}
					?>

					<td class="first b b-topic_tags"><span class="total-count"><?php echo $num; ?></span></td>
					<td class="t topic_tags"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php do_action( 'bbp_dashboard_widget_right_now_content_table_end' ); ?>

		</table>

	</div>


	<div class="table table_discussion">

		<p class="sub"><?php esc_html_e( 'Users &amp; Moderation', 'bbpress' ); ?></p>

		<table>

			<tr class="first">

				<?php
					$num  = $r['user_count'];
					$text = _n( 'User', 'Users', $r['user_count_int'], 'bbpress' );
					if ( current_user_can( 'edit_users' ) ) {
						$link = admin_url( 'users.php' );
						$num  = '<a href="' . $link . '">' . $num  . '</a>';
						$text = '<a href="' . $link . '">' . $text . '</a>';
					}
				?>

				<td class="b b-users"><span class="total-count"><?php echo $num; ?></span></td>
				<td class="last t users"><?php echo $text; ?></td>

			</tr>

			<?php if ( isset( $r['topic_count_hidden'] ) ) : ?>

				<tr>

					<?php
						$num  = $r['topic_count_hidden'];
						$text = _n( 'Hidden Topic', 'Hidden Topics', $r['topic_count_hidden_int'], 'bbpress' );
						$link = add_query_arg( array( 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit.php' ) );
						if ( '0' !== $num ) {
							$link = add_query_arg( array( 'post_status' => bbp_get_spam_status_id() ), $link );
						}
						$num  = '<a href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_topic_title'] ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_topic_title'] ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-topics"><?php echo $num; ?></td>
					<td class="last t hidden-replies"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php if ( isset( $r['reply_count_hidden'] ) ) : ?>

				<tr>

					<?php
						$num  = $r['reply_count_hidden'];
						$text = _n( 'Hidden Reply', 'Hidden Replies', $r['reply_count_hidden_int'], 'bbpress' );
						$link = add_query_arg( array( 'post_type' => bbp_get_reply_post_type() ), admin_url( 'edit.php' ) );
						if ( '0' !== $num ) {
							$link = add_query_arg( array( 'post_status' => bbp_get_spam_status_id() ), $link );
						}
						$num  = '<a href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_reply_title'] ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . esc_url( $link ) . '" title="' . esc_attr( $r['hidden_reply_title'] ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-replies"><?php echo $num; ?></td>
					<td class="last t hidden-replies"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php if ( bbp_allow_topic_tags() && isset( $r['empty_topic_tag_count'] ) ) : ?>

				<tr>

					<?php
						$num  = $r['empty_topic_tag_count'];
						$text = _n( 'Empty Topic Tag', 'Empty Topic Tags', $r['empty_topic_tag_count_int'], 'bbpress' );
						$link = add_query_arg( array( 'taxonomy' => bbp_get_topic_tag_tax_id(), 'post_type' => bbp_get_topic_post_type() ), admin_url( 'edit-tags.php' ) );
						$num  = '<a href="' . esc_url( $link ) . '">' . $num  . '</a>';
						$text = '<a class="waiting" href="' . esc_url( $link ) . '">' . $text . '</a>';
					?>

					<td class="b b-hidden-topic-tags"><?php echo $num; ?></td>
					<td class="last t hidden-topic-tags"><?php echo $text; ?></td>

				</tr>

			<?php endif; ?>

			<?php do_action( 'bbp_dashboard_widget_right_now_discussion_table_end' ); ?>

		</table>

	</div>

	<?php do_action( 'bbp_dashboard_widget_right_now_table_end' ); ?>

	<div class="versions">

		<span id="wp-version-message">
			<?php printf( __( 'You are using <span class="b">bbPress %s</span>.', 'bbpress' ), bbp_get_version() ); ?>
		</span>

	</div>

	<br class="clear" />

	<?php

	do_action( 'bbp_dashboard_widget_right_now_end' );
}

/** Forums ********************************************************************/

/**
 * Forum meta-box
 *
 * The meta-box that holds all of the additional forum information
 *
 * @since 2.0.0 bbPress (r2744)
 */
function bbp_forum_metabox( $post ) {

	// Post ID
	$post_parent = bbp_get_global_post_field( 'post_parent', 'raw'  );
	$menu_order  = bbp_get_global_post_field( 'menu_order',  'edit' );

	/** Type ******************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Type:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="bbp_forum_type_select"><?php esc_html_e( 'Type:', 'bbpress' ) ?></label>
		<?php bbp_form_forum_type_dropdown( array( 'forum_id' => $post->ID ) ); ?>
	</p>

	<?php

	/** Status ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Status:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="bbp_forum_status_select"><?php esc_html_e( 'Status:', 'bbpress' ) ?></label>
		<?php bbp_form_forum_status_dropdown( array( 'forum_id' => $post->ID ) ); ?>
	</p>

	<?php

	/** Visibility ************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Visibility:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="bbp_forum_visibility_select"><?php esc_html_e( 'Visibility:', 'bbpress' ) ?></label>
		<?php bbp_form_forum_visibility_dropdown( array( 'forum_id' => $post->ID ) ); ?>
	</p>

	<hr />

	<?php

	/** Parent ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Parent:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Forum Parent', 'bbpress' ); ?></label>
		<?php bbp_dropdown( array(
			'post_type'          => bbp_get_forum_post_type(),
			'selected'           => $post_parent,
			'numberposts'        => -1,
			'orderby'            => 'title',
			'order'              => 'ASC',
			'walker'             => '',
			'exclude'            => $post->ID,

			// Output-related
			'select_id'          => 'parent_id',
			'options_only'       => false,
			'show_none'          => esc_html__( '&mdash; No parent &mdash;', 'bbpress' ),
			'disable_categories' => false,
			'disabled'           => ''
		) ); ?>
	</p>

	<p>
		<strong class="label"><?php esc_html_e( 'Order:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="menu_order"><?php esc_html_e( 'Forum Order', 'bbpress' ); ?></label>
		<input name="menu_order" type="number" step="1" size="4" id="menu_order" value="<?php echo esc_attr( $menu_order ); ?>" />
	</p>

	<input name="ping_status" type="hidden" id="ping_status" value="open" />

	<?php
	wp_nonce_field( 'bbp_forum_metabox_save', 'bbp_forum_metabox' );
	do_action( 'bbp_forum_metabox', $post );
}

/** Topics ********************************************************************/

/**
 * Topic meta-box
 *
 * The meta-box that holds all of the additional topic information
 *
 * @since 2.0.0 bbPress (r2464)
 */
function bbp_topic_metabox( $post ) {

	/** Type ******************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Type:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="bbp_stick_topic"><?php esc_html_e( 'Topic Type', 'bbpress' ); ?></label>
		<?php bbp_form_topic_type_dropdown( array( 'topic_id' => $post->ID ) ); ?>
	</p>

	<?php

	/** Status ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Status:', 'bbpress' ); ?></strong>
		<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ( 'auto-draft' === $post->post_status ) ? 'draft' : $post->post_status ); ?>" />
		<label class="screen-reader-text" for="bbp_open_close_topic"><?php esc_html_e( 'Select whether to open or close the topic.', 'bbpress' ); ?></label>
		<?php bbp_form_topic_status_dropdown( array( 'select_id' => 'post_status', 'topic_id' => $post->ID ) ); ?>
	</p>

	<?php

	/** Parent *****************************************************************/

	?>

	<hr />

	<p>
		<strong class="label"><?php esc_html_e( 'Forum:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Forum', 'bbpress' ); ?></label>
		<?php bbp_dropdown( array(
			'post_type'          => bbp_get_forum_post_type(),
			'selected'           => bbp_get_topic_forum_id( $post->ID ),
			'numberposts'        => -1,
			'orderby'            => 'title',
			'order'              => 'ASC',
			'walker'             => '',
			'exclude'            => '',

			// Output-related
			'select_id'          => 'parent_id',
			'options_only'       => false,
			'show_none'          => esc_html__( '&mdash; No forum &mdash;', 'bbpress' ),
			'disable_categories' => current_user_can( 'edit_forums' ),
			'disabled'           => ''
		) ); ?>
	</p>

	<input name="ping_status" type="hidden" id="ping_status" value="open" />

	<?php
	wp_nonce_field( 'bbp_topic_metabox_save', 'bbp_topic_metabox' );
	do_action( 'bbp_topic_metabox', $post );
}

/** Replies *******************************************************************/

/**
 * Reply meta-box
 *
 * The meta-box that holds all of the additional reply information
 *
 * @since 2.0.0 bbPress (r2464)
 */
function bbp_reply_metabox( $post ) {

	// Get some meta
	$reply_topic_id = bbp_get_reply_topic_id( $post->ID );
	$reply_forum_id = bbp_get_reply_forum_id( $post->ID );
	$topic_forum_id = bbp_get_topic_forum_id( $reply_topic_id );

	/** Status ****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Status:', 'bbpress' ); ?></strong>
		<input type="hidden" name="hidden_post_status" id="hidden_post_status" value="<?php echo esc_attr( ( 'auto-draft' === $post->post_status ) ? 'draft' : $post->post_status ); ?>" />
		<label class="screen-reader-text" for="post_status"><?php esc_html_e( 'Select what status to give the reply.', 'bbpress' ); ?></label>
		<?php bbp_form_reply_status_dropdown( array( 'select_id' => 'post_status', 'reply_id' => $post->ID ) ); ?>
	</p>

	<hr />

	<?php

	/** Forum *****************************************************************/

	// Only allow individual manipulation of reply forum if there is a mismatch
	if ( ( $reply_forum_id !== $topic_forum_id ) && ( current_user_can( 'edit_others_replies' ) || current_user_can( 'moderate', $post->ID ) ) ) : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'Forum:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_forum_id"><?php esc_html_e( 'Forum', 'bbpress' ); ?></label>
			<?php bbp_dropdown( array(
				'post_type'          => bbp_get_forum_post_type(),
				'selected'           => $reply_forum_id,
				'numberposts'        => -1,
				'orderby'            => 'title',
				'order'              => 'ASC',
				'walker'             => '',
				'exclude'            => '',

				// Output-related
				'select_id'          => 'bbp_forum_id',
				'options_only'       => false,
				'show_none'          => esc_html__( '&mdash; No reply &mdash;', 'bbpress' ),
				'disable_categories' => current_user_can( 'edit_forums' ),
				'disabled'           => ''
			) ); ?>
		</p>

	<?php endif;

	/** Topic *****************************************************************/

	?>

	<p>
		<strong class="label"><?php esc_html_e( 'Topic:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="parent_id"><?php esc_html_e( 'Topic', 'bbpress' ); ?></label>
		<input name="parent_id" id="bbp_topic_id" type="text" value="<?php echo esc_attr( $reply_topic_id ); ?>" data-ajax-url="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_suggest_topic' ), admin_url( 'admin-ajax.php', 'relative' ) ), 'bbp_suggest_topic_nonce' ) ); ?>" />
	</p>

	<?php

	/** Reply To **************************************************************/

	// Only show reply-to drop-down when editing an existing reply
	if ( ! empty( $reply_topic_id ) ) : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'Reply To:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_reply_to"><?php esc_html_e( 'Reply To', 'bbpress' ); ?></label>
			<?php bbp_reply_to_dropdown( $post->ID ); ?>
		</p>

	<?php

	endif;

	?>

	<input name="ping_status" type="hidden" id="ping_status" value="open" />

	<?php
	wp_nonce_field( 'bbp_reply_metabox_save', 'bbp_reply_metabox' );
	do_action( 'bbp_reply_metabox', $post );
}

/**
 * Output the topic replies meta-box
 *
 * @since 2.6.0 bbPress (r5886)
 *
 * @param object $topic
 *
 * @return void
 */
function bbp_topic_replies_metabox( $topic = false ) {

	// Bail if no topic to load replies for
	if ( empty( $topic ) ) {
		return;
	}

	// Pull in the list table class
	if ( ! class_exists( 'BBP_Topic_Replies_List_Table' ) ) {
		require_once bbp_admin()->admin_dir . '/classes/class-bbp-topic-replies-list-table.php';
	}

	// Look for pagination value
	$page = isset( $_REQUEST['page'] )
		? (int) $_REQUEST['page']
		: 0;

	// Load up the list table
	$replies_list_table = new BBP_Topic_Replies_List_Table();
	$replies_list_table->prepare_items( $topic->ID ); ?>

	<form id="bbp-topic-replies" method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />

		<?php $replies_list_table->display(); ?>
	</form>

	<?php
}

/** Users *********************************************************************/

/**
 * Anonymous user information meta-box
 *
 * @since 2.0.0 bbPress (r2828)
 *
 * @param WP_Post $post The current post object
 */
function bbp_author_metabox( $post ) {

	// Show extra bits if topic/reply is anonymous
	if ( bbp_is_reply_anonymous( $post->ID ) || bbp_is_topic_anonymous( $post->ID ) ) : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'Name:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_anonymous_name"><?php esc_html_e( 'Name', 'bbpress' ); ?></label>
			<input type="text" id="bbp_anonymous_name" name="bbp_anonymous_name" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bbp_anonymous_name', true ) ); ?>" />
		</p>

		<p>
			<strong class="label"><?php esc_html_e( 'Email:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_anonymous_email"><?php esc_html_e( 'Email', 'bbpress' ); ?></label>
			<input type="text" id="bbp_anonymous_email" name="bbp_anonymous_email" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bbp_anonymous_email', true ) ); ?>" />
		</p>

		<p>
			<strong class="label"><?php esc_html_e( 'Website:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_anonymous_website"><?php esc_html_e( 'Website', 'bbpress' ); ?></label>
			<input type="text" id="bbp_anonymous_website" name="bbp_anonymous_website" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bbp_anonymous_website', true ) ); ?>" />
		</p>

	<?php else : ?>

		<p>
			<strong class="label"><?php esc_html_e( 'ID:', 'bbpress' ); ?></strong>
			<label class="screen-reader-text" for="bbp_author_id"><?php esc_html_e( 'ID', 'bbpress' ); ?></label>
			<input type="text" id="bbp_author_id" name="post_author_override" value="<?php echo esc_attr( bbp_get_global_post_field( 'post_author' ) ); ?>" data-ajax-url="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'bbp_suggest_user' ), admin_url( 'admin-ajax.php', 'relative' ) ), 'bbp_suggest_user_nonce' ) ); ?>" />
		</p>

	<?php endif; ?>

	<p>
		<strong class="label"><?php esc_html_e( 'IP:', 'bbpress' ); ?></strong>
		<label class="screen-reader-text" for="bbp_author_ip_address"><?php esc_html_e( 'IP Address', 'bbpress' ); ?></label>
		<input type="text" id="bbp_author_ip_address" name="bbp_author_ip_address" value="<?php echo esc_attr( get_post_meta( $post->ID, '_bbp_author_ip', true ) ); ?>" disabled="disabled" />
	</p>

	<?php

	do_action( 'bbp_author_metabox', $post );
}

/**
 * Moderator assignment meta-box
 *
 * @since 2.6.0 bbPress (r2828)
 */
function bbp_moderator_assignment_metabox( $post ) {

	// Get nicenames
	$user_ids       = bbp_get_moderator_ids( $post->ID );
	$user_nicenames = bbp_get_user_nicenames_from_ids( $user_ids );
	$moderators     = ! empty( $user_nicenames )
		? implode( ', ', array_map( 'esc_attr', $user_nicenames ) )
		: ''; ?>

	<label class="screen-reader-text" for="bbp_moderators"><?php esc_html_e( 'Moderators', 'bbpress' ); ?></label>
	<input type="text" id="bbp_moderators" name="bbp_moderators" value="<?php echo esc_attr( $moderators ); ?>" />
	<p class="howto"><?php esc_html_e( 'Separate user-names with commas', 'bbpress' ); ?></p>

	<?php

	do_action( 'bbp_moderator_assignment_metabox', $post );
}

/**
 * See who engaged with a topic
 *
 * @since 2.6.0 bbPress (r6333)
 */
function bbp_topic_engagements_metabox( $post ) {

	// Get user IDs
	$user_ids = bbp_get_topic_engagements( $post->ID );

	// Output
	?><p><?php

		// Relationships
		$args = array(
			'include' => $user_ids
		);

		// Users were found
		if ( ! empty( $user_ids ) && bbp_has_users( $args ) ) :
			bbp_metabox_user_links();

		// No users
		else :
			esc_html_e( 'No users have engaged to this topic.', 'bbpress' );
		endif;

	?></p><?php

	do_action( 'bbp_topic_engagements_metabox', $post );
}

/**
 * See who marked a topic as a favorite
 *
 * @since 2.6.0 bbPress (r6197)
 * @since 2.6.0 bbPress (r6333) Updated to use BBP_User_Query
 */
function bbp_topic_favorites_metabox( $post ) {

	// Get user IDs
	$user_ids = bbp_get_topic_favoriters( $post->ID );

	// Output
	?><p><?php

		// Relationships
		$args = array(
			'include' => $user_ids
		);

		// Users were found
		if ( ! empty( $user_ids ) && bbp_has_users( $args ) ) :
			bbp_metabox_user_links();

		// No users
		else :
			esc_html_e( 'No users have favorited this topic.', 'bbpress' );
		endif;

	?></p><?php

	do_action( 'bbp_favorites_metabox', $post );
}

/**
 * See who is subscribed to a topic
 *
 * @since 2.6.0 bbPress (r6197)
 * @since 2.6.0 bbPress (r6333) Updated to use BBP_User_Query
 */
function bbp_topic_subscriptions_metabox( $post ) {

	// Current user subscription
	$input_value = bbp_is_user_subscribed( bbp_get_current_user_id(), $post->ID )
		? 'bbp_subscribe' // maintain existing subscription
		: '';             // do not add or remove subscription

	// Get user IDs
	$user_ids = bbp_get_subscribers( $post->ID );

	// Output
	?>
	<input name="bbp_topic_subscription" id="bbp_topic_subscription" type="hidden" value="<?php echo esc_attr( $input_value ); ?>" />
	<p><?php

		// Relationships
		$args = array(
			'include' => $user_ids
		);

		// Users were found
		if ( ! empty( $user_ids ) && bbp_has_users( $args ) ) :
			bbp_metabox_user_links();

		// No users
		else :
			esc_html_e( 'No users have subscribed to this topic.', 'bbpress' );
		endif;

	?></p><?php

	do_action( 'bbp_subscriptions_metabox', $post );
}

/**
 * See who is subscribed to a forum
 *
 * @since 2.6.0 bbPress (r6197)
 * @since 2.6.0 bbPress (r6333) Updated to use BBP_User_Query
 */
function bbp_forum_subscriptions_metabox( $post ) {

	// Get user IDs
	$user_ids = bbp_get_subscribers( $post->ID );

	// Output
	?><p><?php

		// Relationships
		$args = array(
			'include' => $user_ids
		);

		// Users were found
		if ( ! empty( $user_ids ) && bbp_has_users( $args ) ) :
			bbp_metabox_user_links();

		// No users
		else :
			esc_html_e( 'No users have subscribed to this forum.', 'bbpress' );
		endif;

	?></p><?php

	do_action( 'bbp_forum_subscriptions_metabox', $post );
}

/**
 * Loop through queried metabox users, and output links to their avatars
 *
 * Developers Note: This function may change in a future release to include
 * additional actions, so do not use this function in any third party plugin.
 *
 * @since 2.6.0 bbPress (r6913)
 */
function bbp_metabox_user_links() {

	// Loop through users
	while ( bbp_users() ) {

		// Set the iterator
		bbp_the_user();

		// Get the user ID, URL, and Avatar
		$user_id     = bbp_get_user_id();
		$user_url    = bbp_get_user_profile_url( $user_id );
		$user_avatar = get_avatar( $user_id, 32, '', '', array(
			'force_display' => true
		) );

		// Output a link to the user avatar
		echo '<a href="' . esc_url( $user_url ) . '">' . $user_avatar . '</a>';
	}
}
