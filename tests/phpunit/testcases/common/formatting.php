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
		$anchor = '<a href="%1$s">@%2$s</a>';
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
		$anchor = '<a href="%1$s">@%2$s</a>';
		$name   = $user->user_nicename;
		$link   = sprintf( $anchor, esc_url( $url ), esc_html( $user->user_nicename ) );
		$link   = bbp_rel_nofollow( $link );

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

		// mentions surrounded by square brackets
		$at_name_square_brackets       = sprintf( '[@%s] hello', $name );
		$at_name_square_brackets_final = sprintf( '[%s] hello', $link );
		$this->assertEquals( $at_name_square_brackets_final, bbp_make_clickable( $at_name_square_brackets ) );

		// mentions surrounded by round brackets
		$at_name_round_brackets       = sprintf( '(@%s) hello', $name );
		$at_name_round_brackets_final = sprintf( '(%s) hello', $link );
		$this->assertEquals( $at_name_round_brackets_final, bbp_make_clickable( $at_name_round_brackets ) );

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
		$anchor_1 = '<a href="%1$s">@%2$s</a>';
		$name_1   = $user_1->user_nicename;
		$link_1   = sprintf( $anchor_1, esc_url( $url_1 ), esc_html( $name_1 ) );
		$link_1   = bbp_rel_nofollow( $link_1 );

		$user_2   = get_userdata( $u2 );
		$url_2    = bbp_get_user_profile_url( $user_2->ID );
		$anchor_2 = '<a href="%1$s">@%2$s</a>';
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
		$anchor_1 = '<a href="%1$s">@%2$s</a>';
		$name_1   = $user_1->user_nicename;
		$link_1   = sprintf( $anchor_1, esc_url( $url_1 ), esc_html( $name_1 ) );
		$link_1   = bbp_rel_nofollow( $link_1 );

		$user_2   = get_userdata( $u2 );
		$url_2    = bbp_get_user_profile_url( $user_2->ID );
		$anchor_2 = '<a href="%1$s">@%2$s</a>';
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
 * @group functions
 * @group bbp_rel_nofollow
 */
class BBP_Tests_Common_Functions_Rel_Nofollow extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_rel_nofollow
	 * @covers ::bbp_rel_nofollow_callback
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

/**
 * @group common
 * @group formatting
 */
