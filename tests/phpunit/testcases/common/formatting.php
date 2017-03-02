<?php

/**
 * @group common
 * @group functions
 * @group bbp_make_clickable
 */
class BBP_Tests_Common_Functions_Make_Clickable extends BBP_UnitTestCase {

	/**
	 * @group bbp_make_clickable_misses
	 * @group bbp_make_clickable_single
	 * @covers ::bbp_make_clickable
	 */
	public function test_bbp_make_clickable_single_mention_misses() {
		$u1 = $this->factory->user->create( array(
			'user_login'    => 'foobarbaz',
			'user_nicename' => 'foobarbaz',
		) );

		add_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );

		// Create the link to the user's profile
		$user   = get_userdata( $u1 );
		$url    = bbp_get_user_profile_url( $user->ID );
		$anchor = '<a href="%1$s" class="">@%2$s</a>';
		$name   = $user->user_nicename;
		$link   = sprintf( $anchor, esc_url( $url ), esc_html( $name ) );

		// mentions inside links, should not be replaced
		$text                    = "Send messages to <a href='mailto:mail@%s.com'>Foo Bar Baz</a>";
		$at_name_in_mailto       = sprintf( $text, $name );
		$at_name_in_mailto_final = sprintf( $text, $name );
		$this->assertEquals( $at_name_in_mailto_final, bbp_make_clickable( $at_name_in_mailto ) );

		// mentions inside links, should not be replaced
		$text                  = "Send messages to <a href='@%s'>Foo Bar Baz</a>";
		$at_name_in_href       = sprintf( $text, $name );
		$at_name_in_href_final = sprintf( $text, $name );
		$this->assertEquals( $at_name_in_href_final, bbp_make_clickable( $at_name_in_href ) );

		// mentions inside links (with an external match) should not be replaced inside href ever
		$at_name_in_anchor       = sprintf( "Send messages to <a href='@%s'>@%s</a>", $name, $name );
		$at_name_in_anchor_final = sprintf( "Send messages to <a href='@%s'>@%s</a>", $name, $name );
		$this->assertEquals( $at_name_in_anchor_final, bbp_make_clickable( $at_name_in_anchor ) );

		// mentions inside links (with an external match) should not be replaced inside href ever
		$at_name_in_anchor_matched       = sprintf( "Send messages to <a href='@%s'>@%s</a> @%s", $name, $name, $name );
		$at_name_in_anchor_matched_final = sprintf( "Send messages to <a href='@%s'>@%s</a> %s", $name, $name, $link );
		$this->assertEquals( $at_name_in_anchor_matched_final, bbp_make_clickable( $at_name_in_anchor_matched ) );

		// mentions inside attributes, should not be replaced
		$text                  = '<a href=" @%s ................................ @%s @%s">@%s</a>';
		$at_name_in_attr       = sprintf( $text, $name, $name, $name, $name );
		$at_name_in_attr_final = sprintf( $text, $name, $name, $name, $name );
		$this->assertEquals( $at_name_in_attr_final, bbp_make_clickable( $at_name_in_attr ) );

		// mentions hugged by brackets, should not be replaced
		$text                    = "<@%s>";
		$at_name_in_hugged       = sprintf( $text, $name );
		$at_name_in_hugged_final = sprintf( $text, $name );
		$this->assertEquals( $at_name_in_hugged_final, bbp_make_clickable( $at_name_in_hugged ) );

		// mentions between brackets, should not be replaced even when linked after
		$at_name_between       = sprintf( "foo < %s > @%s", $name, $link );
		$at_name_between_final = sprintf( "foo < %s > @%s", $name, $link );
		$this->assertEquals( $at_name_between_final, bbp_make_clickable( $at_name_between ) );

