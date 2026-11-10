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
		$anchor = '<a href="%1$s"> @%2$s</a>';
		$name   = $user->user_nicename;
		$link   = sprintf( $anchor, esc_url( $url ), esc_html( $name ) );
		$link   = bbp_rel_nofollow( $link );

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
		$at_name_in_anchor_final = sprintf( "Send messages to <a href='@%s'> @%s</a>", $name, $name );
		$this->assertEquals( $at_name_in_anchor_final, bbp_make_clickable( $at_name_in_anchor ) );

		// mentions inside links (with an external match) should not be replaced inside href ever
		$at_name_in_anchor_matched       = sprintf( "Send messages to <a href='@%s'>@%s</a> @%s", $name, $name, $name );
		$at_name_in_anchor_matched_final = sprintf( "Send messages to <a href='@%s'> @%s</a> %s", $name, $name, $link );
		$this->assertEquals( $at_name_in_anchor_matched_final, bbp_make_clickable( $at_name_in_anchor_matched ) );

		// mentions inside attributes, should not be replaced
		$text                  = '<a href=" @%s ................................ @%s @%s">@%s</a>';
		$at_name_in_attr       = sprintf( $text, $name, $name, $name, $name );
		$at_name_in_attr_final = sprintf( '<a href=" @%1$s ................................ @%1$s @%1$s"> @%1$s</a>', $name );
		$this->assertEquals( $at_name_in_attr_final, bbp_make_clickable( $at_name_in_attr ) );

		// mentions hugged by brackets, should not be replaced
		$text                    = "<@%s>";
		$at_name_in_hugged       = sprintf( $text, $name );
		$at_name_in_hugged_final = sprintf( $text, $name );
		$this->assertEquals( $at_name_in_hugged_final, bbp_make_clickable( $at_name_in_hugged ) );

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
		$anchor = '<a href="%1$s"> @%2$s</a>';
		$name   = $user->user_nicename;
		$link   = sprintf( $anchor, esc_url( $url ), esc_html( $user->user_nicename ) );
		$link   = bbp_rel_nofollow( $link );

		// mentions inside links (with an external match) should not be replaced inside href ever
		$at_name_in_href_matched       = sprintf( "Send messages to <a href='@%s'>Foo Bar Baz</a>@%s @%s", $name, $name, $name );
		$at_name_in_href_matched_final = sprintf( "Send messages to <a href='@%s'>Foo Bar Baz</a>%s %s", $name, $link, $link );
		$this->assertEquals( $at_name_in_href_matched_final, bbp_make_clickable( $at_name_in_href_matched ) );

		// mentions inside linked text (with an external match) should not be linked
		$at_name_in_link_matched       = sprintf( "<a href='https://twitter.com/%s'>@%s</a>@%s @%s", $name, $name, $name, $name );
		$at_name_in_link_matched_final = sprintf( "<a href='https://twitter.com/%s'> @%s</a>%s %s", $name, $name, $link, $link );
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

		// Don't link non-existent users
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
		$anchor_1 = '<a href="%1$s"> @%2$s</a>';
		$name_1   = $user_1->user_nicename;
		$link_1   = sprintf( $anchor_1, esc_url( $url_1 ), esc_html( $name_1 ) );
		$link_1   = bbp_rel_nofollow( $link_1 );

		$user_2   = get_userdata( $u2 );
		$url_2    = bbp_get_user_profile_url( $user_2->ID );
		$anchor_2 = '<a href="%1$s"> @%2$s</a>';
		$name_2   = $user_2->user_nicename;
		$link_2   = sprintf( $anchor_2, esc_url( $url_2 ), esc_html( $name_2 ) );
		$link_2   = bbp_rel_nofollow( $link_2 );

		// Multiples
		$at_name_in_mailto       = sprintf( "Send messages to @%s, @%s.", $name_1, $name_2 );
		$at_name_in_mailto_final = sprintf( "Send messages to %s, %s.",   $link_1, $link_2 );
		$this->assertEquals( $at_name_in_mailto_final, bbp_make_clickable( $at_name_in_mailto ) );

		// Two names separated by `&`
		$at_names_inbetween_ampersand       = sprintf( "Oh hi @%s & @%s.", $name_1, $name_2 );
		$at_names_inbetween_ampersand_final = sprintf( "Oh hi %s & %s.",   $link_1, $link_2 );
		$this->assertEquals( $at_names_inbetween_ampersand_final, bbp_make_clickable( $at_names_inbetween_ampersand ) );

		// Two names separated by `and`
		$at_names_inbetween_and       = sprintf( "Oh hi @%s and @%s.", $name_1, $name_2 );
		$at_names_inbetween_and_final = sprintf( "Oh hi %s and %s.",   $link_1, $link_2 );
		$this->assertEquals( $at_names_inbetween_and_final, bbp_make_clickable( $at_names_inbetween_and ) );

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
		$anchor_1 = '<a href="%1$s"> @%2$s</a>';
		$name_1   = $user_1->user_nicename;
		$link_1   = sprintf( $anchor_1, esc_url( $url_1 ), esc_html( $name_1 ) );
		$link_1   = bbp_rel_nofollow( $link_1 );

		$user_2   = get_userdata( $u2 );
		$url_2    = bbp_get_user_profile_url( $user_2->ID );
		$anchor_2 = '<a href="%1$s"> @%2$s</a>';
		$name_2   = $user_2->user_nicename;
		$link_2   = sprintf( $anchor_2, esc_url( $url_2 ), esc_html( $name_2 ) );
		$link_2   = bbp_rel_nofollow( $link_2 );

		// Multiples
		$at_name_in_mailto       = sprintf( "Send messages to @%s, @non1, @%s, @non2.", $name_1, $name_2 );
		$at_name_in_mailto_final = sprintf( "Send messages to %s, @non1, %s, @non2.",   $link_1, $link_2 );
		$this->assertEquals( $at_name_in_mailto_final, bbp_make_clickable( $at_name_in_mailto ) );

		remove_filter( 'bbp_make_mentions_clickable_classes', '__return_empty_array' );
	}
}

