
<div id="tags">
<?php if ( $user_tags ) : ?>
<div id="yourtags">
<p>Your tags:</p>
<ul>
<?php foreach ( $user_tags as $tag ) : ?>
	<li><a href="<?php tag_link(); ?>"><?php tag_name(); ?></a></li> 
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ( $other_tags ) : ?>
<div id="yourtags">
<p>Tags:</p>
<ul>
<?php foreach ( $other_tags as $tag ) : ?>
	<li><a href="<?php tag_link(); ?>"><?php tag_name(); ?></a></li> 
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<?php if ( !$tags ) : ?>
<p>No <a href="<?php option('uri'); ?>tags/">tags</a> yet.</p>
<?php endif; ?>
<?php tag_form(); ?>

</div>
