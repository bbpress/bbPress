
				<?php bbp_set_query_name( 'bbp_user_profile_favorites' ); ?>

				<div id="bbp-author-favorites" class="bbp-author-favorites">
					<hr />
					<h2 class="entry-title"><?php _e( 'Favorite Forum Topics', 'bbpress' ); ?></h2>
					<div class="entry-content">

						<?php if ( bbp_get_user_favorites() ) :

							get_template_part( 'loop', 'bbp_topics' );

						else : ?>

							<p><?php bbp_is_user_home() ? _e( 'You currently have no favorite topics.', 'bbpress' ) : _e( 'This user has no favorite topics.', 'bbpress' ); ?></p>

						<?php endif; ?>

					</div>
				</div><!-- #bbp-author-favorites -->

				<?php bbp_reset_query_name(); ?>