/**
 * @group common
 * @group formatting
 */
class BBP_Tests_Common_Formatting extends BBP_UnitTestCase {

	/**
	 * Edit-content filters that remove KSES for users with unfiltered HTML.
	 *
	 * @return array
	 */
	public function data_edit_content_filters() {
		return array(
			'forum' => array( 'bbp_edit_forum_pre_content' ),
			'topic' => array( 'bbp_edit_topic_pre_content' ),
			'reply' => array( 'bbp_edit_reply_pre_content' )
		);
	}

	/**
	 * Code containing literal backticks that should survive an edit round trip.
	 *
	 * @return array
	 */
	public function data_code_with_literal_backticks() {
		$svg = '&lt;svg onload="marker"&gt;&lt;/svg&gt;';

		return array(
			'inline' => array(
				'<code>a`b' . $svg . '</code>',
				'<code>a`b' . $svg . '</code>'
			),
			'leading backtick' => array(
				'<code>`a' . $svg . '</code>',
				'<code>`a' . $svg . '</code>'
			),
			'trailing backtick' => array(
				'<code>a' . $svg . '`</code>',
				'<code>a' . $svg . '`</code>'
			),
			'block' => array(
				"<pre><code>\na`b{$svg}\n</code></pre>",
				"<pre><code>\na`b{$svg}\n</code></pre>"
			),
			'multiple blocks' => array(
				'<code>safe&lt;em&gt;</code> <code>a`b' . $svg . '</code>',
				'`safe<em>` <code>a`b' . $svg . '</code>'
			),
			'paragraph and line break markup' => array(
				'<code>a`b<br /><p>c</p>' . $svg . '</code>',
				'<code>a`b<br /><p>c</p>' . $svg . '</code>'
			)
		);
	}

	/**
	 * Apply an edit-content filter as an unfiltered HTML user.
	 *
	 * @param string $filter Edit-content filter.
	 * @param string $form   Form content.
	 * @return string
	 */
	private function filter_unfiltered_edit_content( $filter, $form ) {
		remove_filter( $filter, 'bbp_encode_bad', 10 );
		remove_filter( $filter, 'bbp_filter_kses', 30 );

		try {
			return apply_filters( $filter, $form );
		} finally {
			add_filter( $filter, 'bbp_encode_bad', 10 );
			add_filter( $filter, 'bbp_filter_kses', 30 );
		}
	}

	/**
	 * @dataProvider data_code_with_literal_backticks
	 *
	 * @covers ::bbp_code_trick
	 * @covers ::bbp_code_trick_reverse
	 * @covers ::bbp_decode_callback
	 */
	public function test_privileged_edits_preserve_code_containing_backticks( $stored, $expected_form ) {
		$form    = bbp_code_trick_reverse( $stored );
		$filters = $this->data_edit_content_filters();

		$this->assertSame( $expected_form, $form );

		foreach ( $filters as $filter ) {
			$filter = $filter[0];

			$this->assertSame( 10, has_filter( $filter, 'bbp_encode_bad' ) );
			$this->assertSame( 20, has_filter( $filter, 'bbp_code_trick' ) );
			$this->assertSame( 30, has_filter( $filter, 'bbp_filter_kses' ) );

			$resaved = $this->filter_unfiltered_edit_content( $filter, $form );

			$this->assertSame( $stored, $resaved );
			$this->assertStringNotContainsString( '<svg', $resaved );
			$this->assertStringContainsString( '&lt;svg', $resaved );
		}
	}

