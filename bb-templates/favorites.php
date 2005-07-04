<?php get_header(); ?>

<?php login_form(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Your Favorites</h3>

<p>Your favorites allow you to creat a custom <abbr title="Really Simple Syndication">RSS</abbr> feed which pulls recent replies to the topics you specify.
To add topics to your favorites, just click the "Add to Favorites" link found on that topic's page.</p>

<?php if ( $current_user ) : ?>

<h2>Your Current Favorites<?php if ( $topics ) echo ' (' . count($topics) . ')'; ?></h2>
<p>Subscribe to your favorites' <a href="<?php favorites_rss_link( $current_user->ID ) ?>"><abbr title="Really Simple Syndication">RSS</abbr> feed</a>.</p>

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
	<td class="num">[<?php user_favorites_link('', 'x'); ?>]</td>
</tr>
<?php endforeach; ?>
</table>

<?php else: ?>

<p>You currently have no favorites.</p>

<?php endif; else: ?>

<p>You must log in to manage your favorites.</p>

<?php endif; ?>

<?php get_footer(); ?>
