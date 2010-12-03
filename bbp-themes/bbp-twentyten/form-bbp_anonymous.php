
					<?php if ( bbp_is_anonymous() ) : ?>

						<fieldset>
							<legend><?php _e( 'Your information:', 'bbpress' ); ?></legend>
							<p>
								<label for="bbp_anonymous_author"><?php _e( '* Name:', 'bbpress' ); ?></label><br />
								<input type="text" id="bbp_anonymous_author" value="" tabindex="4" size="40" name="bbp_anonymous_name" />
							</p>

							<p>
								<label for="bbp_anonymous_email"><?php _e( '* Email Address:', 'bbpress' ); ?></label><br />
								<input type="text" id="bbp_anonymous_email" value="" tabindex="6" size="40" name="bbp_anonymous_email" />
							</p>

							<p>
								<label for="bbp_anonymous_website"><?php _e( 'Website:', 'bbpress' ); ?></label><br />
								<input type="text" id="bbp_anonymous_website" value="" tabindex="8" size="40" name="bbp_anonymous_website" />
							</p>
						</fieldset>

					<?php endif; ?>
