<?php

/**
 * Template Name: bbPress - User Register
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php while ( have_posts() ) : the_post(); ?>

					<div id="bbp-register" class="bbp-register">
						<h1 class="entry-title"><?php bbp_title_breadcrumb(); ?></h1>
						<div class="entry-content">

							<?php the_content(); ?>

							<?php if ( !is_user_logged_in() ) : ?>

								<form method="post" action="">

									<fieldset>
										<legend><?php _e( 'Profile Information', 'bbpress' ); ?></legend>

										<p><?php _e( 'Your password will be emailed to the address you provide.', 'bbpress' ); ?></p>

										<?php $user_login_error = $bb_register_error->get_error_message( 'user_login' ); ?>

										<table width="100%">
											<tr class="form-field form-required required<?php if ( $user_login_error ) echo ' form-invalid error'; ?>">
												<th scope="row">
													<label for="user_login"><?php _e('Username'); ?></label>

													<?php
														if ( $user_login_error )
															echo "<em>$user_login_error</em>";
													?>

												</th>

												<td>
													<input name="user_login" type="text" id="user_login" size="30" maxlength="30" value="<?php echo $user_login; ?>" />
												</td>
											</tr>

											<?php

											if ( is_array( $profile_info_keys ) ) :
												foreach ( $profile_info_keys as $key => $label ) :
													$class = 'form-field';

													if ( $label[0] )
														$class .= ' form-required required';

													if ( $profile_info_key_error = $bb_register_error->get_error_message( $key ) )
														$class .= ' form-invalid error'; ?>

													<tr class="<?php echo $class; ?>">
														<th scope="row">
															<label for="<?php echo $key; ?>"><?php echo $label[1]; ?></label>

															<?php
																if ( $profile_info_key_error )
																	echo "<em>$profile_info_key_error</em>";
															?>

														</th>
														<td>
															<input name="<?php echo $key; ?>" type="text" id="<?php echo $key; ?>" size="30" maxlength="140" value="<?php echo $$key; ?>" />
														</td>
													</tr>

												<?php

												endforeach; // profile_info_keys
											endif; // profile_info_keys

											?>

										</table>

										<p class="required-message"><?php _e('These items are <span class="required">required</span>.') ?></p>

										<?php do_action( 'bbp_extra_profile_info', $user ); ?>

										<p class="submit">
											<input type="submit" name="Submit" value="<?php echo esc_attr__( 'Register &raquo;' ); ?>" />
										</p>

									</fieldset>

								</form>

							<?php else : ?>

								<p><?php _e('You&#8217;re already logged in, why do you need to register?'); ?></p>

							<?php endif; ?>

						</div>
					</div><!-- #bbp-login -->

				<?php endwhile; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
