<div id="tags">
<?php if ( $user_tags ) : ?>
<div id="yourtags">
<p>Your tags:</p>
<ul id="yourtaglist">
<?php foreach ( $user_tags as $tag ) : ?>
	<li id="tag-<?php echo $tag->tag_id; ?>-<?php echo $tag->user_id; ?>"><a href="<?php tag_link(); ?>" rel="tag"><?php tag_name(); ?></a> <?php tag_remove_link(); ?></li> 
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ( $other_tags ) : ?>
<div id="othertags">
<p>Tags:</p>
<ul id="otherstaglist">
<?php foreach ( $other_tags as $tag ) : ?>
	<li id="tag-<?php echo $tag->tag_id; ?>-<?php echo $tag->user_id; ?>"><a href="<?php tag_link(); ?>" rel="tag"><?php tag_name(); ?></a> <?php tag_remove_link(); ?></li> 
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ( !$tags ) : ?>
<p>No <a href="<?php option('uri'); ?>tags/">tags</a> yet.</p>
<?php endif; ?>
<?php tag_form(); ?>

</div>
