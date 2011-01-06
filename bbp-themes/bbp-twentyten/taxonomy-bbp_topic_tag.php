<?php

/**
 * bbPress Topic Tag
 *
 * @package bbPress
 * @subpackage Themes
 */


$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );

?>

<?php get_header(); ?>

		<div id="container">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<div id="topic-tag" class="bbp-topic-tag">
					<h1 class="entry-title"><?php printf( __( 'Topic Tag: %s', 'bbpress' ), '<span>' . $term->name . '</span>' ); ?></h1>

					<div class="entry-content">

						<?php term_description(); ?>

						<?php get_template_part( 'loop', 'bbp_topics' ); ?>

					</div>
				</div><!-- #topic-tag -->
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
