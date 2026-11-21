<?php

/**
 * Tests for optional forum URL slug translations.
 *
 * @group admin
 * @group settings
 */
class BBP_Tests_Admin_Slug_Translations extends BBP_UnitTestCase {

	private $saved_options = array();
	private $bbp_options;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'bbp_admin' ) ) {
			require_once BBP_PLUGIN_DIR . 'includes/admin/actions.php';
		}
		bbp_admin();
		$this->bbp_options = bbpress()->options;
		bbpress()->options = array();
	}

	public function tearDown(): void {
		foreach ( $this->saved_options as $option => $saved ) {
			if ( $saved['exists'] ) {
				update_option( $option, $saved['value'] );
			} else {
				delete_option( $option );
			}
		}
		bbpress()->options = $this->bbp_options;
		parent::tearDown();
	}

	private function set_option( $option, $value ) {
		if ( ! isset( $this->saved_options[ $option ] ) ) {
			$missing = new stdClass();
			$current = get_option( $option, $missing );
			$this->saved_options[ $option ] = array(
				'exists' => ( $missing !== $current ),
				'value'  => $current,
			);
		}
		delete_option( $option );
		add_option( $option, $value );
	}

	private function translate_slugs( $translations ) {
		return function( $translation, $text, $context, $domain ) use ( $translations ) {
			if ( 'bbpress' === $domain && 'default URL slug' === $context && isset( $translations[ $text ] ) ) {
				return $translations[ $text ];
			}
			return $translation;
		};
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_slug_translation_suggestions
	 */
	public function test_suggestions_wait_for_translations_and_preserve_custom_slugs() {
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_root_slug', 'forums' );
		$this->set_option( '_bbp_topic_slug', 'my-topics' );
		$this->assertSame( array(), bbp_admin_slug_translation_suggestions() );

		$filter = $this->translate_slugs( array( 'forums' => 'Foren', 'topic' => 'Thema' ) );
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		try {
			$suggestions = bbp_admin_slug_translation_suggestions();
			$this->assertSame( 'forums', $suggestions['_bbp_root_slug']['current'] );
			$this->assertSame( 'foren', $suggestions['_bbp_root_slug']['new'] );
			$this->assertArrayNotHasKey( '_bbp_topic_slug', $suggestions );
			$this->assertSame( 'forums', get_option( '_bbp_root_slug' ) );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_slug_translation_preview
	 */
	public function test_preview_includes_changed_unchanged_and_custom_slugs() {
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_root_slug', 'forums' );
		$this->set_option( '_bbp_topic_slug', 'my-topics' );

		$filter = $this->translate_slugs( array( 'forums' => 'Foren', 'topic' => 'Thema' ) );
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		try {
			$suggestions = bbp_admin_slug_translation_suggestions();
			$preview     = bbp_admin_slug_translation_preview( $suggestions );

			$this->assertCount( 16, $preview );
			$this->assertSame( array( 'current' => 'forums', 'new' => 'foren' ), $preview['_bbp_root_slug'] );
			$this->assertSame( array( 'current' => 'forum', 'new' => 'forum' ), $preview['_bbp_forum_slug'] );
			$this->assertSame( array( 'current' => 'my-topics', 'new' => 'my-topics' ), $preview['_bbp_topic_slug'] );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_slug_translation_state
	 */
	public function test_dismissal_resets_after_site_language_changes() {
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_slug_translation_prompt', array(
			'locale'    => 'en_US',
			'dismissed' => true,
			'applied'   => array( '_bbp_root_slug' => 'foren' ),
		) );
		$this->set_option( 'WPLANG', 'de_DE' );

		$state = bbp_admin_slug_translation_state();
		$this->assertSame( 'de_DE', $state['locale'] );
		$this->assertFalse( $state['dismissed'] );
		$this->assertSame( 'foren', $state['applied']['_bbp_root_slug'] );
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_slug_translation_state
	 */
	public function test_do_not_ask_again_survives_site_language_changes() {
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_slug_translation_prompt', array(
			'locale'    => 'en_US',
			'disabled'  => true,
			'dismissed' => true,
		) );
		$this->set_option( 'WPLANG', 'de_DE' );

		$state = bbp_admin_slug_translation_state();
		$this->assertSame( 'de_DE', $state['locale'] );
		$this->assertTrue( $state['disabled'] );
		$this->assertSame( array(), bbp_admin_slug_translation_prompt() );
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_slug_translation_help_link
	 */
	public function test_manual_review_link_moves_into_help_after_dismissal() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		get_user_by( 'id', $user_id )->add_cap( bbp_admin()->minimum_capability );
		$this->set_current_user( $user_id );
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_root_slug', 'forums' );
		$this->set_option( '_bbp_slug_translation_prompt', array( 'locale' => 'en_US', 'dismissed' => false ) );
		$this->assertTrue( current_user_can( 'bbp_settings_page' ) );

		$filter = $this->translate_slugs( array( 'forums' => 'Foren' ) );
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		try {
			$this->assertStringContainsString( ' hidden', bbp_admin_slug_translation_help_link() );

			$this->set_option( '_bbp_slug_translation_prompt', array( 'locale' => 'en_US', 'dismissed' => true ) );
			$this->assertNotEmpty( bbp_admin_slug_translation_suggestions() );
			$this->assertStringContainsString( 'href="#bbp-slug-translation"', bbp_admin_slug_translation_help_link() );
			$this->assertStringNotContainsString( ' hidden', bbp_admin_slug_translation_help_link() );

			remove_filter( 'gettext_with_context', $filter, 10 );
			$this->assertSame( '', bbp_admin_slug_translation_help_link() );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP3794
	 * @covers ::bbp_admin_slug_translation_panel
	 */
	public function test_panel_skips_a_proposal_that_cannot_be_encoded() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		get_user_by( 'id', $user_id )->add_cap( bbp_admin()->minimum_capability );
		$this->set_current_user( $user_id );
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_root_slug', INF );
		$this->set_option( '_bbp_slug_translation_prompt', array(
			'locale'  => 'en_US',
			'applied' => array( '_bbp_root_slug' => INF ),
		) );

		$filter = $this->translate_slugs( array( 'forums' => 'Foren' ) );
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		try {
			$suggestions = bbp_admin_slug_translation_suggestions( array( '_bbp_root_slug' => INF ) );
			$this->assertArrayHasKey( '_bbp_root_slug', $suggestions );
			$this->assertFalse( wp_json_encode( $suggestions ) );

			ob_start();
			bbp_admin_slug_translation_panel();
			$this->assertSame( '', ob_get_clean() );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_slug_translation_conflicts
	 */
	public function test_conflicting_translation_cannot_be_applied() {
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_root_slug', 'forums' );
		$filter = $this->translate_slugs( array( 'forum' => 'forums' ) );
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		try {
			$suggestions = bbp_admin_slug_translation_suggestions();
			$this->assertContains( '_bbp_forum_slug', bbp_admin_slug_translation_conflicts( $suggestions ) );
			$this->assertFalse( bbp_admin_apply_slug_translations( $suggestions ) );
			$this->assertSame( 'forum', get_option( '_bbp_forum_slug', 'forum' ) );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}

	/**
	 * @ticket BBP2976
	 * @covers ::bbp_admin_apply_slug_translations
	 */
	public function test_acceptance_saves_only_suggested_slugs() {
		$this->set_option( 'WPLANG', 'en_US' );
		$this->set_option( '_bbp_root_slug', 'forums' );
		$this->set_option( '_bbp_topic_slug', 'my-topics' );
		$filter = $this->translate_slugs( array( 'forums' => 'Foren', 'topic' => 'Thema' ) );
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		try {
			$suggestions = bbp_admin_slug_translation_suggestions();
			$this->assertTrue( bbp_admin_apply_slug_translations( $suggestions ) );
			$this->assertSame( 'foren', get_option( '_bbp_root_slug' ) );
			$this->assertSame( 'my-topics', get_option( '_bbp_topic_slug' ) );
		} finally {
			remove_filter( 'gettext_with_context', $filter, 10 );
		}
	}
}
