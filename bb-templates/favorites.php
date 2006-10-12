<?php bb_get_header(); ?>

<h3 class="bbcrumb"><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; <?php _e('Favorites'); ?></h3>

<h2 id="currentfavorites"><?php _e('Current Favorites'); ?><?php if ( $topics ) echo ' (' . count($topics) . ')'; ?></h2>

<p><?php _e('Your Favorites allow you to create a custom <abbr title="Really Simple Syndication">RSS</abbr> feed which pulls recent replies to the topics you specify.
To add topics to your list of favorites, just click the "Add to Favorites" link found on that topic&#8217;s page.'); ?></p>

<?php if ( $user_id == $bb_current_user->ID ) : ?>
<p>Subscribe to your favorites' <a href="<?php favorites_rss_link( $bb_current_user->ID ) ?>"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>.</p>
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
	<td><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
	<td class="num">[<?php user_favorites_link('', array('mid'=>'x'), $user_id); ?>]</td>
</tr>
<?php endforeach; ?>
</table>

<?php else: if ( $user_id == $bb_current_user->ID ) : ?>

<p><?php _e('You currently have no favorites.'); ?></p>

<?php else : ?>

<p><?php echo get_user_name( $user_id ); ?> <?php _e('currently has no favorites.'); ?></p>

<?php endif; endif; ?>

<?php bb_get_footer(); ?>
