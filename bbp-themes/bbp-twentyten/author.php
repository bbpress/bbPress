<?php
/**
 * The template for displaying Author Archive pages.
 *
 * @package WordPress
 * @subpackage Twenty_Ten
 * @since Twenty Ten 1.0
 */

get_header(); ?>

		<div id="container">
			<div id="content" role="main">

<?php
	/* Queue the first post, that way we know who
	 * the author is when we try to get their name,
	 * URL, description, avatar, etc.
	 *
	 * We reset this later so we can run the loop
	 * properly with a call to rewind_posts().
	 */
	if ( have_posts() )
		the_post();
?>

				<h1 class="page-title author"><?php printf( __( 'Author Archives: %s', 'twentyten' ), "<span class='vcard'><a class='url fn n' href='" . get_author_posts_url( get_the_author_meta( 'ID' ) ) . "' title='" . esc_attr( get_the_author() ) . "' rel='me'>" . get_the_author() . "</a></span>" ); ?></h1>

<?php
// If a user has filled out their description, show a bio on their entries.
if ( get_the_author_meta( 'description' ) ) : ?>
					<div id="entry-author-info">
						<div id="author-avatar">
							<?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'twentyten_author_bio_avatar_size', 60 ) ); ?>
						</div><!-- #author-avatar -->
						<div id="author-description">
							<h2><?php printf( __( 'About %s', 'twentyten' ), get_the_author() ); ?></h2>
							<?php the_author_meta( 'description' ); ?>
						</div><!-- #author-description	-->
					</div><!-- #entry-author-info -->
<?php endif; ?>

<?php
	/* Since we called the_post() above, we need to
	 * rewind the loop back to the beginning that way
	 * we can run the loop properly, in full.
	 */
	rewind_posts();

	/* Run the loop for the author archive page to output the authors posts
	 * If you want to overload this in a child theme then include a file
	 * called loop-author.php and that will be used instead.
	 */
	get_template_part( 'loop', 'author' );
?>

<h2 id="fav-heading"><?php _e( 'Favorites', 'bbpress' ); ?></h2>

<?php
	/* @todo Add favorites feeds
        if ( current_user_can( 'edit_user', get_the_author_meta( 'ID' ) ) ) : ?>
	<p>
		<?php printf( __( 'You can also <a href="%1$s">manage your favorites</a> and subscribe to your favorites&#8217; <a href="%2$s"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>.', 'bbpress' ), esc_attr( bbp_get_favorites_link() ), esc_attr( bbp_get_favorites_rss_link() ) ); ?>
	</p>

	<p><?php _e( 'Favorites allow members to create a custom <abbr title="Really Simple Syndication">RSS</abbr> feed which pulls recent replies to the topics they specify.', 'bbpress' ); ?></p>

<?php
		endif;
	 */
?>

<?php if ( current_user_can( 'edit_user', get_the_author_meta( 'ID' ) ) ) : ?>

	<p><?php _e( 'To add topics to your list of favorites, just click the "Add to Favorites" link found on that topic&#8217;s page.', 'bbpress' ); ?></p>

<?php endif; ?>

<?php
	// Get the user's favorite topics
	if ( bbp_get_user_favorites( get_the_author_meta( 'ID' ) ) ) :

		get_template_part( 'loop', 'bbp_topics' );

	else :

		$current_user = wp_get_current_user();

		if ( get_the_author_meta( 'ID' ) == $current_user->ID ) : ?>

		<p><?php _e( 'You currently have no favorite topics.', 'bbpress' ); ?></p>

<?php else : ?>

		<p><?php _e( 'The user currently has no favorite topics.', 'bbpress' ); ?></p>

<?php
		endif;
	endif;
?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
