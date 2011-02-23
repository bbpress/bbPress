<?php bb_get_header(); ?>

<div class="bbcrumb"><a href="<?php bb_uri(); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Search')?></div>
<?php bb_topic_search_form(); ?>

<?php if ( !empty ( $q ) ) : ?>
<h3 id="search-for"><?php _e('Search for')?> &#8220;<?php echo esc_html($q); ?>&#8221;</h3>
<?php endif; ?>

<?php if ( $recent ) : ?>
<div id="results-recent" class="search-results">
	<h4><?php _e('Recent Posts')?></h4>
	<ol>
<?php foreach ( $recent as $bb_post ) : ?>
		<li<?php alt_class( 'recent' ); ?>>
			<a href="<?php post_link(); ?>"><?php topic_title($bb_post->topic_id); ?></a>
			<span class="freshness"><?php printf( __('Posted %s'), bb_datetime_format_i18n( bb_get_post_time( array( 'format' => 'timestamp' ) ) ) ); ?></span>
			<p><?php echo bb_show_context($q, $bb_post->post_text); ?></p>
		</li>
<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

<?php if ( $relevant ) : ?>
<div id="results-relevant" class="search-results">
	<h4><?php _e('Relevant posts')?></h4>
	<ol>
<?php foreach ( $relevant as $bb_post ) : ?>
		<li<?php alt_class( 'relevant' ); ?>>
			<a href="<?php post_link(); ?>"><?php topic_title($bb_post->topic_id); ?></a>
			<span class="freshness"><?php printf( __('Posted %s'), bb_datetime_format_i18n( bb_get_post_time( array( 'format' => 'timestamp' ) ) ) ); ?></span>
			<p><?php post_text(); ?></p>
		</li>
<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

<?php if ( $q && !$recent && !$relevant ) : ?>
<p><?php _e('No results found.') ?></p>
<?php endif; ?>
<br />
<p><?php printf(__('You may also try your <a href="http://google.com/search?q=site:%1$s %2$s">search at Google</a>'), bb_get_uri(null, null, BB_URI_CONTEXT_TEXT), urlencode($q)) ?></p>
<?php bb_get_footer(); ?>
