<form action="<?php bb_option('uri'); ?>search.php" method="get">
	<p><?php _e('Search:'); ?>
		<input type="text" size="38" maxlength="100" name="q" value="<?php echo attribute_escape( $q ); ?>" />
	</p>
	<?php if( empty($q) ) : ?>
	<p class="submit"><input type="submit" value="<?php echo attribute_escape( __('Search &raquo;') ); ?>" class="inputButton" /></p>
	<?php else : ?>
	<p class="submit"><input type="submit" value="<?php echo attribute_escape( __('Search again &raquo;') ); ?>" class="inputButton" /></p>
	<?php endif; ?>
</form>
