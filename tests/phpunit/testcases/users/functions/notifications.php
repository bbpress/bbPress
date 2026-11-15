<?php

/**
 * Tests for user notification preferences.
 *
 * @group users
 * @group notifications
 * @ticket BBP3792
 */
class BBP_Tests_Users_Functions_Notifications extends BBP_UnitTestCase {

	private $user_id;

	public function setUp(): void {
		parent::setUp();
		$this->user_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
	}

	public function tearDown(): void {
		unset( $_POST['_bbp_notification_nonce'], $_POST['bbp_notification_preferences'] );
		$this->set_current_user( 0 );
		parent::tearDown();
	}

	public function test_missing_and_explicit_choices_use_one_global_row() {
		$forum_type = 'subscriptions:post:' . bbp_get_forum_post_type();
		$tag_type   = 'subscriptions:term:' . bbp_get_topic_tag_tax_id();

		$this->assertNull( bbp_get_user_notification_preference( $this->user_id, 'mentions' ) );
		$this->assertFalse( bbp_is_user_notification_enabled( $this->user_id, 'mentions' ) );
		$this->assertTrue( bbp_is_user_notification_enabled( $this->user_id, $forum_type ) );
		$this->assertTrue( bbp_is_user_notification_enabled( $this->user_id, $tag_type ) );
		$this->assertFalse( bbp_get_user_notification_default( $this->user_id, 'mentions' ) );
		$this->assertTrue( bbp_get_user_notification_default( $this->user_id, $forum_type ) );

		bbp_update_user_notification_preferences( $this->user_id, array( 'mentions' => true, $forum_type => false ) );
		$this->assertSame( array( 'mentions' => true, $forum_type => false ), get_user_meta( $this->user_id, '_bbp_notification_preferences', true ) );
		$this->assertTrue( bbp_get_user_notification_preference( $this->user_id, 'mentions' ) );
		$this->assertFalse( bbp_get_user_notification_preference( $this->user_id, $forum_type ) );
		$this->assertFalse( bbp_is_user_notification_enabled( $this->user_id, $forum_type ) );
		$this->assertTrue( bbp_get_user_notification_default( $this->user_id, $forum_type ) );
		$this->assertTrue( bbp_is_user_notification_enabled( $this->user_id, $tag_type ) );

		bbp_update_user_notification_preferences( $this->user_id, array( 'mentions' => null ) );
		$this->assertSame( array( $forum_type => false ), get_user_meta( $this->user_id, '_bbp_notification_preferences', true ) );
		$this->assertNull( bbp_get_user_notification_preference( $this->user_id, 'mentions' ) );
		bbp_update_user_notification_preferences( $this->user_id, array( $forum_type => null ) );
		$this->assertFalse( metadata_exists( 'user', $this->user_id, '_bbp_notification_preferences' ) );
	}

	public function test_inherited_value_applies_only_to_an_unset_choice() {
		$callback = function( $inherited, $user_id, $type ) {
			return ( 'mentions' === $type ) ? true : $inherited;
		};
		add_filter( 'bbp_get_user_notification_inherited_preference', $callback, 10, 3 );

		try {
			$this->assertTrue( bbp_is_user_notification_enabled( $this->user_id, 'mentions' ) );
			$this->assertTrue( bbp_get_user_notification_default( $this->user_id, 'mentions' ) );
			bbp_update_user_notification_preferences( $this->user_id, array( 'mentions' => false ) );
			$this->assertFalse( bbp_is_user_notification_enabled( $this->user_id, 'mentions' ) );
			$this->assertTrue( bbp_get_user_notification_default( $this->user_id, 'mentions' ) );
		} finally {
			remove_filter( 'bbp_get_user_notification_inherited_preference', $callback, 10 );
		}
	}

	public function test_unshipped_mention_key_is_not_a_fallback() {
		update_user_meta( $this->user_id, '_bbp_mention_email', 'yes' );
		$this->assertNull( bbp_get_user_notification_preference( $this->user_id, 'mentions' ) );
		$this->assertFalse( bbp_is_user_mention_email_enabled( $this->user_id ) );
	}

