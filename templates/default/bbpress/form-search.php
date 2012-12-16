<?php

/**
 * Search 
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<form role="search" method="get" id="bbp-search" action="<?php bbp_search_url(); ?>">
	<div>
		<label class="screen-reader-text" for="bbp_search"><?php _e( 'Search for:', 'bbpress' ); ?></label>
		<input type="text" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" id="bbp_search" />
		<input type="submit" id="bbp_search_submit" value="<?php _e( 'Search Forums', 'bbpress' ); ?>" />
	</div>
</form>
