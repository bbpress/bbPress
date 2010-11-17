<?php

function bbp_add_tools_menu () {
	add_management_page( __( 'Recount', 'bbpress' ), __( 'Recount', 'bbpress' ), 'manage_options', 'bbp-recount', 'bbp_admin_tools' );
}
add_action( 'admin_menu', 'bbp_add_tools_menu' );

function bbp_admin_tools () {

	$recount_list = bbp_recount_list();

	if ( 'post' == strtolower( $_SERVER['REQUEST_METHOD'] ) ) {
		check_admin_referer( 'do-counts' );

		// Stores messages
		$messages = array();

		if ( !empty( $_POST['bbp-topic-replies'] ) )
			$messages[] = bbp_recount_topic_replies();

		if ( !empty( $_POST['bbp-topic-voices'] ) )
			$messages[] = bbp_recount_topic_voices();

		if ( !empty( $_POST['bbp-topic-deleted-replies'] ) )
			$messages[] = bbp_recount_topic_deleted_replies();

		if ( !empty( $_POST['bbp-forums'] ) ) {
			$messages[] = bbp_recount_forum_topics();
			$messages[] = bbp_recount_forum_replies();
		}

		if ( !empty( $_POST['bbp-topics-replied'] ) )
			$messages[] = bbp_recount_user_topics_replied();

		if ( !empty( $_POST['bbp-topic-tag-count'] ) )
			$messages[] = bbp_recount_topic_tags();

		if ( !empty( $_POST['bbp-tags-tag-count'] ) )
			$messages[] = bbp_recount_tag_topics();

		if ( !empty( $_POST['bbp-tags-delete-empty'] ) )
			$messages[] = bbp_recount_tag_delete_empty();

		if ( !empty( $_POST['bbp-clean-favorites'] ) )
			$messages[] = bbp_recount_clean_favorites();

		foreach ( (array) $recount_list as $item )
			if ( isset( $item[2] ) && isset( $_POST[$item[0]] ) && 1 == $_POST[$item[0]] && is_callable( $item[2] ) )
				$messages[] = call_user_func( $item[2] );

		wp_cache_flush();

		if ( count( $messages ) ) {
			$messages = join( '</p>' . "\n" . '<p>', $messages );
			bbp_admin_notice( $messages );
		}
	} ?>

	<div class="wrap">

		<div id="icon-tools" class="icon32"><br /></div>
		<h2><?php _e( 'bbPress Recount', 'bbpress' ) ?></h2>

		<?php if ( isset( $_POST['bbp-tools'] ) ) : ?>

			<div id="message" class="updated fade">
				<p><?php _e( 'Settings Saved', 'buddypress' ) ?></p>
			</div>

		<?php endif; ?>

		<p><?php _e( 'bbPress keeps a running count of things like replies to each topic and topics in each forum. In rare occasions these counts can fall out of sync. Using this form you can have bbPress manually recount these items.', 'bbpress' ); ?></p>
		<p><?php _e( 'You can also use this form to clean out stale items like empty tags.', 'bbpress' ); ?></p>

		<form class="settings" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><?php _e( 'Things to recount:', 'bbpress' ) ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e( 'Recount', 'bbpress' ) ?></span></legend>

								<?php if ( !empty( $recount_list ) ) :

										foreach ( $recount_list as $item ) {
											echo '<label><input type="checkbox" class="checkbox" name="' . esc_attr( $item[0] ) . '" id="' . esc_attr( str_replace( '_', '-', $item[0] ) ) . '" value="1" /> ' . esc_html( $item[1] ) . '</label><br />' . "\n";
										}
								?>

								<?php else : ?>

									<p><?php _e( 'There are no recount tools available.', 'bbpress' ) ?></p>

								<?php endif; ?>

							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<fieldset class="submit">
				<input class="button-primary" type="submit" name="submit" value="<?php _e( 'Recount Items', 'bbpress' ); ?>" />
				<?php wp_nonce_field( 'do-counts' ); ?>
			</fieldset>
		</form>
	</div>

<?php
}
?>