	public function test_registered_type_can_use_its_own_default() {
		$callback = function( $types ) {
			$types['digest'] = array( 'label' => 'Digest emails', 'default' => true );
			return $types;
		};
		add_filter( 'bbp_get_notification_types', $callback );

		try {
			$this->assertTrue( bbp_is_user_notification_enabled( $this->user_id, 'digest' ) );
			bbp_update_user_notification_preferences( $this->user_id, array( 'digest' => false ) );
			$this->assertFalse( bbp_is_user_notification_enabled( $this->user_id, 'digest' ) );
			$this->set_current_user( $this->user_id );
			ob_start();
			bbp_user_notification_settings( get_userdata( $this->user_id ) );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'name="bbp_notification_preferences[digest]"', $html );
		} finally {
			remove_filter( 'bbp_get_notification_types', $callback );
		}
	}

	public function test_registered_taxonomy_gets_its_own_email_preference() {
		$taxonomy = 'bbp_test_notification_taxonomy';
		register_taxonomy(
			$taxonomy,
			bbp_get_topic_post_type(),
			array(
				'bbp_subscriptions' => true,
				'labels'            => array( 'name' => 'Plugins' )
			)
		);

		try {
			$type = 'subscriptions:term:' . $taxonomy;
			$this->assertSame( 'Plugins emails', bbp_get_notification_types()[ $type ]['label'] );
			$this->assertTrue( bbp_is_user_notification_enabled( $this->user_id, $type ) );

			$this->set_current_user( $this->user_id );
			ob_start();
			bbp_user_notification_settings( get_userdata( $this->user_id ) );
			$html = ob_get_clean();
			$this->assertStringContainsString( 'name="bbp_notification_preferences[' . $type . ']"', $html );
		} finally {
			unregister_taxonomy( $taxonomy );
		}
	}

	public function test_unregistered_type_does_not_remove_notification_recipients() {
		$forum_type = 'subscriptions:post:' . bbp_get_forum_post_type();
		$callback = function( $types ) {
			unset( $types[ 'subscriptions:post:' . bbp_get_forum_post_type() ] );
			return $types;
		};
		add_filter( 'bbp_get_notification_types', $callback );

		try {
			$this->assertSame( array( $this->user_id ), bbp_filter_notification_user_ids( array( $this->user_id ), $forum_type ) );
		} finally {
			remove_filter( 'bbp_get_notification_types', $callback );
		}
	}

	public function test_profile_selects_save_yes_no_and_default() {
		$forum_type = 'subscriptions:post:' . bbp_get_forum_post_type();
		$tag_type   = 'subscriptions:term:' . bbp_get_topic_tag_tax_id();
		$this->set_current_user( $this->user_id );
		ob_start();
		bbp_user_notification_settings( get_userdata( $this->user_id ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="bbp_notification_preferences[mentions]"', $html );
		$this->assertStringContainsString( 'name="bbp_notification_preferences[' . $forum_type . ']"', $html );
		$this->assertStringContainsString( 'name="bbp_notification_preferences[' . $tag_type . ']"', $html );
		$this->assertSame( count( bbp_get_notification_types() ), substr_count( $html, 'value="default"' ) );
		$this->assertStringContainsString( 'Default (emails on)', $html );
		$this->assertStringContainsString( 'Default (emails off)', $html );
		$this->assertStringContainsString( 'Send emails', $html );
		$this->assertStringContainsString( 'No emails', $html );

		$_POST['_bbp_notification_nonce'] = wp_create_nonce( 'bbp-notification-preferences-' . $this->user_id );
		$_POST['bbp_notification_preferences'] = array( 'mentions' => 'yes', $forum_type => 'no' );
		bbp_save_user_notification_preferences( $this->user_id );
		$this->assertSame( array( 'mentions' => true, $forum_type => false ), get_user_meta( $this->user_id, '_bbp_notification_preferences', true ) );
		$this->assertSame( 'yes', bbp_get_user_notification_preference_choice( $this->user_id, 'mentions' ) );
		$this->assertSame( 'no', bbp_get_user_notification_preference_choice( $this->user_id, $forum_type ) );

		$_POST['bbp_notification_preferences'] = array( 'mentions' => 'default', $forum_type => 'default' );
		bbp_save_user_notification_preferences( $this->user_id );
		$this->assertFalse( metadata_exists( 'user', $this->user_id, '_bbp_notification_preferences' ) );
		$this->assertSame( 'default', bbp_get_user_notification_preference_choice( $this->user_id, 'mentions' ) );
	}

	public function test_template_part_uses_the_displayed_user_without_loader_arguments() {
		$forum_type = 'subscriptions:post:' . bbp_get_forum_post_type();
		$this->set_current_user( $this->user_id );
		$previous = bbpress()->displayed_user;
		bbpress()->displayed_user = get_userdata( $this->user_id );

		try {
			ob_start();
			load_template( bbp_locate_template( 'form-user-notification-preferences.php' ), false );
			$html = ob_get_clean();
		} finally {
			bbpress()->displayed_user = $previous;
		}

		$this->assertStringContainsString( 'name="bbp_notification_preferences[mentions]"', $html );
		$this->assertStringContainsString( 'name="bbp_notification_preferences[' . $forum_type . ']"', $html );

		$document = new DOMDocument();
		$document->loadHTML( $html );
		$xpath = new DOMXPath( $document );
		$this->assertSame( count( bbp_get_notification_types() ), $xpath->query( '//div/select[starts-with(@name, "bbp_notification_preferences[")]' )->length );
		$this->assertSame( 0, $xpath->query( '//p/select[starts-with(@name, "bbp_notification_preferences[")]' )->length );
		$this->assertSame( 0, $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " bbp-user-notification-preferences ")]' )->length );
	}

	public function test_explicit_user_does_not_change_the_displayed_user_after_rendering() {
		$other_id = $this->factory->user->create();
		$this->set_current_user( $this->user_id );
		bbp_update_user_notification_preferences( $this->user_id, array( 'mentions' => false ) );
		bbp_update_user_notification_preferences( $other_id, array( 'mentions' => true ) );
		$previous = bbpress()->displayed_user;
		bbpress()->displayed_user = get_userdata( $other_id );

		try {
			ob_start();
			bbp_user_notification_settings( get_userdata( $this->user_id ) );
			$html = ob_get_clean();
			$this->assertSame( $other_id, bbp_get_displayed_user_id() );
		} finally {
			bbpress()->displayed_user = $previous;
		}

		$document = new DOMDocument();
		$document->loadHTML( $html );
		$xpath = new DOMXPath( $document );
		$this->assertSame( 1, $xpath->query( '//select[@name="bbp_notification_preferences[mentions]"]/option[@value="no" and @selected]' )->length );
	}
}
