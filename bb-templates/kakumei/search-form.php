<form action="<?php bb_option('uri'); ?>search.php" method="get">
	<label class="hidden" for="q"><?php _e('Search:'); ?></label>
	<input class="text" type="text" size="14" maxlength="100" name="q" id="q" />
	<input class="submit" type="submit" value="<?php echo attribute_escape( __('Search &raquo;') ); ?>" />
</form>