<?php

/**
 * User topics created loop
 *
 * @package bbPress
 * @subpackage Themes
 */

?>
				<?php bbp_set_query_name( 'bbp_user_profile_topics_created' ); ?>

				<div id="bbp-author-topics-started" class="bbp-author-topics-started">
					<hr />
					<h2 class="entry-title"><?php _e( 'Forum Topics Created', 'bbpress' ); ?></h2>
					<div class="entry-content">

						<?php if ( bbp_get_user_topics_started() ) :

							get_template_part( 'loop', 'bbp_topics' );

						else : ?>

							<p><?php bbp_is_user_home() ? _e( 'You have not created any topics.', 'bbpress' ) : _e( 'This user has not created any topics.', 'bbpress' ); ?></p>

						<?php endif; ?>

					</div>
				</div><!-- #bbp-author-topics-started -->

				<?php bbp_reset_query_name(); ?>
