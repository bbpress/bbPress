
				<?php if ( bbp_is_user_home() && bbp_is_subscriptions_active() ) : ?>

					<?php set_query_var( '_bbp_query_name', 'bbp_user_profile_subscriptions' ); ?>

					<div id="bbp-author-subscriptions" class="bbp-author-subscriptions">
						<hr />
						<h2 class="entry-title"><?php _e( 'Subscribed Forum Topics', 'bbpress' ); ?></h2>
						<div class="entry-content">

							<?php if ( bbp_get_user_subscriptions() ) : ?>

								<?php get_template_part( 'loop', 'bbp_topics' ); ?>

							<?php else : ?>

								<p><?php _e( 'You are not currently subscribed to any topics.', 'bbpress' ); ?></p>

							<?php endif; ?>

						</div>
					</div><!-- #bbp-author-subscriptions -->

					<?php set_query_var( '_bbp_query_name', '' ); ?>

				<?php endif; ?>
