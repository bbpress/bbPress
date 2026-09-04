<?php

/**
 * @group core
 * @group theme_compat
 */
class BBP_Tests_Core_Theme_Compat extends BBP_UnitTestCase {

	/**
	 * Original active theme stylesheet.
	 *
	 * @var string
	 */
	private $original_theme;

	/**
	 * Original registered theme directories.
	 *
	 * @var array
	 */
	private $original_theme_directories;

	/**
	 * Test theme root.
	 *
	 * @var string
	 */
	private $theme_root;

	public function setUp() {
		parent::setUp();

		$this->original_theme             = get_stylesheet();
		$this->original_theme_directories = $GLOBALS['wp_theme_directories'];
		$this->theme_root                 = BBP_TESTS_DIR . '/data/themes';

		register_theme_directory( $this->theme_root );
		wp_clean_themes_cache();
	}

	public function tearDown() {
		bbp_restore_all_filters( 'the_content' );
		bbp_set_theme_compat_active( false );
		bbp_set_template_included( false );
		remove_theme_support( 'block-templates' );

		switch_theme( $this->original_theme );
		$GLOBALS['wp_theme_directories'] = $this->original_theme_directories;
		wp_clean_themes_cache();

		parent::tearDown();
	}

	/**
	 * @ticket 3487
	 * @dataProvider get_theme_compat_template_cases
	 *
	 * @param string $theme                   Theme stylesheet.
	 * @param bool   $supports_block_templates Whether the theme supports block templates.
	 * @param bool   $is_block_theme           Whether the theme is a Block Theme.
	 * @param string $expected_template        Expected template path, relative to the test theme root.
	 */
	public function test_theme_compat_template_selection( $theme, $supports_block_templates, $is_block_theme, $expected_template ) {
		remove_theme_support( 'block-templates' );
		switch_theme( $theme );
		wp_enable_block_templates();

		$this->assertSame( $supports_block_templates, current_theme_supports( 'block-templates' ) );
		$this->assertSame( $is_block_theme, wp_is_block_theme() );
		$this->assertSame(
			':canvas' === $expected_template
				? ABSPATH . WPINC . '/template-canvas.php'
				: $this->theme_root . '/' . $expected_template,
			$this->get_theme_compat_template()
		);
	}

	/**
	 * Theme compatibility template selection cases.
	 *
	 * @return array[] Test cases.
	 */
	public function get_theme_compat_template_cases() {
		return array(
			'classic theme' => array(
				'theme'                    => 'bbp-classic-theme',
				'supports_block_templates' => false,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-theme/index.php',
			),
			'classic theme with theme.json' => array(
				'theme'                    => 'bbp-classic-hybrid-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-hybrid-theme/index.php',
			),
			'classic theme with theme.json and bbpress.php' => array(
				'theme'                    => 'bbp-classic-theme-json',
				'supports_block_templates' => true,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-theme-json/bbpress.php',
			),
			'classic child theme' => array(
				'theme'                    => 'bbp-classic-child-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => false,
				'expected_template'        => 'bbp-classic-theme-json/bbpress.php',
			),
			'block theme' => array(
				'theme'                    => 'bbp-block-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => true,
				'expected_template'        => ':canvas',
			),
			'block child theme' => array(
				'theme'                    => 'bbp-block-child-theme',
				'supports_block_templates' => true,
				'is_block_theme'           => true,
				'expected_template'        => ':canvas',
			),
		);
	}

	/**
	 * Run the theme compatibility template selection.
	 *
	 * @return string Selected template path.
	 */
	private function get_theme_compat_template() {
		bbp_set_theme_compat_active( true );
		bbp_set_template_included( false );

		$template = bbp_template_include_theme_compat( '/original-template.php' );

		bbp_restore_all_filters( 'the_content' );

		return $template;
	}
}
