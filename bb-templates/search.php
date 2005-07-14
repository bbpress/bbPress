<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Search</h3>
<?php search_form( $q ); ?>

<?php if ( !empty ( $q ) ) : ?>
<h2>Search for &#8220;<?php echo bb_specialchars($q); ?>&#8221;</h2>
<?php endif; ?>

<?php if ( $users ) : ?>
<h2>Users</h2>
<ul>
<?php foreach ( $users as $user ) : ?>
	<li><a href="<?php user_profile_link($user->ID); ?>"><?php echo $user->user_login; ?></a></li>

<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ( $titles ) : ?>
<h2>Thread title matches</h2>

<ol>
<?php 
foreach ( $titles as $topic ) : 
$count = $topic->topic_posts;
?>
<li><h4><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></h4>
<small><?php echo $count; ?> replies &#8212; Last reply <?php echo topic_date('F j, Y', $topic->topic_id); ?> </small>
</li>
<?php endforeach; ?>

</ol>
<?php endif; ?>

<?php if ( $recent ) : ?>
<h2>Recent Posts</h2>
<ol class="results">
<?php foreach ( $recent as $post ) : ?>
<li><h4><a href="<?php post_link(); ?>"><?php topic_title($post->topic_id); ?></a></h4>
<p><?php echo show_context($q, $post->post_text); ?></p>
<p><small>Posted <?php echo date('F j, Y, h:i A', get_post_time()); ?></small></p>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( $relevant ) : ?>
<h2>Relevant posts</h2>
<ol class="results">
<?php foreach ( $relevant as $post ) : ?>
<li><h4><a href="<?php post_link(); ?>"><?php topic_title($post->topic_id); ?></a></h4>
<p><?php echo show_context($q, $post->post_text); ?></p>
<p><small>Posted <?php echo date('F j, Y, h:i A', get_post_time()); ?></small></p>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( !$topics && !$recent && !$relevant ) : ?>
<p>No results found.</p>
<?php endif; ?>
<p>You may also try your <a href="http://google.com/search?q=site:<?php option('uri'); ?> <?php echo urlencode($q); ?>">search at Google.</a></p>
<?php get_footer(); ?>
