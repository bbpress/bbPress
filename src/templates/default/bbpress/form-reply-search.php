<?php

/**
 * Search 
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<form role="search" method="get" id="bbp-reply-search-form">
	<div>
		<label class="screen-reader-text hidden" for="ts"><?php esc_html_e( 'Search replies:', 'bbpress' ); ?></label>
		<input type="text" value="<?php bbp_search_terms(); ?>" name="ts" id="rs" />
		<input class="button" type="submit" id="bbp_search_submit" value="<?php esc_attr_e( 'Search', 'bbpress' ); ?>" />
	</div>
</form>
