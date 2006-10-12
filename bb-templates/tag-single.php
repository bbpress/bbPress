<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <a href="<?php tag_page_link(); ?>">Tags</a> &raquo; <?php tag_name(); ?></h3>

<p><a href="<?php tag_rss_link(); ?>"><abbr title="Really Simple Syndication">RSS</abbr> link for this tag.</a></p>

<?php do_action('tag_above_table', ''); ?>

<?php if ( $topics ) : ?>

<table id="latest">
<tr>
	<th><?php _e('Topic'); ?> &#8212; <?php new_topic(); ?></th>
	<th><?php _e('Posts'); ?></th>
	<th><?php _e('Last Poster'); ?></th>
	<th><?php _e('Freshness'); ?></th>
</tr>

<?php foreach ( $topics as $topic ) : ?>
<tr<?php topic_class(); ?>>
	<td><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><?php topic_last_poster(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
</tr>
<?php endforeach; ?>
</table>
<div class="nav">
<?php tag_pages(); ?>
</div>
<?php endif; ?>

<?php post_form(); ?>

<?php do_action('tag_below_table', ''); ?>

<?php manage_tags_forms(); ?>

<?php bb_get_footer(); ?>
