<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Search')?></h3>
<?php bb_topic_search_form(); ?>

<?php if ( !empty ( $q ) ) : ?>
<h2><?php _e('Search for')?> &#8220;<?php echo wp_specialchars($q); ?>&#8221;</h2>
<?php endif; ?>

<?php if ( $recent ) : ?>
<h2><?php _e('Recent Posts')?></h2>
<ol class="results">
<?php foreach ( $recent as $bb_post ) : ?>
<li><h4><a href="<?php post_link(); ?>"><?php topic_title($bb_post->topic_id); ?></a></h4>
<p><?php echo bb_show_context($q, $bb_post->post_text); ?></p>
<p><small><?php _e('Posted') ?> <?php echo bb_datetime_format_i18n( bb_get_post_time( array( 'format' => 'timestamp' ) ) ); ?></small></p>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( $relevant ) : ?>
<h2><?php _e('Relevant posts')?></h2>
<ol class="results">
<?php foreach ( $relevant as $bb_post ) : ?>
<li><h4><a href="<?php post_link(); ?>"><?php topic_title($bb_post->topic_id); ?></a></h4>
<p><?php echo bb_show_context($q, $bb_post->post_text); ?></p>
<p><small><?php _e('Posted') ?> <?php echo bb_datetime_format_i18n( bb_get_post_time( array( 'format' => 'timestamp' ) ) ); ?></small></p>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( $q && !$recent && !$relevant ) : ?>
<p><?php _e('No results found.') ?></p>
<?php endif; ?>
<br />
<p><?php printf(__('You may also try your <a href="http://google.com/search?q=site:%1$s %2$s">search at Google</a>'), bb_get_option('uri'), urlencode($q)) ?></p>
<?php bb_get_footer(); ?>
