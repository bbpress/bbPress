<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Search</h3>
<?php search_form( $q ); ?>

<?php if ( !empty ( $q ) ) : ?>
<h2>Search for &#8220;<?php echo bb_specialchars($q); ?>&#8221;</h2>
<?php endif; ?>

<?php if ( $topics ) : ?>
<h2>Thread title matches</h2>

<ol>
<?php 
foreach ( $topics as $topic ) : 
$count = $bbdb->get_var("SELECT COUNT(*) FROM $bbdb->posts WHERE topic_id = $topic->topic_id"); // TODO
?>
<li><h4><a href="<?php topic_link(); ?>"><?php topic_title(); ?></a></h4>
<small><?php echo $count; ?> replies &#8212; Last reply <?php echo date('F j, Y', $topic->posttime); ?> </small>
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
<p><small>Posted <?php post_date('F j, Y, h:i A', $post->topic_id); ?></small></p>
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
<p><small>Posted <?php post_date('F j, Y, h:i A', $post->topic_id); ?></small></p>
</li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php get_footer(); ?>