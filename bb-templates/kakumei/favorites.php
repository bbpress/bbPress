<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php bb_uri(); ?>"><?php bb_option('name'); ?></a> &raquo; <?php _e('Favorites'); ?></h3>

<h2 id="currentfavorites" role="main"><?php _e('Current Favorites'); ?><?php if ( $topics ) echo ' (' . $favorites_total . ')'; ?></h2>

<p><?php _e("Your Favorites allow you to create a custom <abbr title=\"Really Simple Syndication\">RSS</abbr> feed which pulls recent replies to the topics you specify.\nTo add topics to your list of favorites, just click the \"Add to Favorites\" link found on that topic&#8217;s page."); ?></p>

<?php if ( $user_id == bb_get_current_user_info( 'id' ) ) : ?>
<p><?php printf(__('Subscribe to your favorites&#8217; <a href="%s"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>.'), attribute_escape( get_favorites_rss_link( bb_get_current_user_info( 'id' ) ) )) ?></p>
<?php endif; ?>

<?php if ( $topics ) : ?>

<table id="favorites">
<tr>
	<th><?php _e('Topic'); ?></th>
	<th><?php _e('Posts'); ?></th>
	<th><?php _e('Freshness'); ?></th>
	<th><?php _e('Remove'); ?></th>
</tr>

<?php foreach ( $topics as $topic ) : ?>
<tr<?php topic_class(); ?>>
	<td><?php bb_topic_labels(); ?> <a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><a href="<?php topic_last_post_link(); ?>"><?php topic_time(); ?></a></td>
	<td class="num">[<?php user_favorites_link('', array('mid'=>'&times;'), $user_id); ?>]</td>
</tr>
<?php endforeach; ?>
</table>

<?php favorites_pages( array( 'before' => '<div class="nav">', 'after' => '</div>' ) ); ?>

<?php else: if ( $user_id == bb_get_current_user_info( 'id' ) ) : ?>

<p><?php _e('You currently have no favorites.'); ?></p>

<?php else : ?>

<p><?php echo get_user_name( $user_id ); ?> <?php _e('currently has no favorites.'); ?></p>

<?php endif; endif; ?>

<?php bb_get_footer(); ?>
