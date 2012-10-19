<?php

/**
 * Used to hook ajax listeners in
 */
class BBP_Post_Search {

	public function __construct() {
		add_action( 'wp_enqueue_scripts',              array( $this, 'enqueue_scripts' )     );
		add_action( 'admin_head',                      array( $this, 'admin_head'      ), 99 );
		add_action( 'wp_ajax_bbp_forum_lookup',        array( $this, 'forum_lookup'    )     );
		add_action( 'wp_ajax_nopriv_bbp_forum_lookup', array( $this, 'forum_lookup'    )     );
		add_action( 'wp_ajax_bbp_topic_lookup',        array( $this, 'topic_lookup'    )     );
		add_action( 'wp_ajax_nopriv_bbp_topic_lookup', array( $this, 'topic_lookup'    )     );
	}
	
	public function enqueue_scripts() {
		wp_enqueue_script( 'suggest' );
	}
	
	public function admin_head() {
	?>

	<script type="text/javascript">
		jQuery(document).ready(function() {

			var bbp_forum_id = jQuery( '#bbp_forum_id' );

			bbp_forum_id.suggest( ajaxurl + '?action=bbp_forum_lookup', {
				onSelect: function() {
					var value = this.value;
					bbp_forum_id.val( value.substr( 0, value.indexOf( ' ' ) ) );
				}
			} );

			var bbp_topic_id = jQuery( '#bbp_topic_id' );

			bbp_topic_id.suggest( ajaxurl + '?action=bbp_topic_lookup', {
				onSelect: function() {
					var value = this.value;
					bbp_topic_id.val( value.substr( 0, value.indexOf( ' ' ) ) );
				}
			} );
		});
	</script>

	<?php
	}

	public function forum_lookup() {
		foreach ( get_posts( array( 's' => like_escape( $_REQUEST['q'] ), 'post_type' => bbp_get_forum_post_type() ) ) as $post ) {
			echo sprintf( __( '%s - %s', 'bbpress' ), bbp_get_forum_id( $post->ID ), bbp_get_forum_title( $post->ID ) ) . "\n";
		}
		die();
	}

	public function topic_lookup() {
		foreach ( get_posts( array( 's' => like_escape( $_REQUEST['q'] ), 'post_type' => bbp_get_topic_post_type() ) ) as $post ) {
			echo sprintf( __( '%s - %s', 'bbpress' ), bbp_get_topic_id( $post->ID ), bbp_get_topic_title( $post->ID ) ) . "\n";
		}
		die();
	}
}
new BBP_Post_Search;