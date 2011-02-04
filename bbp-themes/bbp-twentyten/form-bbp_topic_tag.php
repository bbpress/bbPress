<?php

/**
 * Edit Topic Tag
 *
 * @package bbPress
 * @subpackage Theme
 */

//@todo - remove $term variable references
$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );

?>
<?php if ( current_user_can( 'edit_topic_tags' ) ) : ?>

	<div id="edit-topic-tag-<?php echo $term->term_id; ?>" class="bbp-topic-tag-form">

		<fieldset>

			<legend><?php printf( __( 'Manage Tag: "%s"', 'bbpress' ), $term->name ); ?></legend>

			<fieldset id="tag-rename">

				<legend><?php _e( 'Rename', 'bbpress' ); ?></legend>

				<div class="bbp-template-notice info">
					<p><?php _e( 'Leave the slug empty to have one automatically generated.', 'bbpress' ); ?></p>
				</div>

				<div class="bbp-template-notice">
					<p><?php _e( 'Changing the slug affects its permalink. Any links to the old slug will stop working.', 'bbpress' ); ?></p>
				</div>

				<form id="rename_tag" name="rename_tag" method="post" action="">

					<div class="alignleft">
						<label for="tag-name"><?php _e( 'Name:', 'bbpress' ); ?></label>
						<input type="text" name="tag-name" size="20" maxlength="40" tabindex="<?php bbp_tab_index(); ?>" value="<?php echo esc_attr( $term->name ); ?>" />
					</div>

					<div class="alignleft">
						<label for="tag-name"><?php _e( 'Slug:', 'bbpress' ); ?></label>
						<input type="text" name="tag-slug" size="20" maxlength="40" tabindex="<?php bbp_tab_index(); ?>" value="<?php echo esc_attr( apply_filters( 'editable_slug', $term->slug ) ); ?>" />
					</div>

					<div class="alignright">
						<input type="submit" name="submit" tabindex="<?php bbp_tab_index(); ?>" value="<?php esc_attr_e( 'Update', 'bbpress' ); ?>" /><br />

						<input type="hidden" name="tag-id" value="<?php echo esc_attr( $term->term_id ); ?>" />
						<input type="hidden" name="action" value="bbp-update-topic-tag" />

						<?php wp_nonce_field( 'update-tag_' . $term->term_id ); ?>

					</div>
				</form>

			</fieldset>

			<fieldset id="tag-merge">

				<legend><?php _e( 'Merge', 'bbpress' ); ?></legend>

				<div class="bbp-template-notice">
					<p><?php _e( 'Merging tags together cannot be undone.', 'bbpress' ); ?></p>
				</div>

				<form id="merge_tag" name="merge_tag" method="post" action="">

					<div class="alignleft">
						<label for="tag-name"><?php _e( 'Existing tag:', 'bbpress' ); ?></label>
						<input type="text" name="tag-name" size="22" tabindex="<?php bbp_tab_index(); ?>" maxlength="40" />
					</div>

					<div class="alignright">
						<input type="submit" name="submit" tabindex="<?php bbp_tab_index(); ?>" value="<?php esc_attr_e( 'Merge', 'bbpress' ); ?>"
							onclick="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to merge the "%s" tag into the tag you specified?', 'bbpress' ), $term->name ) ); ?>');" />

						<input type="hidden" name="tag-id" value="<?php echo $term->term_id; ?>" />
						<input type="hidden" name="action" value="bbp-merge-topic-tag" />

						<?php wp_nonce_field( 'merge-tag_' . $term->term_id ); ?>
					</div>
				</form>

			</fieldset>

			<?php if ( current_user_can( 'delete_topic_tags' ) ) : ?>

				<fieldset id="delete-tag">

					<legend><?php _e( 'Delete', 'bbpress' ); ?></legend>

					<div class="bbp-template-notice info">
						<p><?php _e( 'This does not delete your topics. Only the tag itself is deleted.', 'bbpress' ); ?></p>
					</div>
					<div class="bbp-template-notice">
						<p><?php _e( 'Deleting a tag cannot be undone.', 'bbpress' ); ?></p>
						<p><?php _e( 'Any links to this tag will no longer function.', 'bbpress' ); ?></p>						
					</div>

					<form id="delete_tag" name="delete_tag" method="post" action="">

						<div class="alignright">
							<input type="submit" name="submit" tabindex="<?php bbp_tab_index(); ?>" value="<?php _e( 'Delete', 'bbpress' ); ?>"
								onclick="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to delete the "%s" tag? This is permanent and cannot be undone.', 'bbpress' ), $term->name ) ); ?>');" />

							<input type="hidden" name="tag-id" value="<?php echo $term->term_id; ?>" />
							<input type="hidden" name="action" value="bbp-delete-topic-tag" />

							<?php wp_nonce_field( 'delete-tag_' . $term->term_id ); ?>
						</div>
					</form>

				</fieldset>

			<?php endif; ?>

		</fieldset>
	</div>

<?php endif; ?>
