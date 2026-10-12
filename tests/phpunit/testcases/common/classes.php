<?php

/**
 * Tests for common classes.
 *
 * @group common
 */
class BBP_Tests_Common_Classes extends BBP_UnitTestCase {

	/**
	 * @covers BBP_Component::generate_rewrite_rules
	 */
	public function test_component_forwards_rewrite_object_to_specific_hook() {
		$component = new BBP_Component(
			array(
				'name' => 'test_component',
				'id'   => 'test-component',
				'slug' => 'test-component',
			)
		);
		$rewrite   = new WP_Rewrite();
		$received  = null;
		$callback  = function( &$wp_rewrite ) use ( &$received ) {
			$received = $wp_rewrite;
		};

		add_action( 'bbp_test_component_generate_rewrite_rules', $callback );

		try {
			bbp_generate_rewrite_rules( $rewrite );
		} finally {
			remove_action( 'bbp_test_component_generate_rewrite_rules', $callback );
			remove_action( 'bbp_register_post_types', array( $component, 'register_post_types' ) );
			remove_action( 'bbp_register_taxonomies', array( $component, 'register_taxonomies' ) );
			remove_action( 'bbp_add_rewrite_tags', array( $component, 'add_rewrite_tags' ) );
			remove_action( 'bbp_generate_rewrite_rules', array( $component, 'generate_rewrite_rules' ) );
		}

		$this->assertSame( $rewrite, $received );
	}
}
