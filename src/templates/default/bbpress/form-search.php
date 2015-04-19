<?php

/**
 * Search 
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<form role="search" method="get" id="bbp-search-form" action="<?php bbp_search_url(); ?>">
	<div>
		<label class="screen-reader-text hidden" for="bbp_search"><?php esc_html_e( 'Search for:', 'bbpress' ); ?></label>
		<input type="hidden" name="action" value="bbp-search-request" />
		<input type="text" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" id="bbp_search" />
		<input class="button" type="submit" id="bbp_search_submit" value="<?php esc_attr_e( 'Search', 'bbpress' ); ?>" />
	</div>
</form>
