<?php $current_poster = bb_get_current_poster(); ?>
	<p id="post-form-author-container">
		<label for="author"><?php _e( 'Author (required)' ); ?>
			<input type="text" name="author" id="author" size="50" tabindex="2" aria-required="true" value="<?php echo esc_attr( $current_poster['post_author'] ); ?>" />
		</label>
	</p>

	<p id="post-form-email-container">
		<label for="email"><?php _e( 'Email (required)' ); ?>
			<input type="text" name="email" id="email" size="50" tabindex="3" aria-required="true" value="<?php echo esc_attr( $current_poster['post_author_email'] ); ?>" />
		</label>
	</p>

	<p id="post-form-url-container">
		<label for="url"><?php _e( 'URL' ); ?>
			<input type="text" name="url" id="url" size="50" tabindex="4" aria-required="true" value="<?php echo esc_attr( $current_poster['post_author_url'] ); ?>" />
		</label>
	</p>
