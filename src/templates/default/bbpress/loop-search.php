<?php

/**
 * Search Loop
 *
 * @package bbPress
 * @subpackage Theme
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

$search_title = bbp_is_single_user_mentions()
	? __( 'Mentions', 'bbpress' )
	: __( 'Search Results', 'bbpress' );

do_action( 'bbp_template_before_search_results_loop' ); ?>

<ul id="bbp-search-results" class="forums bbp-search-results">

	<li class="bbp-header">

		<div class="bbp-search-author"><?php esc_html_e( 'Author',  'bbpress' ); ?></div><!-- .bbp-reply-author -->

		<div class="bbp-search-content">

			<?php echo esc_html( $search_title ); ?>

		</div><!-- .bbp-search-content -->

	</li><!-- .bbp-header -->

	<li class="bbp-body">

		<?php while ( bbp_search_results() ) : bbp_the_search_result(); ?>

			<?php bbp_get_template_part( 'loop', 'search-' . get_post_type() ); ?>

		<?php endwhile; ?>

	</li><!-- .bbp-body -->

	<li class="bbp-footer">

		<div class="bbp-search-author"><?php esc_html_e( 'Author',  'bbpress' ); ?></div>

		<div class="bbp-search-content">

			<?php echo esc_html( $search_title ); ?>

		</div><!-- .bbp-search-content -->

	</li><!-- .bbp-footer -->

</ul><!-- #bbp-search-results -->

<?php do_action( 'bbp_template_after_search_results_loop' );
