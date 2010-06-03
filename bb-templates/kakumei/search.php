<?php bb_get_header(); ?>

<div class="bbcrumb"><a href="<?php bb_uri(); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Search')?></div>
<?php bb_topic_search_form(); ?>

<?php if ( !empty ( $q ) ) : ?>
<h3 id="search-for"><?php printf( __( 'Search for %s' ), '&#8220;' . esc_html( $q ) . '&#8221;' ); ?></h3>
<?php endif; ?>

<?php if ( $recent ) : ?>
<div id="results-recent" class="search-results">
	<h4><?php _e( 'Recent Posts' )?></h4>
	<ol>
<?php foreach ( $recent as $bb_post ) : ?>
		<li<?php alt_class( 'recent' ); ?>>
			<a class="result" href="<?php post_link(); ?>"><?php echo bb_show_topic_context( $q, get_topic_title( $bb_post->topic_id ) ); ?></a>
			<span class="freshness"><?php printf( __( 'Posted by <a href="%1$s">%2$s</a> on %3$s'), get_user_profile_link( $bb_post->poster_id ), get_post_author(), bb_datetime_format_i18n( bb_get_post_time( array( 'format' => 'timestamp' ) ) ) ); ?></span>
			<p><?php echo bb_show_context( $q, $bb_post->post_text ); ?></p>
		</li>
<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

<?php if ( $relevant ) : ?>
<div id="results-relevant" class="search-results">
	<h4><?php _e( 'Relevant Topics' )?></h4>
	<ol>
<?php foreach ( $relevant as $topic ) : ?>
		<li<?php alt_class( 'relevant' ); ?>>
			<a class="result" href="<?php post_link( $topic->post_id ); ?>"><?php echo bb_show_topic_context( $q, get_topic_title() ); ?></a>
			<span class="freshness"><?php printf( __( 'Posted by <a href="%1$s">%2$s</a> on %3$s' ), get_user_profile_link( $topic->topic_id ), get_topic_author(), bb_datetime_format_i18n( get_topic_start_time( array( 'format' => 'timestamp' ) ) ) ); ?></span>
			<p><?php echo bb_show_context( $q, $topic->post_text ); ?></p>
		</li>
<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

<?php if ( $q && !$recent && !$relevant ) : ?>
<p><?php printf( __( 'Your search %s did not return any results. Here are some suggestions:' ), '&#8220;<em>' . esc_html( $q ) . '</em>&#8221;' ); ?></p>
<ul id="search-suggestions">
    <li><?php _e( 'Make sure all words are spelled correctly' ); ?></li>
    <li><?php _e( 'Try different keywords' ); ?></li>
    <li><?php _e( 'Try more general keywords' ); ?></li>
</ul>
<?php else: ?>
	<?php bb_search_pages(); ?>
	<br />
<?php endif; ?>

<br />
<p><?php printf( __( 'You may also try your <a href="%s">search at Google</a>.' ), 'http://google.com/search?q=site:' . bb_get_uri( null, null, BB_URI_CONTEXT_TEXT ) . urlencode( ' ' . $q ) ); ?></p>

<?php bb_get_footer(); ?>
