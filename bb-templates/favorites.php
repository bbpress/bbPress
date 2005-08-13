<?php get_header(); ?>
<?php profile_menu(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Favorites</h3>

<p>Your Favorites allow you to create a custom <abbr title="Really Simple Syndication">RSS</abbr> feed which pulls recent replies to the topics you specify.
To add topics to your list of favorites, just click the "Add to Favorites" link found on that topic's page.</p>

<h2>Current Favorites<?php if ( $topics ) echo ' (' . count($topics) . ')'; ?></h2>
<?php if ( $user_id == $current_user->ID ) : ?>
<p>Subscribe to your favorites' <a href="<?php favorites_rss_link( $current_user->ID ) ?>"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>.</p>
<?php endif; ?>

<?php if ( $topics ) : ?>

<table id="favorites">
<tr>
	<th>Topic</th>
	<th>Posts</th>
	<th>Freshness</th>
	<th>Remove</th>
</tr>

<?php foreach ( $topics as $topic ) : ?>
<tr<?php alt_class('topic'); ?>>
	<td><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></td>
	<td class="num"><?php topic_posts(); ?></td>
	<td class="num"><small><?php topic_time(); ?></small></td>
	<td class="num">[<?php user_favorites_link('', 'x', $user_id); ?>]</td>
</tr>
<?php endforeach; ?>
</table>

<?php else: if ( $user_id == $current_user->ID ) : ?>

<p>You currently have no favorites.</p>

<?php else : ?>

<p><?php echo get_user_name( $user_id ); ?> currently has no favorites.</p>

<?php endif; endif; ?>

<?php get_footer(); ?>
