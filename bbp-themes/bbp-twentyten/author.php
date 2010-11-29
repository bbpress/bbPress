<?php
/**
 * Template Name: bbPress - User Profile
 *
 * @package bbPress
 * @subpackage Template
 */
?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php if ( have_posts() ) the_post(); ?>

				<h1 class="page-title author"><?php printf( __( 'Profile: %s', 'twentyten' ), "<span class='vcard'><a class='url fn n' href='" . get_author_posts_url( get_the_author_meta( 'ID' ) ) . "' title='" . esc_attr( get_the_author() ) . "' rel='me'>" . get_the_author() . "</a></span>" ); ?></h1>

				<div id="entry-author-info">
					<div id="author-avatar">

						<?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'twentyten_author_bio_avatar_size', 60 ) ); ?>

					</div><!-- #author-avatar -->
					<div id="author-description">
						<h1><?php printf( __( 'About %s', 'twentyten' ), get_the_author() ); ?></h1>

						<?php the_author_meta( 'description' ); ?>

					</div><!-- #author-description	-->
				</div><!-- #entry-author-info -->

				<div id="bbp-author-favorites" class="bbp-author-favorites">
					<hr />
					<h1 class="entry-title"><?php _e( 'Favorite Forum Topics', 'bbpress' ); ?></h1>
					<div class="entry-content">

						<?php if ( bbp_is_user_home() ) : ?>

							<p><?php _e( 'To add topics to your list of favorites, just click the "Add to Favorites" link found on that topic&#8217;s page.', 'bbpress' ); ?></p>

						<?php endif; ?>

						<?php if ( bbp_get_user_favorites() ) :

							get_template_part( 'loop', 'bbp_topics' );

						else : ?>

							<p><?php bbp_is_user_home() ? _e( 'You currently have no favorite topics.', 'bbpress' ) : _e( 'This user has no favorite topics.', 'bbpress' ); ?></p>

						<?php endif; ?>

					</div>
				</div><!-- #bbp-author-favorites -->

				<div id="bbp-author-topics-started" class="bbp-author-topics-started">
					<hr />
					<h1 class="entry-title"><?php _e( 'Forum Topics Created', 'bbpress' ); ?></h1>
					<div class="entry-content">

						<?php if ( bbp_get_user_topics_started() ) :

							get_template_part( 'loop', 'bbp_topics' );

						else : ?>

							<p><?php bbp_is_user_home() ? _e( 'You have not created any topics.', 'bbpress' ) : _e( 'This user has not created any topics.', 'bbpress' ); ?></p>

						<?php endif; ?>

					</div>
				</div><!-- #bbp-author-topics-started -->


				<div id="bbp-author-blog-posts" class="bbp-author-blog-posts">
					<hr />
					<h1 class="entry-title"><?php _e( 'Blog Posts', 'bbpress' ); ?></h1>

					<div class="entry-content">

					<?php rewind_posts(); ?>

					<?php get_template_part( 'loop', 'author' ); ?>
					</div>
				</div><!-- #bbp-author-blog-posts -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
