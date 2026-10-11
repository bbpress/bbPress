<?php
/**
 * Tests for the topics component topic template functions.
 *
 * @group topics
 * @group template
 * @group topic
 */
class BBP_Tests_Topics_Template_Topic extends BBP_UnitTestCase {

	/**
	 * Return the expected output for the active KSES parser.
	 *
	 * @param string $legacy   Expected output from the legacy parser.
	 * @param string $html_api Expected output from the HTML API parser.
	 * @return string
	 */
	private function get_expected_kses_output( $legacy, $html_api ) {
		return apply_filters( 'wp_kses_force_legacy_parser', true ) ? $legacy : $html_api;
	}

	/**
	 * @covers ::bbp_show_lead_topic
	 */
	public function test_bbp_show_lead_topic() {
		$this->assertFalse( bbp_show_lead_topic() );
		$this->assertTrue( bbp_show_lead_topic( true ) );
		$this->assertFalse( bbp_show_lead_topic( 0 ) );
	}

	/**
	 * @covers ::bbp_topic_id
	 * @covers ::bbp_get_topic_id
	 */
	public function test_bbp_get_topic_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_id = bbp_get_topic_id( $t );
		$this->assertSame( $t, $topic_id );
	}

	/**
	 * @covers ::bbp_get_topic
	 */
	public function test_bbp_get_topic() {
		$topic_id = $this->factory->topic->create();
		$forum_id = $this->factory->forum->create();

		$this->assertSame( $topic_id, bbp_get_topic( $topic_id )->ID );
		$this->assertSame( $topic_id, bbp_get_topic( get_post( $topic_id ) )->ID );
		$this->assertSame( $topic_id, bbp_get_topic( $topic_id, ARRAY_A )['ID'] );
		$this->assertNull( bbp_get_topic( $forum_id ) );
	}

	/**
	 * @covers ::bbp_topic_permalink
	 * @covers ::bbp_get_topic_permalink
	 */
	public function test_bbp_get_topic_permalink() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_permalink = bbp_get_topic_permalink( $t );

		$this->expectOutputString( $topic_permalink );
		bbp_topic_permalink( $t );

		$this->assertSame( 'http://' . WP_TESTS_DOMAIN . '/?topic=topic-1', $topic_permalink );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 */
	public function test_bbp_get_topic_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title' => 'Topic 1',
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( 'Topic 1', $topic_title );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_topic_title_with_script_and_quotes() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title'  => '<script src="https://bbpress.org">Script</script> Topic',
			'post_parent' => $f,
			'topic_meta'  => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( $this->get_expected_kses_output( 'Script Topic', 'Topic' ), $topic_title );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_topic_title_with_script_no_quotes() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title'  => '<script src=https://bbpress.org>Script</script> Topic',
			'post_parent' => $f,
			'topic_meta'  => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( $this->get_expected_kses_output( 'Script Topic', 'Topic' ), $topic_title );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_topic_title_with_quotes() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title'  => '"Quoted" Topic',
			'post_parent' => $f,
			'topic_meta'  => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( '&#8220;Quoted&#8221; Topic', $topic_title );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_topic_title_with_js_as_img_src() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title'  => '<img src="javascript:alert(\'Oh, bother!\');">Topic 1',
			'post_parent' => $f,
			'topic_meta'  => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( 'Topic 1', $topic_title );
	}

	/**
	 * @covers ::bbp_topic_title
	 * @covers ::bbp_get_topic_title
	 * @group  bbp_xss
	 */
	public function test_bbp_get_topic_title_with_extra_open_brackets() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_title'  => '<<script>alert("XSS");//<</script>',
			'post_parent' => $f,
			'topic_meta'  => array(
				'forum_id' => $f,
			),
		) );

		$topic_title = bbp_get_topic_title( $t );
		$this->assertSame( $this->get_expected_kses_output( '&lt;alert(&#8220;XSS&#8221;);//&lt;', '&lt;' ), $topic_title );
	}

	/**
	 * @covers ::bbp_topic_archive_title
	 * @covers ::bbp_get_topic_archive_title
	 */
	public function test_bbp_get_topic_archive_title() {
		$this->assertSame( 'Topics', bbp_get_topic_archive_title() );
		$this->assertSame( 'Custom archive', bbp_get_topic_archive_title( 'Custom archive' ) );
		$this->expectOutputString( 'Custom archive' );
		bbp_topic_archive_title( 'Custom archive' );
	}

	/**
	 * @covers ::bbp_topic_content
	 * @covers ::bbp_get_topic_content
	 */
	public function test_bbp_get_topic_content() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_content' => 'Content of Topic 1',
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		remove_all_filters( 'bbp_get_topic_content' );
		$topic_content = bbp_get_topic_content( $t );
		$this->assertSame( 'Content of Topic 1', $topic_content );
	}

	/**
	 * @covers ::bbp_get_topic_content
	 * @covers ::bbp_get_topic_excerpt
	 */
	public function test_bbp_get_topic_content_and_excerpt_require_ancestor_password() {
		$parent_forum_id = $this->factory->forum->create( array(
			'post_password' => 'parent-secret',
		) );
		$forum_id = $this->factory->forum->create( array(
			'post_parent' => $parent_forum_id,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_content' => 'Protected topic marker',
			'post_excerpt' => 'Protected excerpt marker',
			'post_parent'  => $forum_id,
			'topic_meta'   => array( 'forum_id' => $forum_id ),
		) );

		$this->assertStringNotContainsString( 'Protected topic marker', bbp_get_topic_content( $topic_id ) );
		$this->assertStringNotContainsString( 'Protected excerpt marker', bbp_get_topic_excerpt( $topic_id ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$cookie = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'parent-secret' );

		try {
			$this->assertStringContainsString( 'Protected topic marker', bbp_get_topic_content( $topic_id ) );
			$this->assertStringContainsString( 'Protected excerpt marker', bbp_get_topic_excerpt( $topic_id ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_topic_excerpt
	 * @covers ::bbp_get_topic_excerpt
	 */
	public function test_bbp_get_topic_excerpt() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_content'  => 'Talk about telekinetic activity, look at this mess!',
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		remove_all_filters( 'bbp_get_topic_content' );
		$topic_excerpt = bbp_get_topic_excerpt( $t, 23 );
		$this->assertSame( 'Talk about telekinetic&hellip;', $topic_excerpt );
	}

	/**
	 * @covers ::bbp_topic_post_date
	 * @covers ::bbp_get_topic_post_date
	 *
	 * @ticket BBP3427
	 */
	public function test_bbp_get_topic_post_date() {
		$f = $this->factory->forum->create();

		$now = time();
		$post_date = date( 'Y-m-d H:i:s', $now - 60*60*100 );

		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date' => $post_date,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		// Configue our written date time, August 4, 2012 at 2:37 pm.
		$gmt = false;
		$date   = get_post_time( get_option( 'date_format' ), $gmt, $t, true );
		$time   = get_post_time( get_option( 'time_format' ), $gmt, $t, true );
		$result = sprintf( '%1$s at %2$s', $date, $time );

		// Output, string, August 4, 2012 at 2:37 pm.
		$this->expectOutputString( $result );
		bbp_topic_post_date( $t );

		// String, August 4, 2012 at 2:37 pm.
		$datetime = bbp_get_topic_post_date( $t, false, false );
		$this->assertSame( $result, $datetime );

		// Humanized string, 4 days, 4 hours ago.
		$datetime = bbp_get_topic_post_date( $t, true, false );
		$this->assertSame( '4 days, 4 hours ago', $datetime );

		// Humanized string using GMT formatted date, 4 days, 4 hours ago.
		$datetime = bbp_get_topic_post_date( $t, true, true );
		$this->assertSame( '4 days, 4 hours ago', $datetime );
	}

	/**
	 * @covers ::bbp_topic_post_date
	 * @covers ::bbp_get_topic_post_date
	 *
	 * @ticket BBP3427
	 */
	public function test_bbp_get_topic_post_date_with_custom_format() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'post_date'   => '2012-08-04 14:37:00',
			'topic_meta'  => array(
				'forum_id' => $f,
			),
		) );

		$date   = get_post_time( 'Y-m-d', false, $t, true );
		$time   = get_post_time( get_option( 'time_format' ), false, $t, true );
		$result = sprintf( '%1$s at %2$s', $date, $time );

		$this->assertSame( '2012-08-04', $date );
		$this->assertSame( $result, bbp_get_topic_post_date( $t, false, false, 'Y-m-d' ) );
		$this->expectOutputString( $result );
		bbp_topic_post_date( $t, false, false, 'Y-m-d' );
	}

	/**
	 * @covers ::bbp_topic_pagination
	 * @covers ::bbp_get_topic_pagination
	 */
	public function test_bbp_get_topic_pagination() {
		$topic_id = $this->factory->topic->create();
		$this->factory->reply->create_many( 2, array( 'post_parent' => $topic_id ) );
		update_option( '_bbp_replies_per_page', 1 );
		$args = array( 'topic_id' => $topic_id );
		$pagination = bbp_get_topic_pagination( $args );

		$this->assertStringContainsString( 'bbp-topic-pagination', $pagination );
		$this->assertStringContainsString( 'paged=', $pagination );
		$this->expectOutputString( $pagination );
		bbp_topic_pagination( $args );
	}

	/**
	 * @covers ::bbp_topic_forum_title
	 * @covers ::bbp_get_topic_forum_title
	 */
	public function test_bbp_get_topic_forum_title() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_forum_title = bbp_get_topic_forum_title( $t );
		$this->assertSame( bbp_get_forum_title( $f ), $topic_forum_title );
	}

	/**
	 * @covers ::bbp_topic_forum_id
	 * @covers ::bbp_get_topic_forum_id
	 */
	public function test_bbp_get_topic_forum_id() {
		$f = $this->factory->forum->create();
		$t = $this->factory->topic->create( array(
			'post_parent' => $f,
			'topic_meta' => array(
				'forum_id' => $f,
			),
		) );

		$topic_forum_id = bbp_get_topic_forum_id( $t );
		$this->assertSame( $f, $topic_forum_id );
	}

	/**
	 * @covers ::bbp_topic_class
	 * @covers ::bbp_get_topic_class
	 */
	public function test_bbp_get_topic_class() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$classes  = bbp_get_topic_class( $topic_id, array( 'extra-topic-class' ) );

		$this->assertStringContainsString( 'bbp-parent-forum-' . $forum_id, $classes );
		$this->assertStringContainsString( 'extra-topic-class', $classes );
		bbp_stick_topic( $topic_id );
		$classes = bbp_get_topic_class( $topic_id, array( 'extra-topic-class' ) );
		$this->assertStringContainsString( ' sticky', $classes );
		$this->expectOutputString( $classes );
		bbp_topic_class( $topic_id, array( 'extra-topic-class' ) );
	}

	/**
	 * @covers ::bbp_forum_pagination_count
	 * @covers ::bbp_get_forum_pagination_count
	 */
	public function test_bbp_get_forum_pagination_count() {
		$forum_id = $this->factory->forum->create();
		$this->factory->topic->create_many( 2, array( 'post_parent' => $forum_id ) );
		bbp_has_topics( array( 'post_parent' => $forum_id, 'posts_per_page' => 1 ) );
		$count = bbp_get_forum_pagination_count();

		$this->assertStringContainsString( 'Viewing', $count );
		$this->assertStringContainsString( '2 total', $count );
		$this->expectOutputString( $count );
		bbp_forum_pagination_count();
	}

	/**
	 * @covers ::bbp_topic_notices
	 */
	public function test_bbp_topic_notices() {
		$topic_id = $this->factory->topic->create();
		bbp_unapprove_topic( $topic_id );
		$previous_topic_id = bbpress()->current_topic_id;
		bbpress()->current_topic_id = $topic_id;
		add_filter( 'bbp_is_single_topic', '__return_true' );
		try {
			bbp_topic_notices();
			$this->assertSame( 'This topic is pending moderation.', bbpress()->errors->get_error_message( 'topic_notice' ) );
		} finally {
			bbpress()->errors->remove( 'topic_notice' );
			remove_filter( 'bbp_is_single_topic', '__return_true' );
			bbpress()->current_topic_id = $previous_topic_id;
		}
	}

	/**
	 * @covers ::bbp_topic_type_select
	 */
	public function test_bbp_topic_type_select() {
		$topic_id = $this->factory->topic->create();
		$args     = array( 'topic_id' => $topic_id );
		$dropdown = bbp_get_form_topic_type_dropdown( $args );

		$this->assertStringContainsString( 'bbp_stick_topic', $dropdown );
		$this->assertStringContainsString( 'value="unstick"', $dropdown );
		$this->expectOutputString( $dropdown );
		bbp_topic_type_select( $args );
	}

	/**
	 * @covers ::bbp_single_topic_description
	 * @covers ::bbp_get_single_topic_description
	 */
	public function test_bbp_get_single_topic_description() {
		$topic_id = $this->factory->topic->create();
		$args     = array( 'topic_id' => $topic_id, 'before' => '<p>', 'after' => '</p>' );
		$description = bbp_get_single_topic_description( $args );

		$this->assertStringStartsWith( '<p>', $description );
		$this->assertStringEndsWith( '</p>', $description );
		$this->assertStringContainsString( 'This topic', $description );
		$this->expectOutputString( $description );
		bbp_single_topic_description( $args );
	}

	/**
	 * @covers ::bbp_get_single_topic_description
	 */
	public function test_bbp_get_single_topic_description_can_be_disabled() {
		$args                     = array( 'topic_id' => 123 );
		$filtered_args            = array();
		$original_args            = array();
		$filtered_use_description = null;
		$default_description      = null;
		$voice_count_called       = 0;
		$disable                  = function( $use_description, $description_args, $description_original_args ) use ( &$filtered_args, &$original_args, &$filtered_use_description ) {
			$filtered_args            = $description_args;
			$original_args            = $description_original_args;
			$filtered_use_description = $use_description;

			return false;
		};
		$filter_description = function( $description ) use ( &$default_description ) {
			$default_description = $description;

			return 'Filtered description.';
		};
		$count_voice = function( $voice_count ) use ( &$voice_count_called ) {
			++$voice_count_called;

			return $voice_count;
		};

		add_filter( 'bbp_use_single_topic_description', $disable, 10, 3 );
		add_filter( 'bbp_get_single_topic_description', $filter_description );
		add_filter( 'bbp_get_topic_voice_count_int', $count_voice );

		try {
			$description = bbp_get_single_topic_description( $args );
		} finally {
			remove_filter( 'bbp_get_topic_voice_count_int', $count_voice );
			remove_filter( 'bbp_get_single_topic_description', $filter_description );
			remove_filter( 'bbp_use_single_topic_description', $disable, 10 );
		}

		$this->assertSame( 'Filtered description.', $description );
		$this->assertSame( '', $default_description );
		$this->assertTrue( $filtered_use_description );
		$this->assertSame( 123, $filtered_args['topic_id'] );
		$this->assertSame( $args, $original_args );
		$this->assertSame( 0, $voice_count_called );
	}

	/**
	 * @covers ::bbp_get_single_topic_description
	 */
	public function test_bbp_get_single_topic_description_uses_reply_count_for_sentence_plural() {
		$topic_id     = $this->factory->topic->create();
		$reply_counts = array();
		$single       = 'This topic has %1$s, %2$s, and was last updated %3$s by %4$s.';
		$filter       = function( $translation, $source_single, $source_plural, $number, $domain ) use ( &$reply_counts, $single ) {
			if ( ( $single === $source_single ) && ( 'bbpress' === $domain ) ) {
				$reply_counts[] = $number;
				$translation    = '<b>Localized form ' . $number . ': %1$s, %2$s, %3$s, %4$s.</b>';
			}

			return $translation;
		};

		update_post_meta( $topic_id, '_bbp_voice_count', 1 );
		update_post_meta( $topic_id, '_bbp_last_active_id', $topic_id );
		add_filter( 'ngettext', $filter, 10, 5 );

		try {
			foreach ( array( 0, 1, 2 ) as $reply_count ) {
				update_post_meta( $topic_id, '_bbp_reply_count', $reply_count );
				$description = bbp_get_single_topic_description( array( 'topic_id' => $topic_id ) );
				$this->assertStringContainsString( '&lt;b&gt;Localized form ' . $reply_count . ':', $description );
			}
		} finally {
			remove_filter( 'ngettext', $filter, 10 );
		}

		$this->assertSame( array( 0, 1, 2 ), $reply_counts );
	}

	/**
	 * @covers ::bbp_get_single_topic_description
	 */
	public function test_bbp_get_single_topic_description_without_last_active_uses_reply_count_for_sentence_plural() {
		$topic_id     = $this->factory->topic->create();
		$reply_counts = array();
		$single       = 'This topic has %1$s and %2$s.';
		$filter       = function( $translation, $source_single, $source_plural, $number, $domain ) use ( &$reply_counts, $single ) {
			if ( ( $single === $source_single ) && ( 'bbpress' === $domain ) ) {
				$reply_counts[] = $number;
				$translation    = 'Localized fallback ' . $number . ': %1$s and %2$s.';
			}

			return $translation;
		};

		update_post_meta( $topic_id, '_bbp_voice_count', 1 );
		add_filter( 'bbp_get_topic_last_active_id', '__return_zero' );
		add_filter( 'ngettext', $filter, 10, 5 );

		try {
			foreach ( array( 0, 1, 2 ) as $reply_count ) {
				update_post_meta( $topic_id, '_bbp_reply_count', $reply_count );
				$description = bbp_get_single_topic_description( array( 'topic_id' => $topic_id ) );
				$this->assertStringContainsString( 'Localized fallback ' . $reply_count . ':', $description );
			}
		} finally {
			remove_filter( 'ngettext', $filter, 10 );
			remove_filter( 'bbp_get_topic_last_active_id', '__return_zero' );
		}

		$this->assertSame( array( 0, 1, 2 ), $reply_counts );
	}

	/**
	 * @covers ::bbp_topic_row_actions
	 */
	public function test_bbp_topic_row_actions() {
		$called = 0;
		$callback = function() use ( &$called ) {
			++$called;
		};
		add_action( 'bbp_topic_row_actions', $callback );
		try {
			bbp_topic_row_actions();
			$this->assertSame( 1, $called );
		} finally {
			remove_action( 'bbp_topic_row_actions', $callback );
		}
	}
}
