<?php
/**
 * Tests for the common functions.
 *
 * @group common
 * @group functions
 */

class BBP_Tests_Common_Functions extends BBP_UnitTestCase {

	protected $old_current_user;
	protected $moderator_id;
	protected $keymaster_id;

	/**
	 * Throw when WordPress attempts to insert a revision.
	 *
	 * @param array $data Post data.
	 * @return array Post data.
	 *
	 * @throws RuntimeException When revision data is received.
	 */
	public function throw_on_revision_insert( $data ) {
		if ( isset( $data['post_type'] ) && ( 'revision' === $data['post_type'] ) ) {
			throw new RuntimeException( 'Revision insert.' );
		}

		return $data;
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 */
	public function test_bbp_filter_revision_post_author_ignores_non_revision_data() {
		$data = array(
			'post_type'   => bbp_get_topic_post_type(),
			'post_parent' => 123,
			'post_author' => 456,
		);

		$this->assertSame( $data, bbp_filter_revision_post_author( $data ) );
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 */
	public function test_bbp_filter_revision_post_author_uses_the_parent_author() {
		$author_id = $this->factory->user->create();
		$topic_id  = $this->factory->topic->create( array( 'post_author' => $author_id ) );
		$data      = array(
			'post_type'   => 'revision',
			'post_parent' => $topic_id,
			'post_author' => 0,
		);

		$filtered = bbp_filter_revision_post_author( $data );

		$this->assertSame( $author_id, $filtered['post_author'] );
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 */
	public function test_bbp_filter_revision_post_author_preserves_an_anonymous_parent_author() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );
		$data     = array(
			'post_type'   => 'revision',
			'post_parent' => $topic_id,
			'post_author' => 123,
		);

		$filtered = bbp_filter_revision_post_author( $data );

		$this->assertSame( 0, $filtered['post_author'] );
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 */
	public function test_bbp_filter_revision_post_author_ignores_a_non_bbp_parent() {
		$post_id = $this->factory->post->create();
		$data    = array(
			'post_type'   => 'revision',
			'post_parent' => $post_id,
			'post_author' => 123,
		);

		$this->assertSame( $data, bbp_filter_revision_post_author( $data ) );
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 */
	public function test_bbp_filter_revision_post_author_ignores_an_autosave() {
		$topic_id = $this->factory->topic->create();
		$data     = array(
			'post_type'   => 'revision',
			'post_parent' => $topic_id,
			'post_author' => 123,
			'post_name'   => "{$topic_id}-autosave-v1",
		);

		$this->assertSame( $data, bbp_filter_revision_post_author( $data ) );
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 */
	public function test_bbp_filter_revision_post_author_ignores_a_missing_parent() {
		$data = array(
			'post_type'   => 'revision',
			'post_parent' => PHP_INT_MAX,
			'post_author' => 123,
		);

		$this->assertSame( $data, bbp_filter_revision_post_author( $data ) );
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_rejects_an_empty_id_with_a_global_post() {
		global $post;

		$topic_id = $this->factory->topic->create();
		$old_post = $post;
		$post     = get_post( $topic_id );

		try {
			$this->assertNull( bbp_save_initial_post_revision( 0 ) );
			$this->assertSame( array(), wp_get_post_revisions( $topic_id ) );
		} finally {
			$post = $old_post;
		}
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_rejects_invalid_ids() {
		$this->assertNull( bbp_save_initial_post_revision( -1 ) );
		$this->assertNull( bbp_save_initial_post_revision( PHP_INT_MAX ) );
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_rejects_a_non_bbp_post_type() {
		$post_id = $this->factory->post->create();

		$this->assertNull( bbp_save_initial_post_revision( $post_id ) );
		$this->assertSame( array(), wp_get_post_revisions( $post_id ) );
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_supports_registered_bbp_post_types() {
		$forum_id = $this->factory->forum->create();

		$this->assertIsInt( bbp_save_initial_post_revision( $forum_id ) );
		$this->assertCount( 1, wp_get_post_revisions( $forum_id ) );
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_returns_null_when_revisions_are_disabled() {
		$topic_id = $this->factory->topic->create();

		remove_post_type_support( bbp_get_topic_post_type(), 'revisions' );

		try {
			$this->assertNull( bbp_save_initial_post_revision( $topic_id ) );
			$this->assertSame( array(), wp_get_post_revisions( $topic_id ) );
		} finally {
			add_post_type_support( bbp_get_topic_post_type(), 'revisions' );
		}
	}

	/**
	 * @covers ::bbp_filter_revision_post_author
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_saves_once_and_preserves_the_author() {
		$author_id = $this->factory->user->create();
		$editor_id = $this->factory->user->create();
		$topic_id  = $this->factory->topic->create( array( 'post_author' => $author_id ) );
		$old_user  = get_current_user_id();

		try {
			$this->set_current_user( $editor_id );
			$revision_id = bbp_save_initial_post_revision( $topic_id );

			$this->assertIsInt( $revision_id );
			$this->assertSame( $author_id, (int) get_post_field( 'post_author', $revision_id ) );
			$this->assertFalse( bbp_save_initial_post_revision( $topic_id ) );
			$this->assertCount( 1, wp_get_post_revisions( $topic_id ) );
			$this->assertFalse( has_filter( 'wp_insert_post_data', 'bbp_filter_revision_post_author' ) );
		} finally {
			$this->set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_preserves_an_existing_filter() {
		$topic_id = $this->factory->topic->create();

		add_filter( 'wp_insert_post_data', 'bbp_filter_revision_post_author' );

		try {
			$this->assertIsInt( bbp_save_initial_post_revision( $topic_id ) );
			$this->assertSame( 10, has_filter( 'wp_insert_post_data', 'bbp_filter_revision_post_author' ) );
		} finally {
			remove_filter( 'wp_insert_post_data', 'bbp_filter_revision_post_author' );
		}
	}

	/**
	 * @covers ::bbp_save_initial_post_revision
	 */
	public function test_bbp_save_initial_post_revision_removes_its_filter_after_an_exception() {
		$topic_id = $this->factory->topic->create();

		add_filter( 'wp_insert_post_data', array( $this, 'throw_on_revision_insert' ), 20 );

		try {
			bbp_save_initial_post_revision( $topic_id );
			$this->fail( 'Saving the initial revision should throw.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Revision insert.', $exception->getMessage() );
			$this->assertFalse( has_filter( 'wp_insert_post_data', 'bbp_filter_revision_post_author' ) );
		} finally {
			remove_filter( 'wp_insert_post_data', array( $this, 'throw_on_revision_insert' ), 20 );
		}
	}

	/**
	 * @covers ::bbp_number_format
	 * @todo   Implement test_bbp_number_format().
	 */
	public function test_bbp_number_format() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_number_format_i18n
	 * @todo   Implement test_bbp_number_format_i18n().
	 */
	public function test_bbp_number_format_i18n() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_convert_date
	 * @todo   Implement test_bbp_convert_date().
	 */
	public function test_bbp_convert_date() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_years_months() {
		$now = time();
		$then = $now - ( 3 * YEAR_IN_SECONDS ) - ( 3 * 30 * DAY_IN_SECONDS );
		$since = '3 years, 3 months ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_year_month() {
		$now = time();
		$then = $now - YEAR_IN_SECONDS - ( 1 * 30 * DAY_IN_SECONDS );
		$since = '1 year, 1 month ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_years_nomonths() {
		$now = time();
		$then = $now - ( 3 * YEAR_IN_SECONDS );
		$since = '3 years ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_year_nomonths() {
		$now = time();
		$then = $now - YEAR_IN_SECONDS ;
		$since = '1 year ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_months_weeks() {
		$now = time();
		$then = $now - ( 3 * 30 * DAY_IN_SECONDS ) - ( 3 * WEEK_IN_SECONDS );
		$since = '3 months, 3 weeks ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_month_week() {
		$now = time();
		$then = $now - ( 1 * 30 * DAY_IN_SECONDS ) - ( 1 * WEEK_IN_SECONDS );
		$since = '1 month, 1 week ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_months_noweeks() {
		$now = time();
		$then = $now - ( 3 * 30 * DAY_IN_SECONDS );
		$since = '3 months ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_month_noweeks() {
		$now = time();
		$then = $now - ( 1 * 30 * DAY_IN_SECONDS );
		$since = '1 month ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_weeks_days() {
		$now = time();
		$then = $now - ( 3 * WEEK_IN_SECONDS ) - ( 3 * DAY_IN_SECONDS );
		$since = '3 weeks, 3 days ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_week_day() {
		$now = time();
		$then = $now - ( 1 * WEEK_IN_SECONDS ) - ( 1 * DAY_IN_SECONDS );
		$since = '1 week, 1 day ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_weeks_nodays() {
		$now = time();
		$then = $now - ( 3 * WEEK_IN_SECONDS );
		$since = '3 weeks ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_week_nodays() {
		$now = time();
		$then = $now - ( 1 * WEEK_IN_SECONDS );
		$since = '1 week ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_days_hours() {
		$now = time();
		$then = $now - ( 3 * DAY_IN_SECONDS ) - ( 3 * HOUR_IN_SECONDS );
		$since = '3 days, 3 hours ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_day_hour() {
		$now = time();
		$then = $now - ( 1 * DAY_IN_SECONDS ) - ( 1 * HOUR_IN_SECONDS );
		$since = '1 day, 1 hour ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_days_nohours() {
		$now = time();
		$then = $now - ( 3 * DAY_IN_SECONDS );
		$since = '3 days ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_day_nohours() {
		$now = time();
		$then = $now - ( 1 * DAY_IN_SECONDS );
		$since = '1 day ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_hours_minutes() {
		$now = time();
		$then = $now - ( 3 * HOUR_IN_SECONDS ) - ( 3 * MINUTE_IN_SECONDS );
		$since = '3 hours, 3 minutes ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_hour_minute() {
		$now = time();
		$then = $now - ( 1 * HOUR_IN_SECONDS ) - ( 1 * MINUTE_IN_SECONDS );
		$since = '1 hour, 1 minute ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_hours_nominutes() {
		$now = time();
		$then = $now - ( 3 * HOUR_IN_SECONDS );
		$since = '3 hours ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_hour_nominutes() {
		$now = time();
		$then = $now - ( 1 * HOUR_IN_SECONDS );
		$since = '1 hour ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}
	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_minutes_seconds() {
		$now = time();
		$then = $now - ( 3 * MINUTE_IN_SECONDS ) - 3;
		$since = '3 minutes ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_minutes_noseconds() {
		$now = time();
		$then = $now - ( 3 * MINUTE_IN_SECONDS );
		$since = '3 minutes ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_minute_noseconds() {
		$now = time();
		$then = $now - ( 1 * MINUTE_IN_SECONDS );
		$since = '1 minute ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_seconds() {
		$now = time();
		$then = $now - 3;
		$since = '3 seconds ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_second() {
		$now = time();
		$then = $now - 1;
		$since = '1 second ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_rightnow() {
		$now = time();
		$then = $now;
		$since = 'right now';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_future() {
		$now = time();
		$then = $now + 100;
		$since = 'sometime ago';

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertEquals( $since, bbp_get_time_since( $then, $now ) );
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_timezone_minute_ago() {
		$now = time();
		$then = $now - ( 1 * MINUTE_IN_SECONDS );
		$since = '1 minute ago';

		// Backup timezone.
		$tz_backup = date_default_timezone_get();

		// Set timezone to something other than UTC.
		date_default_timezone_set( 'Europe/Paris' );

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertSame( $since, bbp_get_time_since( $then, $now, $gmt = false ) );

		// Revert timezone back to normal.
		if ( $tz_backup ) {
			date_default_timezone_set( $tz_backup );
		}
	}

	/**
	 * @covers ::bbp_time_since
	 * @covers ::bbp_get_time_since
	 */
	public function test_bbp_time_since_timezone() {
		$now = time();
		$then = $now - ( 1 * HOUR_IN_SECONDS );
		$since = '1 hour ago';

		// Backup timezone.
		$tz_backup = date_default_timezone_get();

		// Set timezone to something other than UTC.
		date_default_timezone_set( 'Europe/Paris' );

		// Output.
		$this->expectOutputString( $since );
		bbp_time_since( $then, $now );

		// Formatted.
		$this->assertSame( $since, bbp_get_time_since( $then, $now, true ) );

		// Revert timezone back to normal.
		if ( $tz_backup ) {
			date_default_timezone_set( $tz_backup );
		}
	}

	/**
	 * @covers ::bbp_format_revision_reason
	 * @todo   Implement test_bbp_format_revision_reason().
	 */
	public function test_bbp_format_revision_reason() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_redirect_to
	 * @todo   Implement test_bbp_get_redirect_to().
	 */
	public function test_bbp_get_redirect_to() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_add_view_all
	 * @todo   Implement test_bbp_add_view_all().
	 */
	public function test_bbp_add_view_all() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_remove_view_all
	 * @todo   Implement test_bbp_remove_view_all().
	 */
	public function test_bbp_remove_view_all() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_view_all
	 * @todo   Implement test_bbp_get_view_all().
	 */
	public function test_bbp_get_view_all() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_paged
	 * @todo   Implement test_bbp_get_paged().
	 */
	public function test_bbp_get_paged() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_fix_post_author
	 * @todo   Implement test_bbp_fix_post_author().
	 */
	public function test_bbp_fix_post_author() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_set_pending_post_date_gmt
	 */
	public function test_bbp_set_pending_post_date_gmt_preserves_dates_on_status_change() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'post_status' => bbp_get_pending_status_id(),
			'post_date'   => '2020-01-01 00:00:00',
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'post_status' => bbp_get_pending_status_id(),
			'post_date'   => '2020-01-02 00:00:00',
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		foreach ( array( $topic_id, $reply_id ) as $post_id ) {
			$post_date = get_post_field( 'post_date', $post_id );
			$this->assertSame( get_gmt_from_date( $post_date ), get_post_field( 'post_date_gmt', $post_id ) );

			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => bbp_get_spam_status_id(),
			) );

			$this->assertSame( $post_date, get_post_field( 'post_date', $post_id ) );
		}
	}

	/**
	 * @group  locking
	 * @covers ::bbp_past_edit_lock
	 */
	public function test_bbp_past_edit_lock_before_5_minutes() {
		update_option( '_bbp_edit_lock', 5 );
		update_option( '_bbp_allow_content_edit', true );

		// Before
		$result = bbp_past_edit_lock( '4 minutes 59 seconds ago UTC' );
		$this->assertFalse( $result );
	}

	/**
	 * @group  locking
	 * @covers ::bbp_past_edit_lock
	 */
	public function test_bbp_past_edit_lock_on_5_minutes() {
		update_option( '_bbp_edit_lock', 5 );
		update_option( '_bbp_allow_content_edit', true );

		// On
		$result = bbp_past_edit_lock( '5 minutes ago UTC' );
		$this->assertTrue( $result );
	}

	/**
	 * @group  locking
	 * @covers ::bbp_past_edit_lock
	 */
	public function test_bbp_past_edit_lock_after_5_minutes() {
		update_option( '_bbp_edit_lock', 5 );
		update_option( '_bbp_allow_content_edit', true );

		// After
		$result = bbp_past_edit_lock( '5 minutes 1 second ago UTC' );
		$this->assertTrue( $result );
	}

	/**
	 * @group  locking
	 * @covers ::bbp_past_edit_lock
	 */
	public function test_bbp_past_edit_lock_before_0_minutes() {
		update_option( '_bbp_edit_lock', 0 );
		update_option( '_bbp_allow_content_edit', true );

		// Before
		$result = bbp_past_edit_lock( '4 minutes 59 seconds ago UTC' );
		$this->assertFalse( $result );
	}

	/**
	 * @group  locking
	 * @covers ::bbp_past_edit_lock
	 */
	public function test_bbp_past_edit_lock_on_0_minutes() {
		update_option( '_bbp_edit_lock', 0 );
		update_option( '_bbp_allow_content_edit', true );

		// On
		$result = bbp_past_edit_lock( '5 minutes ago UTC' );
		$this->assertFalse( $result );
	}

	/**
	 * @group  locking
	 * @covers ::bbp_past_edit_lock
	 */
	public function test_bbp_past_edit_lock_after_0_minutes() {
		update_option( '_bbp_edit_lock', 0 );
		update_option( '_bbp_allow_content_edit', true );

		// After
		$result = bbp_past_edit_lock( '5 minutes 1 second ago UTC' );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_get_statistics
	 */
	public function test_bbp_get_statistics() {
		$term = wp_insert_term( 'Unused tag', bbp_get_topic_tag_tax_id() );
		$this->assertNotWPError( $term );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_hidden_status_id(),
				'topic_meta'  => array( 'forum_id' => $forum_id ),
			)
		);
		$this->factory->reply->create(
			array(
				'post_parent' => $topic_id,
				'post_status' => bbp_get_hidden_status_id(),
				'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
			)
		);

		wp_set_current_user( 0 );
		$anonymous = bbp_get_statistics();
		$this->assertSame( 0, $anonymous['empty_topic_tag_count_int'] );
		$this->assertSame( 0, $anonymous['topic_count_hidden_int'] );
		$this->assertSame( 0, $anonymous['reply_count_hidden_int'] );

		$participant_id = $this->factory->user->create();
		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		wp_set_current_user( $participant_id );
		$participant = bbp_get_statistics();
		$this->assertSame( 0, $participant['empty_topic_tag_count_int'] );
		$this->assertSame( 0, $participant['topic_count_hidden_int'] );
		$this->assertSame( 0, $participant['reply_count_hidden_int'] );

		$user_id = $this->factory->user->create();
		bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
		wp_set_current_user( $user_id );
		$this->assertTrue( current_user_can( 'edit_topic_tags' ) );
		$staff = bbp_get_statistics();
		$this->assertSame( 1, $staff['empty_topic_tag_count_int'] );
		$this->assertSame( 1, $staff['topic_count_hidden_int'] );
		$this->assertSame( 1, $staff['reply_count_hidden_int'] );
	}

	/**
	 * @covers ::bbp_escape_feed_cdata
	 */
	public function test_bbp_escape_feed_cdata_keeps_description_inside_cdata() {
		if ( ! function_exists( 'simplexml_load_string' ) ) {
			$this->markTestSkipped( 'SimpleXML is required to parse the feed fragment.' );
		}

		$content = 'Before ]]><p>After</p>';
		$escaped = bbp_escape_feed_cdata( $content );
		$xml     = simplexml_load_string( '<description><![CDATA[' . $escaped . ']]></description>' );

		$this->assertInstanceOf( 'SimpleXMLElement', $xml );
		$this->assertCount( 0, $xml->children() );
		$this->assertSame( 'Before ]]&gt;<p>After</p>', (string) $xml );
		$this->assertSame( '<p>Unchanged</p>', bbp_escape_feed_cdata( '<p>Unchanged</p>' ) );
	}

	/**
	 * @covers ::bbp_filter_anonymous_post_data
	 * @todo   Implement test_bbp_filter_anonymous_post_data().
	 */
	public function test_bbp_filter_anonymous_post_data() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_for_duplicate
	 * @todo   Implement test_bbp_check_for_duplicate().
	 */
	public function test_bbp_check_for_duplicate() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_for_flood
	 * @todo   Implement test_bbp_check_for_flood().
	 */
	public function test_bbp_check_for_flood() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_bbp_check_for_moderation() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'Beware, there maybe bees hibernating.';

		update_option( 'moderation_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertFalse( $result );

		update_option( 'moderation_keys',"foo\nbar" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_user_url_moderation_check() {
		$u = $this->factory->user->create( array(
			'user_url'   => 'http://example.net/banned',
		) );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'moderation_keys',"http://example.net/banned\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_user_email_moderation_check() {
		$u = $this->factory->user->create( array(
			'user_email' => 'banned@example.net',
		) );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'moderation_keys',"banned@example.net\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_user_ip_moderation_check() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'moderation_keys',"127.0.0.1\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_true_for_moderators_to_bypass_moderation_check() {
		// Create a moderator user.
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$this->moderator_id = get_current_user_id();
		bbp_set_user_role( $this->moderator_id, bbp_get_moderator_role() );

		$t = $this->factory->topic->create( array(
			'post_author' => bbp_get_current_user_id(),
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'moderation_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertTrue( $result );

		// Retore the original user.
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_when_link_count_exceeds_comment_max_links_setting() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'This is a post with <a href="http://example.com">multiple</a> <a href="http://bob.example.com">links</a>.';

		update_option( 'comment_max_links', 2 );
		$results = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );
		$this->assertFalse( $results );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_true_when_link_count_does_not_exceed_comment_max_links_setting() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'This is a post with <a href="http://example.com">multiple</a> <a href="http://bob.example.com">links</a>.';

		update_option( 'comment_max_links', 3 );
		$results = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );
		$this->assertTrue( $results );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_when_link_matches_moderation_keys() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'Beware, there maybe bees <a href="http://example.com/hibernating/>buzzing</a>, buzzing.';

		update_option( 'moderation_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_when_html_wrapped_content_matches_moderation_keys() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees <strong>hiber</strong><em>nating</em>.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'moderation_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_bbp_check_for_moderation_strict() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'Beware, they maybe bees hibernating.';

		update_option( 'disallowed_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );

		update_option( 'disallowed_keys',"foo\nbar" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertTrue( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_user_url_strict_moderation_check() {
		$u = $this->factory->user->create( array(
			'user_url'   => 'http://example.net/banned',
		) );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'disallowed_keys',"http://example.net/banned\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_user_email_strict_moderation_check() {
		$u = $this->factory->user->create( array(
			'user_email' => 'banned@example.net',
		) );

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'disallowed_keys',"banned@example.net\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_user_ip_strict_moderation_check() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'disallowed_keys',"127.0.0.1\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_for_moderators_to_bypass_strict_moderation_check() {
		// Create a moderator user.
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$this->moderator_id = get_current_user_id();
		bbp_set_user_role( $this->moderator_id, bbp_get_moderator_role() );

		$t = $this->factory->topic->create( array(
			'post_author' => bbp_get_current_user_id(),
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'disallowed_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );

		// Retore the original user.
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_true_for_keymasterss_to_bypass_strict_moderation_check() {
		// Create a keymaster user.
		$this->old_current_user = get_current_user_id();
		$this->set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$this->keymaster_id = get_current_user_id();
		bbp_set_user_role( $this->keymaster_id, bbp_get_keymaster_role() );

		$t = $this->factory->topic->create( array(
			'post_author' => bbp_get_current_user_id(),
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees hibernating.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'disallowed_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertTrue( $result );

		// Retore the original user.
		$this->set_current_user( $this->old_current_user );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_when_link_matches_strict_moderation_keys() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'Beware, there maybe bees <a href="http://example.com/hibernating/>buzzing</a>, buzzing.';

		update_option( 'disallowed_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_return_false_when_html_wrapped_content_matches_strict_moderation_keys() {
		$u = $this->factory->user->create();

		$t = $this->factory->topic->create( array(
			'post_author' => $u,
			'post_title' => 'Sting',
			'post_content' => 'Beware, there maybe bees <strong>hiber</strong><em>nating</em>.',
		) );

		$anonymous_data = false;
		$author_id      = bbp_get_topic_author_id( $t );
		$title          = bbp_get_topic_title( $t );
		$content        = bbp_get_topic_content( $t );

		update_option( 'disallowed_keys',"hibernating\nfoo" );

		$result = bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content, true );

		$this->assertFalse( $result );
	}

	/**
	 * @covers ::bbp_get_do_not_reply_address
	 */
	public function test_bbp_get_do_not_reply_address() {
		$server_name = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : null;
		$home_url    = 'https://www.example.org/forums/';
		$filter      = function() use ( &$home_url ) { return $home_url; };
		$site        = function() { return 'https://site.example.org/'; };

		add_filter( 'home_url', $filter );
		add_filter( 'site_url', $site );
		$_SERVER['SERVER_NAME'] = 'attacker.example';

		try {
			$this->assertSame( 'noreply@example.org', bbp_get_do_not_reply_address() );

			$home_url = 'https://subdomain.example.org:8443/forums/';
			$this->assertSame( 'noreply@subdomain.example.org', bbp_get_do_not_reply_address() );

			$home_url = 'https://www.subdomain.example.org/forums/';
			$this->assertSame( 'noreply@subdomain.example.org', bbp_get_do_not_reply_address() );

			$home_url = '/forums/';
			$this->assertSame( 'noreply@site.example.org', bbp_get_do_not_reply_address() );
		} finally {
			remove_filter( 'home_url', $filter );
			remove_filter( 'site_url', $site );
			if ( null === $server_name ) {
				unset( $_SERVER['SERVER_NAME'] );
			} else {
				$_SERVER['SERVER_NAME'] = $server_name;
			}
		}
	}

	/**
	 * @covers ::bbp_filter_subscription_user_ids
	 */
	public function test_bbp_filter_subscription_user_ids() {
		$participant_id = $this->factory->user->create();
		$forum_mod_id   = $this->factory->user->create();
		$moderator_id   = $this->factory->user->create();
		$keymaster_id   = $this->factory->user->create();
		$blocked_id     = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $forum_mod_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $keymaster_id, bbp_get_keymaster_role() );
		bbp_set_user_role( $blocked_id, bbp_get_blocked_role() );

		$parent_id = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );
		$forum_id  = $this->factory->forum->create( array(
			'post_parent' => $parent_id,
		) );
		$topic_id  = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );
		$reply_id  = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		bbp_add_moderator( $forum_id, $forum_mod_id );

		$this->assertSame(
			array( $forum_mod_id, $moderator_id, $keymaster_id ),
			array_values(
				bbp_filter_subscription_user_ids(
					array( $participant_id, $forum_mod_id, $moderator_id, $keymaster_id ),
					$forum_id,
					$topic_id,
					$reply_id
				)
			)
		);

		$public_id = $this->factory->forum->create();

		$this->assertSame(
			array( $participant_id ),
			array_values( bbp_filter_subscription_user_ids( array( $participant_id, $blocked_id ), $public_id ) )
		);

		$public_topic_id = $this->factory->topic->create( array(
			'post_parent' => $public_id,
			'topic_meta'  => array(
				'forum_id' => $public_id,
			),
		) );
		$public_reply_id = $this->factory->reply->create( array(
			'post_parent' => $public_topic_id,
			'reply_meta'  => array(
				'forum_id' => $public_id,
				'topic_id' => $public_topic_id,
			),
		) );
		$denied_cap    = 'read_topic';
		$filter_args   = array();
		$deny_read_cap = function( $caps, $cap, $user_id ) use ( &$denied_cap, $participant_id ) {
			if ( ( $cap === $denied_cap ) && ( $user_id === $participant_id ) ) {
				$caps = array( 'do_not_allow' );
			}

			return $caps;
		};
		$capture_args  = function( $can_view, $user_id, $forum_id, $topic_id, $reply_id ) use ( &$filter_args ) {
			$filter_args = compact( 'user_id', 'forum_id', 'topic_id', 'reply_id' );

			return $can_view;
		};

		add_filter( 'bbp_map_meta_caps', $deny_read_cap, 99, 3 );
		add_filter( 'bbp_subscription_user_can_view_forum', $capture_args, 10, 5 );
		$topic_results = array_values( bbp_filter_subscription_user_ids( array( $participant_id ), $public_id, $public_topic_id, $public_reply_id ) );

		$denied_cap = 'read_reply';
		$reply_results = array_values( bbp_filter_subscription_user_ids( array( $participant_id ), $public_id, $public_topic_id, $public_reply_id ) );
		remove_filter( 'bbp_map_meta_caps', $deny_read_cap, 99 );
		remove_filter( 'bbp_subscription_user_can_view_forum', $capture_args, 10 );

		$this->assertSame( array(), $topic_results );
		$this->assertSame( array(), $reply_results );
		$this->assertSame(
			array(
				'user_id'  => $participant_id,
				'forum_id' => $public_id,
				'topic_id' => $public_topic_id,
				'reply_id' => $public_reply_id,
			),
			$filter_args
		);
	}

	/**
	 * @covers ::bbp_notify_topic_subscribers
	 */
	public function test_bbp_notify_topic_subscribers() {
		$participant_id = $this->factory->user->create( array(
			'user_email' => 'participant@example.org',
		) );
		$moderator_id   = $this->factory->user->create( array(
			'user_email' => 'moderator@example.org',
		) );
		$author_id      = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );
		$forum_id       = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );
		$topic_id       = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );
		$reply_id       = $this->factory->reply->create( array(
			'post_author' => $author_id,
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		bbp_add_user_topic_subscription( $participant_id, $topic_id );
		bbp_add_user_topic_subscription( $moderator_id, $topic_id );

		$mail = null;
		$pre_wp_mail = function( $return, $atts ) use ( &$mail ) {
			$mail = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $pre_wp_mail, 10, 2 );

		$result = bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id, array(), $author_id );
		remove_filter( 'pre_wp_mail', $pre_wp_mail, 10 );

		$this->assertTrue( $result );
		$this->assertContains( 'Bcc: moderator@example.org', $mail['headers'] );
		$this->assertNotContains( 'Bcc: participant@example.org', $mail['headers'] );
	}

	/**
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_bbp_notify_forum_subscribers() {
		$participant_id = $this->factory->user->create( array(
			'user_email' => 'participant@example.org',
		) );
		$moderator_id   = $this->factory->user->create( array(
			'user_email' => 'moderator@example.org',
		) );
		$author_id      = $this->factory->user->create();

		bbp_set_user_role( $participant_id, bbp_get_participant_role() );
		bbp_set_user_role( $moderator_id, bbp_get_moderator_role() );
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );
		$forum_id       = $this->factory->forum->create( array(
			'post_status' => bbp_get_hidden_status_id(),
		) );
		$topic_id       = $this->factory->topic->create( array(
			'post_author' => $author_id,
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );

		bbp_add_user_forum_subscription( $participant_id, $forum_id );
		bbp_add_user_forum_subscription( $moderator_id, $forum_id );

		$mail = null;
		$pre_wp_mail = function( $return, $atts ) use ( &$mail ) {
			$mail = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $pre_wp_mail, 10, 2 );

		$result = bbp_notify_forum_subscribers( $topic_id, $forum_id, array(), $author_id );
		remove_filter( 'pre_wp_mail', $pre_wp_mail, 10 );

		$this->assertTrue( $result );
		$this->assertContains( 'Bcc: moderator@example.org', $mail['headers'] );
		$this->assertNotContains( 'Bcc: participant@example.org', $mail['headers'] );
	}

	/**
	 * @covers ::bbp_notify_topic_subscribers
	 */
	public function test_bbp_notify_topic_subscribers_redacts_password_protected_content() {
		$subscriber_id = $this->factory->user->create( array(
			'user_email' => 'subscriber@example.org',
		) );
		$author_id = $this->factory->user->create( array(
			'display_name' => 'Secret Author',
		) );

		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );

		$forum_id = $this->factory->forum->create( array(
			'post_title' => 'Protected Forum',
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_author'   => $author_id,
			'post_parent'   => $forum_id,
			'post_password' => 'password',
			'post_title'    => 'Protected Topic',
			'topic_meta'    => array(
				'forum_id' => $forum_id,
			),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_author'  => $author_id,
			'post_content' => 'Confidential reply body',
			'post_parent'  => $topic_id,
			'reply_meta'   => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );

		$this->assertTrue( bbp_add_user_topic_subscription( $subscriber_id, $topic_id ) );

		$mail = null;
		$pre_wp_mail = function( $return, $atts ) use ( &$mail ) {
			$mail = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $pre_wp_mail, 10, 2 );

		$result = bbp_notify_topic_subscribers( $reply_id, $topic_id, $forum_id, array(), $author_id );
		remove_filter( 'pre_wp_mail', $pre_wp_mail, 10 );

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Protected Forum', $mail['subject'] );
		$this->assertStringContainsString( 'Protected Topic', $mail['subject'] );
		$this->assertStringContainsString( 'password-protected discussion', $mail['message'] );
		$this->assertStringContainsString( bbp_get_reply_url( $reply_id ), $mail['message'] );
		$this->assertStringNotContainsString( 'Secret Author', $mail['message'] );
		$this->assertStringNotContainsString( 'Confidential reply body', $mail['message'] );
	}

	/**
	 * @covers ::bbp_notify_forum_subscribers
	 */
	public function test_bbp_notify_forum_subscribers_redacts_password_protected_content() {
		$subscriber_id = $this->factory->user->create( array(
			'user_email' => 'subscriber@example.org',
		) );
		$author_id = $this->factory->user->create( array(
			'display_name' => 'Secret Author',
		) );

		bbp_set_user_role( $subscriber_id, bbp_get_participant_role() );
		bbp_set_user_role( $author_id, bbp_get_keymaster_role() );

		$forum_id = $this->factory->forum->create( array(
			'post_password' => 'password',
			'post_title'    => 'Protected Forum',
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_author'  => $author_id,
			'post_content' => 'Confidential topic body',
			'post_parent'  => $forum_id,
			'post_title'   => 'Protected Topic',
			'topic_meta'   => array(
				'forum_id' => $forum_id,
			),
		) );

		$this->assertTrue( bbp_add_user_forum_subscription( $subscriber_id, $forum_id ) );

		$mail = null;
		$pre_wp_mail = function( $return, $atts ) use ( &$mail ) {
			$mail = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $pre_wp_mail, 10, 2 );

		$result = bbp_notify_forum_subscribers( $topic_id, $forum_id, array(), $author_id );
		remove_filter( 'pre_wp_mail', $pre_wp_mail, 10 );

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Protected Forum', $mail['subject'] );
		$this->assertStringContainsString( 'Protected Topic', $mail['subject'] );
		$this->assertStringContainsString( 'password-protected discussion', $mail['message'] );
		$this->assertStringContainsString( bbp_get_topic_permalink( $topic_id ), $mail['message'] );
		$this->assertStringNotContainsString( 'Secret Author', $mail['message'] );
		$this->assertStringNotContainsString( 'Confidential topic body', $mail['message'] );
	}

	/**
	 * @covers ::bbp_notify_subscribers
	 * @todo   Implement test_bbp_notify_subscribers().
	 */
	public function test_bbp_notify_subscribers() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_logout_url
	 */
	public function test_bbp_logout_url() {
		$url      = 'https://example.org/logout/';
		$redirect = home_url( '/forums/' );
		$result   = bbp_logout_url( $url, $redirect );
		$query    = array();
		wp_parse_str( wp_parse_url( $result, PHP_URL_QUERY ), $query );

		$this->assertSame( $redirect . '?loggedout=true', $query['redirect_to'] );
		$this->assertSame( $url . '?redirect_to=already-set', bbp_logout_url( $url . '?redirect_to=already-set', $redirect ) );
	}

	/**
	 * @covers ::bbp_logout_url
	 */
	public function test_bbp_logout_url_defaults_to_current_request() {
		$server = $_SERVER;
		$home   = wp_parse_url( home_url() );
		$host   = $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );

		try {
			$_SERVER['HTTP_HOST']    = $host;
			$_SERVER['REQUEST_URI']  = '/forums/topic/current/';
			$_SERVER['HTTP_REFERER'] = bbp_get_url_scheme() . $host . '/forums/topic/previous/';

			$result = bbp_logout_url( 'https://example.org/logout/' );
			$query  = array();
			wp_parse_str( wp_parse_url( $result, PHP_URL_QUERY ), $query );

			$this->assertSame( bbp_get_url_scheme() . $host . '/forums/topic/current/?loggedout=true', $query['redirect_to'] );
		} finally {
			$_SERVER = $server;
		}
	}

	/**
	 * @covers ::bbp_logout_url
	 */
	public function test_bbp_logout_url_defaults_to_forum_root_without_current_request() {
		$server = $_SERVER;

		try {
			unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );

			$result = bbp_logout_url( 'https://example.org/logout/' );
			$query  = array();
			wp_parse_str( wp_parse_url( $result, PHP_URL_QUERY ), $query );

			$this->assertSame( add_query_arg( 'loggedout', 'true', bbp_get_root_url() ), $query['redirect_to'] );
		} finally {
			$_SERVER = $server;
		}
	}

	/**
	 * @covers ::bbp_parse_args
	 * @todo   Implement test_bbp_parse_args().
	 */
	public function test_bbp_parse_args() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_global_post_field
	 * @todo   Implement test_bbp_get_global_post_field().
	 */
	public function test_bbp_get_global_post_field() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_verify_nonce_request
	 * @todo   Implement test_bbp_verify_nonce_request().
	 */
	public function test_bbp_verify_nonce_request() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_request_feed_trap
	 */
	public function test_bbp_request_feed_trap() {
		$query_vars = array( 'post_type' => bbp_get_topic_post_type(), 'paged' => 2 );
		$this->assertSame( $query_vars, bbp_request_feed_trap( $query_vars ) );
		$this->assertSame( array( 'feed' => 'rss2', 'post_type' => 'post' ), bbp_request_feed_trap( array( 'feed' => 'rss2', 'post_type' => 'post' ) ) );

		$view_id = bbp_get_view_rewrite_id();
		$this->assertSame(
			array( 'feed' => 'rss2', 'error' => 404 ),
			bbp_request_feed_trap( array( 'feed' => 'rss2', $view_id => 'missing-view' ) )
		);
	}

	/**
	 * @covers ::bbp_get_page_by_path
	 * @todo   Implement test_bbp_get_page_by_path().
	 */
	public function test_bbp_get_page_by_path() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_string_array_values
	 */
	public function test_bbp_get_string_array_values() {
		$values = array( '', '0', 'topic', true, false, 12, null, array(), new stdClass() );

		$this->assertSame( array( '0', 'topic' ), bbp_get_string_array_values( $values ) );
		$this->assertSame( array( 'topic' ), bbp_get_string_array_values( 'topic' ) );
	}

	/**
	 * @covers ::bbp_is_custom_post_type
	 */
	public function test_bbp_is_custom_post_type() {
		$forum_id      = $this->factory->forum->create();
		$forum_object  = (object) get_post( $forum_id )->to_array();
		$original_post = isset( $GLOBALS['post'] )
			? $GLOBALS['post']
			: null;

		try {
			$GLOBALS['post'] = get_post( $forum_id );
			$this->assertTrue( bbp_is_custom_post_type() );
			$this->assertTrue( bbp_is_custom_post_type( $forum_id ) );
			$this->assertTrue( bbp_is_custom_post_type( (float) $forum_id ) );
			$this->assertTrue( bbp_is_custom_post_type( (string) $forum_id ) );
			$this->assertTrue( bbp_is_custom_post_type( get_post( $forum_id ) ) );
			$this->assertTrue( bbp_is_custom_post_type( $forum_object ) );
			$this->assertTrue( bbp_is_custom_post_type( bbp_get_forum_post_type() ) );
			$this->assertTrue( bbp_is_custom_post_type( array( 'post', bbp_get_topic_post_type() ) ) );
			$this->assertFalse( bbp_is_custom_post_type( 'post' ) );
			$this->assertFalse( bbp_is_custom_post_type( array( 'post', 'page' ) ) );
			$this->assertFalse( bbp_is_custom_post_type( array() ) );
			$this->assertFalse( bbp_is_custom_post_type( array( true, new stdClass() ) ) );
			$this->assertTrue( bbp_is_custom_post_type( array( true, new stdClass(), bbp_get_reply_post_type() ) ) );
		} finally {
			$GLOBALS['post'] = $original_post;
		}
	}

	/**
	 * @covers ::bbp_is_password_protected
	 */
	public function test_bbp_is_password_protected() {
		$parent_forum_id = $this->factory->forum->create( array(
			'post_password' => 'password',
		) );
		$forum_id = $this->factory->forum->create( array(
			'post_parent' => $parent_forum_id,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array(
				'forum_id' => $forum_id,
			),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array(
				'forum_id' => $forum_id,
				'topic_id' => $topic_id,
			),
		) );
		$post_id = $this->factory->post->create( array(
			'post_password' => 'password',
		) );

		$this->assertTrue( bbp_is_password_protected( $parent_forum_id ) );
		$this->assertTrue( bbp_is_password_protected( $forum_id ) );
		$this->assertTrue( bbp_is_password_protected( $topic_id ) );
		$this->assertTrue( bbp_is_password_protected( $reply_id ) );
		$this->assertFalse( bbp_is_password_protected( $post_id ) );
		$this->assertFalse( bbp_is_password_protected( 999999 ) );

		$filter = function( $retval, $object_id, $object_type ) {
			$this->assertFalse( $retval );
			$this->assertSame( 123, $object_id );
			$this->assertSame( 'term', $object_type );

			return true;
		};
		add_filter( 'bbp_is_password_protected', $filter, 10, 3 );

		$this->assertTrue( bbp_is_password_protected( 123, 'term' ) );

		remove_filter( 'bbp_is_password_protected', $filter, 10 );
	}

	/**
	 * @covers ::bbp_get_password_required_id
	 */
	public function test_bbp_get_password_required_id() {
		$parent_forum_id = $this->factory->forum->create( array(
			'post_password' => 'parent-secret',
		) );
		$forum_id = $this->factory->forum->create( array(
			'post_parent' => $parent_forum_id,
		) );
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$reply_id = $this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );
		$post_id = $this->factory->post->create( array(
			'post_password' => 'post-secret',
		) );

		$this->assertSame( $parent_forum_id, bbp_get_password_required_id( $parent_forum_id ) );
		$this->assertSame( $parent_forum_id, bbp_get_password_required_id( $forum_id ) );
		$this->assertSame( $parent_forum_id, bbp_get_password_required_id( $topic_id ) );
		$this->assertSame( $parent_forum_id, bbp_get_password_required_id( $reply_id ) );
		$this->assertSame( 0, bbp_get_password_required_id( $post_id ) );
		$this->assertSame( 0, bbp_get_password_required_id( 999999 ) );

		require_once ABSPATH . WPINC . '/class-phpass.php';
		$hasher = new PasswordHash( 8, true );
		$cookie = 'wp-postpass_' . COOKIEHASH;
		$old_cookie = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : null;
		$_COOKIE[ $cookie ] = $hasher->HashPassword( 'parent-secret' );

		try {
			$this->assertSame( 0, bbp_get_password_required_id( $forum_id ) );
			$this->assertSame( 0, bbp_get_password_required_id( $topic_id ) );
			$this->assertSame( 0, bbp_get_password_required_id( $reply_id ) );
		} finally {
			if ( null === $old_cookie ) {
				unset( $_COOKIE[ $cookie ] );
			} else {
				$_COOKIE[ $cookie ] = $old_cookie;
			}
		}
	}

	/**
	 * @covers ::bbp_do_not_guess_404_permalink
	 */
	public function test_bbp_do_not_guess_404_permalink() {
		$original_post_type = get_query_var( 'post_type' );

		set_query_var( 'post_type', 'post' );
		$this->assertTrue( bbp_do_not_guess_404_permalink( true ) );
		$this->assertFalse( bbp_do_not_guess_404_permalink( false ) );

		foreach ( bbp_get_post_types() as $post_type ) {
			set_query_var( 'post_type', $post_type );
			$this->assertFalse( apply_filters( 'do_redirect_guess_404_permalink', true ) );
		}

		set_query_var( 'post_type', array( 'post', bbp_get_topic_post_type() ) );
		$this->assertFalse( bbp_do_not_guess_404_permalink( true ) );

		set_query_var( 'post_type', $original_post_type );
	}

	/**
	 * @covers ::bbp_do_not_guess_404_permalink
	 */
	public function test_bbp_do_not_guess_404_permalink_for_incomplete_route_roots() {
		global $wp;

		$original_request = $wp->request;
		$include_root     = true;
		$include_filter   = function () use ( &$include_root ) {
			return $include_root;
		};

		add_filter( 'bbp_include_root_slug', $include_filter );

		try {
			foreach ( array( true, false ) as $include_root ) {
				$route_roots = bbp_get_route_root_slugs();

				foreach ( $route_roots as $route_root ) {
					$wp->request = $route_root;
					$this->assertFalse( bbp_do_not_guess_404_permalink( true ), $route_root );
				}

				$wp->request = bbp_get_topic_slug() . '/missing-topic';
				$this->assertTrue( bbp_do_not_guess_404_permalink( true ) );

				$wp->request = bbp_get_topic_slug() . '-similar';
				$this->assertTrue( bbp_do_not_guess_404_permalink( true ) );

				$wp->request = bbp_get_forum_slug();
				$this->assertTrue( bbp_do_not_guess_404_permalink( true ) );
			}
		} finally {
			$wp->request = $original_request;
			remove_filter( 'bbp_include_root_slug', $include_filter );
		}
	}

	/**
	 * @covers ::bbp_do_not_guess_404_permalink
	 */
	public function test_bbp_do_not_guess_404_permalink_for_normalized_route_root_requests() {
		global $wp;

		$original_request = $wp->request;

		add_filter( 'bbp_include_root_slug', '__return_false' );

		try {
			$route_roots = bbp_get_route_root_slugs();

			foreach ( $route_roots as $route_root ) {
				$requests = array(
					'/' . $route_root,
					'/' . $route_root . '/',
					'/' . $route_root . '/?example=value',
				);

				foreach ( $requests as $request ) {
					$wp->request = trim( wp_parse_url( $request, PHP_URL_PATH ), '/' );

					$this->assertSame( $route_root, $wp->request, $request . ' normalization' );
					$this->assertFalse( bbp_do_not_guess_404_permalink( true ), $request . ' redirect' );
				}
			}
		} finally {
			$wp->request = $original_request;
			remove_filter( 'bbp_include_root_slug', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_do_not_guess_404_permalink
	 */
	public function test_bbp_do_not_guess_404_permalink_for_custom_route_roots() {
		global $wp;

		$original_request = $wp->request;
		$slug_options     = array(
			'_bbp_topic_slug'     => 'discussion',
			'_bbp_topic_tag_slug' => 'discussion-label',
			'_bbp_reply_slug'     => 'response',
			'_bbp_user_slug'      => 'members',
			'_bbp_view_slug'      => 'collection',
		);
		$original_options = array();

		add_filter( 'bbp_include_root_slug', '__return_false' );

		try {
			foreach ( $slug_options as $option_name => $slug ) {
				$original_options[ $option_name ] = get_option( $option_name, false );
				update_option( $option_name, $slug );
			}

			$route_roots = bbp_get_route_root_slugs();

			$this->assertSame( array_values( $slug_options ), $route_roots );

			foreach ( $route_roots as $route_root ) {
				$wp->request = $route_root;
				$this->assertFalse( bbp_do_not_guess_404_permalink( true ), $route_root );
			}
		} finally {
			foreach ( $original_options as $option_name => $slug ) {
				if ( false === $slug ) {
					delete_option( $option_name );
				} else {
					update_option( $option_name, $slug );
				}
			}

			$wp->request = $original_request;
			remove_filter( 'bbp_include_root_slug', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_do_not_guess_404_permalink
	 * @covers ::bbp_get_route_root_slugs
	 */
	public function test_bbp_do_not_guess_404_permalink_for_filtered_route_root() {
		global $wp;

		$original_request = $wp->request;
		$filter           = function ( $route_roots ) {
			$route_roots[] = 'archive';

			return $route_roots;
		};

		add_filter( 'bbp_get_route_root_slugs', $filter );

		try {
			$wp->request = 'archive';

			$this->assertContains( 'archive', bbp_get_route_root_slugs() );
			$this->assertFalse( bbp_do_not_guess_404_permalink( true ) );
		} finally {
			$wp->request = $original_request;
			remove_filter( 'bbp_get_route_root_slugs', $filter );
		}
	}

	/**
	 * @covers ::bbp_do_not_guess_404_permalink
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_incomplete_route_roots_do_not_start_canonical_redirect_chain() {
		global $wp;

		$original_request   = $wp->request;
		$original_name      = get_query_var( 'name' );
		$original_post_type = get_query_var( 'post_type' );

		add_filter( 'bbp_include_root_slug', '__return_false' );
		set_query_var( 'name', 'topic' );
		set_query_var( 'post_type', '' );

		try {
			$route_roots = bbp_get_route_root_slugs();

			foreach ( $route_roots as $route_root ) {
				$post_id     = self::factory()->post->create( array( 'post_name' => $route_root . '-unrelated-target' ) );
				$wp->request = $route_root;
				set_query_var( 'name', $route_root );

				remove_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
				$this->assertSame( get_permalink( $post_id ), redirect_guess_404_permalink(), $route_root . ' reproduction' );
				add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );

				$this->assertFalse( redirect_guess_404_permalink(), $route_root . ' redirect' );
			}

			$post_id     = self::factory()->post->create( array( 'post_name' => 'topic-nearby-target' ) );
			$wp->request = bbp_get_topic_slug() . '-similar';
			set_query_var( 'name', 'topic-nearby' );
			$this->assertSame( get_permalink( $post_id ), redirect_guess_404_permalink() );
		} finally {
			$wp->request = $original_request;
			if ( false === has_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' ) ) {
				add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
			}
			remove_filter( 'bbp_include_root_slug', '__return_false' );
			set_query_var( 'name', $original_name );
			set_query_var( 'post_type', $original_post_type );
		}
	}

	/**
	 * @covers ::bbp_set_404
	 * @todo   Implement test_bbp_set_404().
	 */
	public function test_bbp_set_404() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}
}
