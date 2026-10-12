<?php

/**
 * Tests for bbPress block registration and rendering.
 *
 * @group common
 * @group blocks
 */
class BBP_Tests_Common_Blocks extends BBP_UnitTestCase {

	public function test_registered_blocks_match_block_metadata() {
		$blocks   = bbpress()->blocks;
		$metadata = BBP_Blocks::get_block_metadata();
		$names    = wp_list_pluck( $metadata, 'name' );

		sort( $names );
		$registered = array_keys( $blocks->blocks );
		sort( $registered );

		$this->assertSame( $names, $registered );
		$this->assertNotEmpty( $registered );

		foreach ( $registered as $name ) {
			$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $name );

			$this->assertInstanceOf( 'WP_Block_Type', $block_type );
			$this->assertArrayHasKey( $blocks->blocks[ $name ], bbpress()->shortcodes->codes );
			$this->assertSame( array( $blocks, 'render_block' ), $block_type->render_callback );
		}
	}

	public function test_constructor_sets_up_block_hooks() {
		$blocks = bbpress()->blocks;

		$this->assertSame( 10, has_action( 'enqueue_block_editor_assets', array( $blocks, 'enqueue_block_editor_assets' ) ) );
		$this->assertSame( 10, has_filter( 'block_categories_all', array( $blocks, 'register_block_category' ) ) );
		$this->assertGreaterThan( 0, did_action( 'bbp_blocks_loaded' ) );
	}

	/**
	 * @covers BBP_Blocks::get_block_metadata
	 */
	public function test_get_block_metadata_returns_decoded_block_json() {
		$metadata = BBP_Blocks::get_block_metadata();

		foreach ( $metadata as $block ) {
			$this->assertStringStartsWith( 'bbpress/', $block['name'] );
			$this->assertIsInt( $block['apiVersion'] );
			$this->assertSame( 'bbpress', $block['category'] );
			$this->assertSame( 'bbp-admin-blocks', $block['editorScript'] );
			$this->assertSame( 'bbp-admin-blocks', $block['editorStyle'] );
		}
	}

	/**
	 * @covers BBP_Blocks::render_block
	 */
	public function test_render_block_returns_empty_string_for_invalid_blocks() {
		$blocks = bbpress()->blocks;

		$this->assertSame( '', $blocks->render_block() );
		$this->assertSame( '', $blocks->render_block( array(), '', (object) array() ) );
		$this->assertSame( '', $blocks->render_block( array(), '', (object) array( 'name' => 'bbpress/unknown' ) ) );
	}

	/**
	 * @covers BBP_Blocks::render_block
	 */
	public function test_render_block_returns_empty_string_for_missing_shortcode() {
		$blocks                          = bbpress()->blocks;
		$blocks->blocks['bbpress/probe'] = 'bbp-probe';

		try {
			$this->assertSame( '', $blocks->render_block( array(), '', (object) array( 'name' => 'bbpress/probe' ) ) );
		} finally {
			unset( $blocks->blocks['bbpress/probe'] );
		}
	}

	/**
	 * @covers BBP_Blocks::render_block
	 */
	public function test_render_block_forwards_attributes_and_content_to_shortcode_callback() {
		$blocks                          = bbpress()->blocks;
		$shortcodes                      = bbpress()->shortcodes;
		$blocks->blocks['bbpress/probe'] = 'bbp-probe';
		$shortcodes->codes['bbp-probe']  = function( $attributes, $content ) {
			return $attributes['prefix'] . $content;
		};

		try {
			$output = $blocks->render_block(
				array( 'prefix' => 'before-' ),
				'content',
				(object) array( 'name' => 'bbpress/probe' )
			);

			$this->assertSame( 'before-content', $output );
		} finally {
			unset( $blocks->blocks['bbpress/probe'], $shortcodes->codes['bbp-probe'] );
		}
	}

	/**
	 * @covers BBP_Blocks::register_block_category
	 */
	public function test_register_block_category_appends_forums_category() {
		$categories = array(
			array(
				'slug'  => 'text',
				'title' => 'Text',
			),
		);

		$actual = apply_filters( 'block_categories_all', $categories, new WP_Block_Editor_Context() );

		$this->assertSame( $categories[0], $actual[0] );
		$this->assertSame(
			array(
				'slug'  => 'bbpress',
				'title' => 'Forums',
				'icon'  => 'buddicons-bbpress-logo',
			),
			$actual[1]
		);
	}

	/**
	 * @covers BBP_Blocks::get_localize_script_data
	 */
	public function test_get_localize_script_data_returns_views_and_fallback_titles() {
		$views = bbpress()->views;

		try {
			bbpress()->views = array();
			$this->assertSame(
				array(
					array(
						'value' => '',
						'label' => 'Select a View',
					),
				),
				BBP_Blocks::get_localize_script_data( 'views' )
			);

			bbpress()->views['custom-view']   = array( 'title' => 'Custom View' );
			bbpress()->views['fallback-view'] = array();

			$options = BBP_Blocks::get_localize_script_data( 'views' );
			$labels  = wp_list_pluck( $options, 'label', 'value' );

			$this->assertSame( array( 'value' => '', 'label' => 'Select a View' ), $options[0] );
			$this->assertSame( 'Custom View', $labels['custom-view'] );
			$this->assertSame( 'Fallback View', $labels['fallback-view'] );
		} finally {
			bbpress()->views = $views;
		}
	}

	/**
	 * @covers BBP_Blocks::get_localize_script_data
	 */
	public function test_get_localize_script_data_returns_null_for_unknown_key() {
		$this->assertNull( BBP_Blocks::get_localize_script_data() );
		$this->assertNull( BBP_Blocks::get_localize_script_data( 'unknown' ) );
	}
}
