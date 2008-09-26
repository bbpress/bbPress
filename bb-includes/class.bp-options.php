<?php

/**
 * BP_Options allows storage of options for BackPress
 * in the bbPress database
 *
 * @package bbPress
 */
class BP_Options
{
	function prefix() {
		return 'bp_bbpress_';
	}
	
	function get($option) {
		switch ($option) {
			case 'cron_uri':
				return bb_get_uri('bb-cron.php', array('check' => BP_Options::get('cron_check')), BB_URI_CONTEXT_WP_HTTP_REQUEST);
				break;
			case 'cron_check':
				return wp_hash('187425');
				break;
			case 'charset':
				return bb_get_option($option);
				break;
			case 'wp_http_version':
				return 'bbPress/' . bb_get_option('version');
				break;
			default:
				return bb_get_option(BP_Options::prefix() . $option);
				break;
		}
	}
	
	function add($option, $value) {
		return BP_Options::update($option, $value);
	}
	
	function update($option, $value) {
		return bb_update_option(BP_Options::prefix() . $option, $value);
	}
	
	function delete($option) {
		return bb_delete_option(BP_Options::prefix() . $option);
	}
} // END class BP_Options implements BP_Options_Interface

?>