	/**
	 * @covers ::bbp_code_trick
	 * @covers ::bbp_code_trick_reverse
	 * @covers ::bbp_decode_callback
	 */
	public function test_multiple_backticks_remain_inert_after_privileged_edit() {
		$stored = '<code>a`b&lt;svg onload="marker"&gt;&lt;/svg&gt;`c</code>';
		$form   = bbp_code_trick_reverse( $stored );

		$this->assertSame( $stored, $form );

		foreach ( $this->data_edit_content_filters() as $filter ) {
			$resaved = $this->filter_unfiltered_edit_content( $filter[0], $form );

			$this->assertStringNotContainsString( '<svg', $resaved );
			$this->assertStringContainsString( '&lt;svg', $resaved );
		}
	}

	/**
	 * @covers ::bbp_code_trick
	 * @covers ::bbp_code_trick_reverse
	 * @covers ::bbp_decode_callback
	 */
	public function test_entity_encoded_backtick_remains_inert_after_privileged_edit() {
		$stored = '<code>a&#96;b&lt;svg onload="marker"&gt;&lt;/svg&gt;</code>';
		$form   = bbp_code_trick_reverse( $stored );

		$this->assertSame( 2, substr_count( $form, '`' ) );

		foreach ( $this->data_edit_content_filters() as $filter ) {
			$resaved = $this->filter_unfiltered_edit_content( $filter[0], $form );

			$this->assertStringNotContainsString( '<svg', $resaved );
			$this->assertStringContainsString( '&lt;svg', $resaved );
		}
	}

	/**
	 * @covers ::bbp_code_trick
	 * @covers ::bbp_code_trick_reverse
	 * @covers ::bbp_decode_callback
	 */
	public function test_code_without_backticks_still_uses_shorthand() {
		$stored = '<code>safe&lt;em&gt;</code>';
		$form   = bbp_code_trick_reverse( $stored );

		$this->assertSame( '`safe<em>`', $form );
		$this->assertSame( $stored, bbp_code_trick( $form ) );
	}
}

/**
 * @group common
 * @group functions
 * @group bbp_rel_nofollow
 */
class BBP_Tests_Common_Functions_Rel_Nofollow extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_rel_nofollow
	 */
	public function test_bbp_rel_nofollow_adds_relationship_to_external_link() {
		$link = '<a href="https://example.com/">Link</a>';

		$this->assertSame( '<a href="https://example.com/" rel="nofollow">Link</a>', bbp_rel_nofollow( $link ) );
	}

	/**
	 * @covers ::bbp_rel_nofollow
	 */
	public function test_bbp_rel_nofollow_preserves_internal_link() {
		$link = '<a href="' . home_url( '/forums/' ) . '">Forums</a>';

		$this->assertSame( $link, bbp_rel_nofollow( $link ) );
	}

	/**
	 * @covers ::bbp_rel_nofollow
	 */
	public function test_bbp_rel_nofollow_merges_existing_relationship() {
		$link = '<a href="https://example.com/" rel="ugc">Link</a>';

		$this->assertSame( '<a href="https://example.com/" rel="ugc nofollow">Link</a>', bbp_rel_nofollow( $link ) );
	}

	/**
	 * @covers ::bbp_rel_nofollow
	 */
	public function test_bbp_rel_nofollow_preserves_valueless_attribute() {
		$link = '<a href="https://example.com/" data-example>Link</a>';

		$this->assertSame( '<a href="https://example.com/" data-example rel="nofollow">Link</a>', bbp_rel_nofollow( $link ) );
	}

	/**
	 * @covers ::bbp_rel_nofollow
	 */
	public function test_bbp_rel_nofollow_does_not_decode_escaped_attribute_characters() {
		$link   = '<a rel="nofollow x=\\x22\\x3Eimg\\x3Cimg src=x onerror=alert(1)\\x3E\\x3Cb\\x3Ex\\x3C/b\\x3E" href="https://example.com/">Link</a>';
		$output = bbp_rel_nofollow( $link );

		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringContainsString( '\\x22', $output );
		$this->assertStringContainsString( 'rel="nofollow', $output );
	}
}