class BBP_Tests_Common_Formatting extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_kses_allowed_tags
	 * @covers ::bbp_filter_kses
	 * @covers ::bbp_kses_data
	 */
	public function test_kses_helpers_define_filter_and_preserve_slashing_contracts() {
		$tags = bbp_kses_allowed_tags();
		$this->assertArrayHasKey( 'a', $tags );
		$this->assertArrayHasKey( 'href', $tags['a'] );
		$this->assertArrayHasKey( 'img', $tags );
		$this->assertArrayNotHasKey( 'script', $tags );

		$raw      = '<a href="https://example.com" onclick="bad()">Link</a><span>Text</span>';
		$expected = '<a href="https://example.com">Link</a>Text';
		$this->assertSame( $expected, bbp_kses_data( $raw ) );
		$this->assertSame( wp_slash( $expected ), bbp_filter_kses( wp_slash( $raw ) ) );
		$script = bbp_kses_data( '<script>alert(1)</script>' );
		$this->assertStringNotContainsString( '<script', $script );
		$this->assertStringNotContainsString( '</script', $script );
		$dangerous = bbp_kses_data( '<a href="javascript:alert(1)">Bad</a><img src="x" onerror="bad()">' );
		$this->assertStringNotContainsString( 'javascript:', $dangerous );
		$this->assertStringNotContainsString( 'onerror', $dangerous );

		$filter = function() {
			return array( 'mark' => array() );
		};
		add_filter( 'bbp_kses_allowed_tags', $filter );
		try {
			$this->assertSame( array( 'mark' => array() ), bbp_kses_allowed_tags() );
			$this->assertSame( '<mark>yes</mark>no', bbp_kses_data( '<mark>yes</mark><em>no</em>' ) );
		} finally {
			remove_filter( 'bbp_kses_allowed_tags', $filter );
		}
	}

	/**
	 * @covers ::bbp_code_trick
	 * @covers ::bbp_code_trick_reverse
	 * @covers ::bbp_encode_bad
	 * @covers ::bbp_encode_callback
	 * @covers ::bbp_decode_callback
	 * @covers ::bbp_encode_empty_callback
	 * @covers ::bbp_encode_normal_callback
	 */
	public function test_code_helpers_encode_restore_and_preserve_allowed_markup() {
		$source  = "Before `<b> & \"quoted\" 'single'</b>` after";
		$encoded = 'Before <code>&lt;b&gt; &amp; &quot;quoted&quot; &#039;single&#039;&lt;/b&gt;</code> after';
		$this->assertSame( $encoded, bbp_code_trick( $source ) );
		$this->assertSame( $source, bbp_code_trick_reverse( $encoded ) );
		$this->assertSame( '<code>x &amp; y</code>', bbp_encode_callback( array( '` x &amp; y `', '`', ' x &amp; y ' ) ) );
		$this->assertSame( '`<coded_p>x<coded_br />y</coded_p>`', bbp_decode_callback( array( '<code><p>x<br />y</p></code>', '<code>', '<p>x<br />y</p>', '</code>' ) ) );

		$block_source  = "`\n  indented\n\n\n\nline\n`";
		$block_encoded = "\n<pre><code>\n  indented\n\nline\n</code></pre>\n";
		$this->assertSame( $block_encoded, bbp_code_trick( $block_source ) );
		$this->assertSame( "\n`\n  indented\n\nline\n`\n", bbp_code_trick_reverse( $block_encoded ) );

		$content = '&lt;img src="x"&gt;';
		bbp_encode_empty_callback( $content, 0, 'img(?:\s.*?)?' );
		$this->assertSame( '<img src="x" />', $content );
		$content = '&lt;strong&gt;x&lt;/strong&gt;';
		bbp_encode_normal_callback( $content, 0, 'strong' );
		$this->assertSame( '<strong>x</strong>', $content );

		$bad = '<strong>yes</strong><img src="x"><a href="https://example.com">link</a><script>no</script> `<em>code</em>`';
		$this->assertSame( '<strong>yes</strong><img src="x" /><a href="https://example.com">link</a>&lt;script&gt;no&lt;/script&gt; `&lt;em&gt;code&lt;/em&gt;`', bbp_encode_bad( $bad ) );
	}

	/**
	 * @covers ::bbp_make_urls_clickable
	 * @covers ::bbp_make_ftps_clickable
	 * @covers ::bbp_make_emails_clickable
	 * @covers ::bbp_make_mentions_clickable
	 * @covers ::bbp_make_mentions_clickable_callback
	 */
	public function test_clickable_helpers_link_supported_targets_and_mentions() {
		$this->assertStringContainsString( 'href="https://example.com/path"', bbp_make_urls_clickable( ' https://example.com/path ' ) );
		$this->assertStringContainsString( 'href="http://www.example.com"', bbp_make_ftps_clickable( ' www.example.com ' ) );
		$this->assertStringContainsString( 'href="http://ftp.example.com"', bbp_make_ftps_clickable( ' ftp.example.com ' ) );
		$this->assertStringContainsString( 'href="mailto:user@example.com"', bbp_make_emails_clickable( ' user@example.com ' ) );

		$user_id = $this->factory->user->create(
			array(
				'user_login'    => 'mention-user',
				'user_nicename' => 'mention-user',
			)
		);
		$output = bbp_make_mentions_clickable( ' Hi @mention-user!' );
		$this->assertStringContainsString( esc_url( bbp_get_user_profile_url( $user_id ) ), $output );
		$this->assertStringContainsString( 'class="bbp-user-mention bbp-user-id-' . $user_id . '"', $output );
		$this->assertStringNotContainsString( 'rel="nofollow"', $output );
		$this->assertSame( ' @missing-user', bbp_make_mentions_clickable_callback( array( ' @missing-user', ' ', 'missing-user' ) ) );
		$this->assertSame( ' @', bbp_make_mentions_clickable_callback( array( ' @', ' ', '' ) ) );

		add_filter( 'bbp_core_is_user_spammer', '__return_true' );
		try {
			$this->assertSame( ' Hi @mention-user!', bbp_make_mentions_clickable( ' Hi @mention-user!' ) );
		} finally {
			remove_filter( 'bbp_core_is_user_spammer', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_number_not_negative
	 */
	public function test_number_not_negative_normalizes_inputs_and_filters_context() {
		$this->assertSame( 0, bbp_number_not_negative( -2 ) );
		$this->assertSame( 1234, bbp_number_not_negative( '1,234 posts' ) );
		$this->assertSame( 5, bbp_number_not_negative( '<b>5</b>' ) );
		$this->assertSame( 3, bbp_number_not_negative( 3.9 ) );
		$this->assertSame( 0, bbp_number_not_negative( null ) );
		$this->assertSame( 0, bbp_number_not_negative( array( 5 ) ) );

		$filter = function( $value, $integer, $normalized ) {
			$this->assertSame( 0, $value );
			$this->assertSame( -25, $integer );
			$this->assertSame( '-25', $normalized );
			return 7;
		};
		add_filter( 'bbp_number_not_negative', $filter, 10, 3 );
		try {
			$this->assertSame( 7, bbp_number_not_negative( '-25' ) );
		} finally {
			remove_filter( 'bbp_number_not_negative', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_format_user_display_name
	 */
	public function test_format_user_display_name_preserves_utf8_and_converts_latin1() {
		$this->assertSame( 'Renée', bbp_format_user_display_name( 'Renée' ) );
		$this->assertSame( "\xC3\x89", bbp_format_user_display_name( "\xC9" ) );
	}
}