		remove_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );
	}

	/**
	 * @group bbp_make_clickable_hits
	 * @group bbp_make_clickable_single
	 * @covers ::bbp_make_clickable
	 */
	public function test_bbp_make_clickable_single_mention_hits() {
		$u1 = $this->factory->user->create( array(
			'user_login'    => 'foobarbaz',
			'user_nicename' => 'foobarbaz',
		) );

		add_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );

		// Create the link to the user's profile
		$user   = get_userdata( $u1 );
		$url    = bbp_get_user_profile_url( $user->ID );
		$anchor = '<a href="%1$s" class="">@%2$s</a>';
		$name   = $user->user_nicename;
		$link   = sprintf( $anchor, esc_url( $url ), esc_html( $user->user_nicename ) );

		// mentions inside links (with an external match) should not be replaced inside href ever
		$at_name_in_href_matched       = sprintf( "Send messages to <a href='@%s'>Foo Bar Baz</a>@%s @%s", $name, $name, $name );
		$at_name_in_href_matched_final = sprintf( "Send messages to <a href='@%s'>Foo Bar Baz</a>%s %s", $name, $link, $link );
		$this->assertEquals( $at_name_in_href_matched_final, bbp_make_clickable( $at_name_in_href_matched ) );

		// mentions inside linked text (with an external match) should not be linked
		$at_name_in_link_matched       = sprintf( "<a href='https://twitter.com/%s'>@%s</a>@%s @%s", $name, $name, $name, $name );
		$at_name_in_link_matched_final = sprintf( "<a href='https://twitter.com/%s'>@%s</a>%s %s", $name, $name, $link, $link );
		$this->assertEquals( $at_name_in_link_matched_final, bbp_make_clickable( $at_name_in_link_matched ) );

		// mentions after greater-than bracket, should be replaced
		$at_name_after_greater_than       = sprintf( "foo > @%s", $name );
		$at_name_after_greater_than_final = sprintf( "foo > %s", $link );
		$this->assertEquals( $at_name_after_greater_than_final, bbp_make_clickable( $at_name_after_greater_than ) );

		// mentions after less-than bracket, should be replaced
		$at_name_after_less_than       = sprintf( "foo < @%s", $name );
		$at_name_after_less_than_final = sprintf( "foo < %s", $link );
		$this->assertEquals( $at_name_after_less_than_final, bbp_make_clickable( $at_name_after_less_than ) );

		// mentions at end of normal text, should be replaced
		$at_name_at_end       = sprintf( 'Hello @%s', $name );
		$at_name_at_end_final = sprintf( 'Hello %s', $link );
		$this->assertEquals( $at_name_at_end_final, bbp_make_clickable( $at_name_at_end ) );

		// mentions at start of normal text, should be replaced
		$at_name_at_start       = sprintf( '@%s, hello', $name );
		$at_name_at_start_final = sprintf( '%s, hello', $link );
		$this->assertEquals( $at_name_at_start_final, bbp_make_clickable( $at_name_at_start ) );

		// mention is all text, should be replaced
		$at_name_is_text       = sprintf( '@%s', $name );
		$at_name_is_text_final = sprintf( '%s', $link );
		$this->assertEquals( $at_name_is_text_final, bbp_make_clickable( $at_name_is_text ) );

		// mention followed by colon, should be replaced
		$at_name_colon       = sprintf( 'Hey @%s: hello', $name );
		$at_name_colon_final = sprintf( 'Hey %s: hello', $link );
		$this->assertEquals( $at_name_colon_final, bbp_make_clickable( $at_name_colon ) );

		// mention followed by comma, should be replaced
		$at_name_comma       = sprintf( 'Hey @%s, hello', $name );
		$at_name_comma_final = sprintf( 'Hey %s, hello', $link );
		$this->assertEquals( $at_name_comma_final, bbp_make_clickable( $at_name_comma ) );

		// Don"t link non-existent users
		$text = "Don't link @non @existent @users";
		$this->assertSame( $text, bbp_make_clickable( $text ) );

		remove_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );
	}

	/**
	 * @group bbp_make_clickable_hits
	 * @group bbp_make_clickable_multiple
	 * @covers ::bbp_make_clickable
	 */
	public function test_bbp_make_clickable_multiple_mention_hits() {
		$u1 = $this->factory->user->create( array(
			'user_login'    => 'foobarbaz',
			'user_nicename' => 'foobarbaz',
		) );

		$u2 = $this->factory->user->create( array(
			'user_login'    => 'foo2',
			'user_nicename' => 'foo2',
		) );

		add_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );

		// Create the link to the user's profile
		$user_1   = get_userdata( $u1 );
		$url_1    = bbp_get_user_profile_url( $user_1->ID );
		$anchor_1 = '<a href="%1$s" class="">@%2$s</a>';
		$name_1   = $user_1->user_nicename;
		$link_1   = sprintf( $anchor_1, esc_url( $url_1 ), esc_html( $name_1 ) );

		$user_2   = get_userdata( $u2 );
		$url_2    = bbp_get_user_profile_url( $user_2->ID );
		$anchor_2 = '<a href="%1$s" class="">@%2$s</a>';
		$name_2   = $user_2->user_nicename;
		$link_2   = sprintf( $anchor_2, esc_url( $url_2 ), esc_html( $name_2 ) );

		// Multiples
		$at_name_in_mailto       = sprintf( "Send messages to @%s, @%s.", $link_1, $link_2 );
		$at_name_in_mailto_final = sprintf( "Send messages to @%s, @%s.", $link_1, $link_2 );
		$this->assertEquals( $at_name_in_mailto_final, bbp_make_clickable( $at_name_in_mailto ) );

		remove_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );
	}

	/**
	 * @group bbp_make_clickable_misses
	 * @group bbp_make_clickable_multiple
	 * @covers ::bbp_make_clickable
	 */
	public function test_bbp_make_clickable_multiple_mention_misses() {
		$u1 = $this->factory->user->create( array(
			'user_login'    => 'foobarbaz',
			'user_nicename' => 'foobarbaz',
		) );

		$u2 = $this->factory->user->create( array(
			'user_login'    => 'foo2',
			'user_nicename' => 'foo2',
		) );

		add_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );

		// Create the link to the user's profile
		$user_1   = get_userdata( $u1 );
		$url_1    = bbp_get_user_profile_url( $user_1->ID );
		$anchor_1 = '<a href="%1$s" class="">@%2$s</a>';
		$name_1   = $user_1->user_nicename;
		$link_1   = sprintf( $anchor_1, esc_url( $url_1 ), esc_html( $name_1 ) );

		$user_2   = get_userdata( $u2 );
		$url_2    = bbp_get_user_profile_url( $user_2->ID );
		$anchor_2 = '<a href="%1$s" class="">@%2$s</a>';
		$name_2   = $user_2->user_nicename;
		$link_2   = sprintf( $anchor_2, esc_url( $url_2 ), esc_html( $name_2 ) );

		// Multiples
		$at_name_in_mailto       = sprintf( "Send messages to @%s, @non1, @%s, @non2.", $link_1, $link_2 );
		$at_name_in_mailto_final = sprintf( "Send messages to @%s, @non1, @%s, @non2.", $link_1, $link_2 );
		$this->assertEquals( $at_name_in_mailto_final, bbp_make_clickable( $at_name_in_mailto ) );

		remove_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );
	}
}
