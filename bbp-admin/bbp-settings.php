<?php


class BBP_Admin_Settings {

	function bbp_admin_settings () {
		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Create the settings page
		add_action( 'admin_menu', array( $this, 'settings_page' ) );

	}
}

?>
