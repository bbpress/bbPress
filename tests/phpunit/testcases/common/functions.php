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
	 * @covers ::bbp_get_unique_array_values
	 * @ticket BBP3717
	 *
	 * @requires PHP 8.0
	 */
	public function test_bbp_get_unique_array_values_preserves_named_arguments() {
		$this->assertSame(
			array( 0 => 'first', 3 => 'second' ),
			call_user_func_array(
				'bbp_get_unique_array_values',
				array(
					'array' => array( 'first', '', 'first', 'second' ),
				)
			)
		);
	}

	/**
	 * @covers ::bbp_number_format
	 */
	public function test_bbp_number_format() {
		$this->assertSame( '1,234', bbp_number_format( 1234 ) );
		$this->assertSame( '1 234,50', bbp_number_format( 1234.5, 2, ',', ' ' ) );
		$this->assertSame( '0', bbp_number_format( 'not a number' ) );
	}

	/**
	 * @covers ::bbp_number_format_i18n
	 */
	public function test_bbp_number_format_i18n() {
		$this->assertSame( number_format_i18n( 1234.5, 2 ), bbp_number_format_i18n( 1234.5, 2 ) );
		$this->assertSame( number_format_i18n( 0 ), bbp_number_format_i18n( 'not a number' ) );
	}

	/**
	 * @covers ::bbp_convert_date
	 */
	public function test_bbp_convert_date() {
		$this->assertSame( '2020-01-02', bbp_convert_date( '2020-01-02 03:04:05', 'Y-m-d' ) );
		$this->assertSame( mysql2date( 'U', '2020-01-02 03:04:05' ), bbp_convert_date( '2020-01-02 03:04:05' ) );
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
	 */
	public function test_bbp_format_revision_reason() {
		$this->assertSame( '', bbp_format_revision_reason() );
		$this->assertSame( 'Correct typo', bbp_format_revision_reason( '  Correct typo...  ' ) );
		$this->assertSame( 'Correct typo', bbp_format_revision_reason( 'Correct typo...' ) );
	}

	/**
	 * @covers ::bbp_get_redirect_to
	 */
	public function test_bbp_get_redirect_to() {
		$old_request = $_REQUEST;
		try {
			unset( $_REQUEST['redirect_to'] );
			$this->assertSame( '', bbp_get_redirect_to() );
			$_REQUEST['redirect_to'] = '/forums/';
			$this->assertSame( '/forums/', bbp_get_redirect_to() );
		} finally {
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_add_view_all
	 */
	public function test_bbp_add_view_all() {
		$url = 'https://example.org/forums/?paged=2';
		$this->assertSame( $url, bbp_add_view_all( $url ) );
		$this->assertSame( 'https://example.org/forums/?paged=2&view=all', bbp_add_view_all( $url, true ) );
	}

	/**
	 * @covers ::bbp_remove_view_all
	 */
	public function test_bbp_remove_view_all() {
		$this->assertSame( 'https://example.org/forums/?paged=2', bbp_remove_view_all( 'https://example.org/forums/?paged=2&view=all' ) );
		$this->assertSame( 'https://example.org/forums/', bbp_remove_view_all( 'https://example.org/forums/' ) );
	}

	/**
	 * @covers ::bbp_get_view_all
	 */
	public function test_bbp_get_view_all() {
		$old_get  = $_GET;
		$old_user = get_current_user_id();
		try {
			$_GET['view'] = 'all';
			wp_set_current_user( 0 );
			$this->assertFalse( bbp_get_view_all() );
			$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
			bbp_set_user_role( $user_id, bbp_get_keymaster_role() );
			wp_set_current_user( $user_id );
			$this->assertTrue( current_user_can( 'moderate' ) );
			$this->assertTrue( bbp_get_view_all() );
			$_GET['view'] = 'single';
			$this->assertFalse( bbp_get_view_all() );
		} finally {
			$_GET = $old_get;
			wp_set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_get_paged
	 */
	public function test_bbp_get_paged() {
		$wp_query      = bbp_get_wp_query();
		$old_query     = $wp_query->query;
		$old_query_var = get_query_var( 'paged' );
		try {
			unset( $wp_query->query['paged'] );
			set_query_var( 'paged', 0 );
			$this->assertSame( 1, bbp_get_paged() );
			$wp_query->query['paged'] = '3';
			$this->assertSame( 3, bbp_get_paged() );
			set_query_var( 'paged', '2' );
			$this->assertSame( 2, bbp_get_paged() );
		} finally {
			$wp_query->query = $old_query;
			set_query_var( 'paged', $old_query_var );
		}
	}

	/**
	 * @covers ::bbp_filter_post_title_data
	 */
	public function test_bbp_filter_post_title_data_removes_unsafe_markup_from_bbp_titles() {
		$title = wp_slash( 'Fish &amp; Chips <strong onclick="alert(1)">Discussion</strong>' );

		foreach ( bbp_get_post_types() as $post_type ) {
			$data = array(
				'post_type'  => $post_type,
				'post_title' => $title,
			);

			$this->assertSame( 'Fish &amp; Chips <strong>Discussion</strong>', wp_unslash( bbp_filter_post_title_data( $data )['post_title'] ) );
		}
	}

	/**
	 * @covers ::bbp_filter_post_title_data
	 */
	public function test_bbp_filter_post_title_data_ignores_other_post_types() {
		$data = array(
			'post_type'  => 'post',
			'post_title' => '<strong>Post title</strong>',
		);

		$this->assertSame( $data, bbp_filter_post_title_data( $data ) );
	}

	/**
	 * @covers ::bbp_filter_post_title_data
	 */
	public function test_bbp_titles_are_sanitized_on_insert_and_update() {
		$old_user = get_current_user_id();
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );

		try {
			foreach ( bbp_get_post_types() as $post_type ) {
				$post_id = wp_insert_post(
					array(
						'post_type'   => $post_type,
						'post_status' => 'draft',
						'post_title'  => '<img src=x onerror=alert(1)>Inserted title',
					)
				);

				$inserted_title = get_post_field( 'post_title', $post_id, 'raw' );
				$this->assertStringContainsString( 'Inserted title', $inserted_title );
				$this->assertStringNotContainsString( 'onerror', $inserted_title );

				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => "<svg onload=alert(1)></svg>John's updated title",
					)
				);

				$updated_title = get_post_field( 'post_title', $post_id, 'raw' );
				$this->assertStringContainsString( "John's updated title", $updated_title );
				$this->assertStringNotContainsString( 'onload', $updated_title );
			}
		} finally {
			wp_set_current_user( $old_user );
		}
	}

	/**
	 * @covers ::bbp_filter_post_title
	 */
	public function test_bbp_filter_post_title_removes_unsafe_legacy_markup() {
		global $wpdb;

		$title   = '<img src=x onerror=alert(1)>Legacy title';
		$getters = array(
			bbp_get_forum_post_type() => 'bbp_get_forum_title',
			bbp_get_topic_post_type() => 'bbp_get_topic_title',
			bbp_get_reply_post_type() => 'bbp_get_reply_title',
		);

		foreach ( $getters as $post_type => $getter ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_status' => 'draft',
					'post_title'  => 'Fixture title',
				)
			);

			$wpdb->update(
				$wpdb->posts,
				array( 'post_title' => $title ),
				array( 'ID' => $post_id )
			);
			clean_post_cache( $post_id );

			$filtered_titles = array(
				apply_filters( 'the_title', $title, $post_id ),
				$getter( $post_id ),
			);

			foreach ( $filtered_titles as $filtered_title ) {
				$this->assertStringContainsString( 'Legacy title', $filtered_title );
				$this->assertStringNotContainsString( 'onerror', $filtered_title );
			}
		}
	}

	/**
	 * @covers ::bbp_filter_post_title
	 */
	public function test_bbp_filter_post_title_ignores_other_post_types() {
		$post_id = $this->factory->post->create();

		$this->assertSame( '<strong>Post title</strong>', apply_filters( 'the_title', '<strong>Post title</strong>', $post_id ) );
	}

	/**
	 * @covers ::bbp_filter_post_title
	 */
	public function test_bbp_filter_post_title_preserves_later_bbp_title_filters() {
		$topic_id = $this->factory->topic->create( array( 'post_title' => 'Topic title' ) );
		$filter   = function ( $title ) {
			return '<strong>' . $title . '</strong>';
		};

		add_filter( 'bbp_get_topic_title', $filter );

		try {
			$this->assertSame( '<strong>Topic title</strong>', bbp_get_topic_title( $topic_id ) );
		} finally {
			remove_filter( 'bbp_get_topic_title', $filter );
		}
	}

	/**
	 * @covers ::bbp_fix_post_author
	 */
	public function test_bbp_fix_post_author() {
		$author_id = $this->factory->user->create();
		$topic_id  = $this->factory->topic->create( array( 'post_author' => 0 ) );
		$data      = array( 'post_type' => bbp_get_topic_post_type(), 'post_author' => $author_id );
		$this->assertSame( 0, bbp_fix_post_author( $data, array( 'ID' => $topic_id ) )['post_author'] );
		$this->assertSame( $data, bbp_fix_post_author( $data, array() ) );
		$authored_topic = $this->factory->topic->create( array( 'post_author' => $author_id ) );
		$this->assertSame( $data, bbp_fix_post_author( $data, array( 'ID' => $authored_topic ) ) );
		$non_bbp_data = array( 'post_type' => 'post', 'post_author' => $author_id );
		$this->assertSame( $non_bbp_data, bbp_fix_post_author( $non_bbp_data, array( 'ID' => $this->factory->post->create() ) ) );
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
	 */
	public function test_bbp_filter_anonymous_post_data() {
		$error_messages = bbpress()->errors->get_error_messages();
		$result = bbp_filter_anonymous_post_data( array(
			'bbp_anonymous_name'    => 'Forum Visitor',
			'bbp_anonymous_email'   => 'visitor@example.org',
			'bbp_anonymous_website' => '',
		) );
		$this->assertSame( 'Forum Visitor', $result['bbp_anonymous_name'] );
		$this->assertSame( 'visitor@example.org', $result['bbp_anonymous_email'] );
		$this->assertSame( '', $result['bbp_anonymous_website'] );
		$this->assertSame( $error_messages, bbpress()->errors->get_error_messages() );
	}

	/**
	 * @covers ::bbp_check_for_duplicate
	 */
	public function test_bbp_check_for_duplicate() {
		$author_id = $this->factory->user->create();
		$topic_id  = $this->factory->topic->create( array(
			'post_author'  => $author_id,
			'post_content' => 'Duplicate topic text',
			'post_status'  => 'publish',
		) );
		$post_data = array(
			'post_author'  => $author_id,
			'post_type'    => bbp_get_topic_post_type(),
			'post_content' => 'Duplicate topic text',
		);
		$this->assertFalse( bbp_check_for_duplicate( $post_data ) );
		$post_data['post_content'] = 'A different topic';
		$this->assertTrue( bbp_check_for_duplicate( $post_data ) );
		$this->assertSame( $author_id, (int) get_post_field( 'post_author', $topic_id ) );
	}

	/**
	 * @covers ::bbp_check_for_flood
	 */
	public function test_bbp_check_for_flood() {
		$old_throttle = get_option( '_bbp_throttle_time', null );
		$key          = '_bbp_' . bbp_current_author_ip() . '_last_posted';
		$old_posted   = get_transient( $key );
		try {
			update_option( '_bbp_throttle_time', 60 );
			set_transient( $key, time() );
			$this->assertFalse( bbp_check_for_flood( array( 'bbp_anonymous_email' => 'visitor@example.org' ) ) );
			set_transient( $key, time() - 120 );
			$this->assertTrue( bbp_check_for_flood( array( 'bbp_anonymous_email' => 'visitor@example.org' ) ) );
		} finally {
			if ( is_null( $old_throttle ) ) {
				delete_option( '_bbp_throttle_time' );
			} else {
				update_option( '_bbp_throttle_time', $old_throttle );
			}
			if ( false === $old_posted ) {
				delete_transient( $key );
			} else {
				set_transient( $key, $old_posted );
			}
		}
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

		delete_option( '_bbp_allow_moderation_links' );
		delete_option( '_bbp_moderation_links' );
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
	 * @ticket BBP3352
	 * @covers ::bbp_check_for_moderation
	 */
	public function test_should_use_forum_specific_moderation_link_limit() {
		$anonymous_data = false;
		$author_id      = 0;
		$title          = 'Sting';
		$content        = 'This is a post with <a href="http://example.com">multiple</a> <a href="http://bob.example.com">links</a>.';

		update_option( 'comment_max_links', 2 );
		update_option( '_bbp_moderation_links', 3 );
		$this->assertTrue( bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content ) );

		update_option( 'comment_max_links', 3 );
		update_option( '_bbp_moderation_links', 2 );
		$this->assertFalse( bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content ) );

		update_option( '_bbp_allow_moderation_links', 0 );
		$this->assertTrue( bbp_check_for_moderation( $anonymous_data, $author_id, $title, $content ) );
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
	 */
	public function test_bbp_notify_subscribers() {
		$this->assertFalse( bbp_notify_subscribers() );
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
	 */
	public function test_bbp_parse_args() {
		$defaults = array( 'count' => 1, 'order' => 'ASC' );
		$this->assertSame( array( 'count' => 2, 'order' => 'ASC' ), bbp_parse_args( array( 'count' => 2 ), $defaults ) );
		$this->assertSame( array( 'count' => '3', 'order' => 'DESC' ), bbp_parse_args( 'count=3&order=DESC', $defaults ) );

		$before = function( $args ) {
			$args['count'] = 4;
			return $args;
		};
		$after = function( $args ) {
			$args['order'] = 'DESC';
			return $args;
		};
		add_filter( 'bbp_before_common_test_parse_args', $before );
		add_filter( 'bbp_after_common_test_parse_args', $after );
		try {
			$this->assertSame( array( 'count' => 4, 'order' => 'DESC' ), bbp_parse_args( array(), $defaults, 'common_test' ) );
		} finally {
			remove_filter( 'bbp_before_common_test_parse_args', $before );
			remove_filter( 'bbp_after_common_test_parse_args', $after );
		}
	}

	/**
	 * @covers ::bbp_get_global_post_field
	 */
	public function test_bbp_get_global_post_field() {
		global $post;
		$old_post = $post;
		$post_id  = $this->factory->post->create( array( 'post_title' => 'Global field title' ) );
		try {
			$post = get_post( $post_id );
			$this->assertSame( $post_id, bbp_get_global_post_field() );
			$this->assertSame( 'Global field title', bbp_get_global_post_field( 'post_title', 'raw' ) );
			$this->assertSame( '', bbp_get_global_post_field( 'not_a_field' ) );
		} finally {
			$post = $old_post;
		}
	}

	/**
	 * @covers ::bbp_verify_nonce_request
	 */
	public function test_bbp_verify_nonce_request() {
		$old_request = $_REQUEST;
		$action      = 'common-functions-nonce';
		$match       = function() {
			return home_url( '/forums/' );
		};
		add_filter( 'bbp_verify_nonce_request_url', $match );
		try {
			$_REQUEST['_wpnonce'] = wp_create_nonce( $action );
			$this->assertNotFalse( bbp_verify_nonce_request( $action ) );
			$this->assertFalse( bbp_verify_nonce_request( 'wrong-action' ) );
			unset( $_REQUEST['_wpnonce'] );
			$this->assertFalse( bbp_verify_nonce_request( $action ) );
		} finally {
			remove_filter( 'bbp_verify_nonce_request_url', $match );
			$_REQUEST = $old_request;
		}
	}

	/**
	 * @covers ::bbp_request_remove_empty_query_vars
	 */
	public function test_bbp_request_remove_empty_query_vars() {
		$routes = array(
			bbp_get_forum_post_type()  => bbp_get_forum_slug(),
			bbp_get_topic_post_type()  => bbp_get_topic_slug(),
			bbp_get_reply_post_type()  => bbp_get_reply_slug(),
			bbp_get_topic_tag_tax_id() => bbp_get_topic_tag_tax_slug(),
			bbp_get_view_rewrite_id()  => bbp_get_view_slug(),
		);
		$route_query_vars = array_keys( $routes );

		foreach ( $routes as $query_var => $route ) {
			$this->assertSame(
				array(),
				bbp_request( array( $query_var => '0' ) ),
				'/' . $route . '/0/'
			);
		}

		foreach ( array( '', '0', 0, false, array() ) as $empty_value ) {
			$query_vars              = array_fill_keys( $route_query_vars, $empty_value );
			$query_vars['unrelated'] = $empty_value;

			$this->assertSame(
				array( 'unrelated' => $empty_value ),
				bbp_request_remove_empty_query_vars( $query_vars )
			);
		}

		$query_vars = array_combine(
			$route_query_vars,
			array( 'forum-slug', 'topic-slug', 'reply-slug', 'topic-tag-slug', 'view-slug' )
		);

		$this->assertSame( $query_vars, bbp_request_remove_empty_query_vars( $query_vars ) );
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
	 */
	public function test_bbp_get_page_by_path() {
		global $wp_rewrite;
		$old_permalinks = $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$slug = 'common-test-page';
		try {
			$this->assertFalse( bbp_get_page_by_path( $slug ) );
			$page_id = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => $slug, 'post_status' => 'publish' ) );
			$this->assertSame( $page_id, bbp_get_page_by_path( $slug )->ID );
		} finally {
			$wp_rewrite->set_permalink_structure( $old_permalinks );
		}
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
		$forum_id     = $this->factory->forum->create();
		$forum_object = (object) get_post( $forum_id )->to_array();
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
	 * @covers ::redirect_guess_404_permalink
	 */
	public function test_bbp_do_not_guess_404_permalink_for_hidden_topic_request() {
		global $wp;

		$original_request             = $wp->request;
		$original_name                = get_query_var( 'name' );
		$original_post_type           = get_query_var( 'post_type' );
		$topic_post_type              = get_post_type_object( bbp_get_topic_post_type() );
		$original_exclude_from_search = $topic_post_type->exclude_from_search;
		$original_publicly_queryable  = $topic_post_type->publicly_queryable;
		$forum_id                     = $this->factory->forum->create();
		$hidden_topic_id              = $this->factory->topic->create( array(
			'post_name'   => 'hidden-topic-request',
			'post_parent' => $forum_id,
			'post_status' => bbp_get_spam_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$public_topic_id              = $this->factory->topic->create( array(
			'post_name'   => 'hidden-topic-request-public',
			'post_parent' => $forum_id,
			'post_status' => bbp_get_public_status_id(),
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );

		// Reproduce the historical path where WordPress queried topics for a guess.
		$topic_post_type->exclude_from_search = false;
		$topic_post_type->publicly_queryable  = true;

		set_query_var( 'post_type', bbp_get_topic_post_type() );
		set_query_var( 'name', get_post_field( 'post_name', $hidden_topic_id ) );
		$wp->request = bbp_get_topic_slug() . '/' . get_post_field( 'post_name', $hidden_topic_id );
		$this->set_current_user( 0 );

		try {
			remove_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
			$this->assertSame( get_permalink( $public_topic_id ), redirect_guess_404_permalink() );
			add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );

			$this->assertFalse( redirect_guess_404_permalink() );
		} finally {
			$wp->request = $original_request;
			set_query_var( 'name', $original_name );
			set_query_var( 'post_type', $original_post_type );
			$topic_post_type->exclude_from_search = $original_exclude_from_search;
			$topic_post_type->publicly_queryable  = $original_publicly_queryable;

			if ( false === has_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' ) ) {
				add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
			}
		}
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

					$wp->request = strtoupper( $route_root );
					$this->assertFalse( bbp_do_not_guess_404_permalink( true ), strtoupper( $route_root ) );
				}

				$wp->request = bbp_get_topic_slug() . '/missing-topic';
				$this->assertFalse( bbp_do_not_guess_404_permalink( true ) );

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
	public function test_bbp_do_not_guess_404_permalink_below_route_roots() {
		global $wp;

		$original_request   = $wp->request;
		$original_name      = get_query_var( 'name' );
		$original_post_type = get_query_var( 'post_type' );

		add_filter( 'bbp_include_root_slug', '__return_false' );

		try {
			$post_id = self::factory()->post->create( array( 'post_name' => 'zz1-route-target' ) );

			set_query_var( 'post_type', '' );
			set_query_var( 'name', 'zz1' );
			$wp->request = bbp_get_topic_slug() . '/test/*zz1*';

			remove_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
			$this->assertSame( get_permalink( $post_id ), redirect_guess_404_permalink() );
			add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
			$this->assertFalse( redirect_guess_404_permalink() );

			set_query_var( 'name', 'valid-name' );
			$this->assertFalse( bbp_do_not_guess_404_permalink( true ) );

			foreach ( bbp_get_route_root_slugs() as $route_root ) {
				$wp->request = $route_root . '/test/valid-name';
				$this->assertFalse( bbp_do_not_guess_404_permalink( true ) );

				$wp->request = strtoupper( $route_root ) . '/test/valid-name';
				$this->assertFalse( bbp_do_not_guess_404_permalink( true ), strtoupper( $route_root ) . ' child' );
			}

			$wp->request = '%54OPIC/test/valid-name';
			$this->assertFalse( bbp_do_not_guess_404_permalink( true ), 'percent-encoded mixed-case topic child' );

			$wp->request = ucfirst( bbp_get_topic_slug() ) . '/test/*zz1*';
			set_query_var( 'name', 'zz1' );
			$this->assertFalse( redirect_guess_404_permalink(), 'mixed-case topic child redirect' );

			$wp->request = bbp_get_topic_slug() . '-similar/zz1';
			set_query_var( 'name', 'zz1' );
			$this->assertTrue( bbp_do_not_guess_404_permalink( true ) );

			$wp->request = strtoupper( bbp_get_topic_slug() ) . '-similar/zz1';
			$this->assertTrue( bbp_do_not_guess_404_permalink( true ) );

			remove_filter( 'bbp_include_root_slug', '__return_false' );
			$wp->request = bbp_get_topic_slug() . '/test/valid-name';
			set_query_var( 'name', 'valid-name' );
			$this->assertFalse( bbp_do_not_guess_404_permalink( true ) );
		} finally {
			$wp->request = $original_request;
			set_query_var( 'name', $original_name );
			set_query_var( 'post_type', $original_post_type );
			if ( false === has_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' ) ) {
				add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
			}
			remove_filter( 'bbp_include_root_slug', '__return_false' );
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
				$post_id      = self::factory()->post->create( array( 'post_name' => $route_root . '-unrelated-target' ) );
				$wp->request  = $route_root;
				set_query_var( 'name', $route_root );

				remove_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );
				$this->assertSame( get_permalink( $post_id ), redirect_guess_404_permalink(), $route_root . ' reproduction' );
				add_filter( 'do_redirect_guess_404_permalink', 'bbp_do_not_guess_404_permalink' );

				$this->assertFalse( redirect_guess_404_permalink(), $route_root . ' redirect' );

				$wp->request = strtoupper( $route_root );
				set_query_var( 'name', strtoupper( $route_root ) );
				$this->assertFalse( redirect_guess_404_permalink(), strtoupper( $route_root ) . ' redirect' );
			}

			$post_id      = self::factory()->post->create( array( 'post_name' => 'topic-nearby-target' ) );
			$wp->request  = bbp_get_topic_slug() . '-similar';
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
	 * @covers ::bbp_get_post_types
	 */
	public function test_bbp_get_post_types_returns_only_matching_bbpress_types() {
		$this->assertEqualSets(
			array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
			bbp_get_post_types()
		);
		$this->assertSame( array( bbp_get_forum_post_type() ), array_values( bbp_get_post_types( array( 'hierarchical' => true ) ) ) );
		$this->assertSame( array(), array_values( bbp_get_post_types( array( 'public' => false ) ) ) );
	}

	/**
	 * @covers ::bbp_filter_use_block_editor_for_post_type
	 */
	public function test_bbp_filter_use_block_editor_for_post_type_disables_bbpress_and_preserves_other_types() {
		$this->assertFalse( bbp_filter_use_block_editor_for_post_type( true, bbp_get_topic_post_type() ) );
		$this->assertFalse( bbp_filter_use_block_editor_for_post_type( false, 'post' ) );
		$this->assertTrue( bbp_filter_use_block_editor_for_post_type( true, 'post' ) );

		$filter = function( $retval, $is_post_type, $using, $post_type ) {
			$this->assertFalse( $retval );
			$this->assertTrue( $is_post_type );
			$this->assertTrue( $using );
			$this->assertSame( bbp_get_reply_post_type(), $post_type );

			return true;
		};
		add_filter( 'bbp_filter_use_block_editor_for_post_type', $filter, 10, 4 );

		try {
			$this->assertTrue( bbp_filter_use_block_editor_for_post_type( true, bbp_get_reply_post_type() ) );
		} finally {
			remove_filter( 'bbp_filter_use_block_editor_for_post_type', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_filter_use_block_editor_for_post_type
	 */
	public function test_bbp_filter_use_block_editor_for_post_type_preserves_earlier_filter_decision() {
		$filter = function() {
			return false;
		};
		add_filter( 'use_block_editor_for_post_type', $filter, 5 );

		try {
			$this->assertFalse( use_block_editor_for_post_type( 'post' ) );
		} finally {
			remove_filter( 'use_block_editor_for_post_type', $filter, 5 );
		}
	}

	/**
	 * @covers ::bbp_fix_untrash_post_status
	 */
	public function test_bbp_fix_untrash_post_status_restores_bbpress_status_only() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$post_id  = $this->factory->post->create();

		$this->assertSame( bbp_get_private_status_id(), bbp_fix_untrash_post_status( 'draft', $forum_id, bbp_get_private_status_id() ) );
		$this->assertSame( bbp_get_pending_status_id(), bbp_fix_untrash_post_status( 'draft', $reply_id, bbp_get_pending_status_id() ) );
		$this->assertSame( 'draft', bbp_fix_untrash_post_status( 'draft', $forum_id, '' ) );
		$this->assertSame( 'draft', bbp_fix_untrash_post_status( 'draft', $post_id, 'publish' ) );
		$this->assertSame( 'draft', bbp_fix_untrash_post_status( 'draft', 999999, 'publish' ) );
	}

	/**
	 * @covers ::bbp_get_trash_days
	 */
	public function test_bbp_get_trash_days_sanitizes_context_and_applies_filter() {
		$default = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30;

		$this->assertSame( $default, bbp_get_trash_days() );

		$filter = function( $days, $context ) use ( $default ) {
			$this->assertSame( $default, $days );
			$this->assertSame( 'topiccontext', $context );

			return '7';
		};
		add_filter( 'bbp_get_trash_days', $filter, 10, 2 );

		try {
			$this->assertSame( 7, bbp_get_trash_days( 'Topic Context!' ) );
		} finally {
			remove_filter( 'bbp_get_trash_days', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_pre_handle_404
	 * @covers ::bbp_posts_pre_query
	 */
	public function test_bbp_query_short_circuits_follow_custom_404_state() {
		$query = new WP_Query();
		$status = null;
		$filter = function( $status_header, $code ) use ( &$status ) {
			$status = $code;

			return $status_header;
		};
		add_filter( 'status_header', $filter, 10, 2 );

		try {
			$this->assertFalse( bbp_pre_handle_404( false, $query ) );
			$this->assertNull( bbp_posts_pre_query( null, $query ) );

			$query->bbp_is_404 = false;
			$this->assertNull( bbp_posts_pre_query( null, $query ) );

			$query->bbp_is_404 = true;
			$this->assertTrue( bbp_pre_handle_404( false, $query ) );
			$this->assertSame( 404, $status );
			$this->assertTrue( $query->is_404() );
			$this->assertSame( array(), bbp_posts_pre_query( null, $query ) );
			$this->assertSame( array( 'existing' ), bbp_posts_pre_query( array( 'existing' ), new WP_Query() ) );
		} finally {
			remove_filter( 'status_header', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_pre_handle_404
	 */
	public function test_bbp_pre_handle_404_restores_success_status() {
		$query           = new WP_Query();
		$query->bbp_is_404 = false;
		$status          = null;
		$filter          = function( $status_header, $code ) use ( &$status ) {
			$status = $code;

			return $status_header;
		};
		add_filter( 'status_header', $filter, 10, 2 );

		try {
			$this->assertTrue( bbp_pre_handle_404( false, $query ) );
			$this->assertSame( 200, $status );
		} finally {
			remove_filter( 'status_header', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_url_scheme
	 */
	public function test_bbp_get_url_scheme_matches_ssl_state() {
		$https = isset( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : null;
		$port  = isset( $_SERVER['SERVER_PORT'] ) ? $_SERVER['SERVER_PORT'] : null;

		try {
			$_SERVER['HTTPS'] = 'off';
			$_SERVER['SERVER_PORT'] = '80';
			$this->assertSame( 'http://', bbp_get_url_scheme() );

			$_SERVER['HTTPS'] = 'on';
			$this->assertSame( 'https://', bbp_get_url_scheme() );
		} finally {
			if ( null === $https ) {
				unset( $_SERVER['HTTPS'] );
			} else {
				$_SERVER['HTTPS'] = $https;
			}

			if ( null === $port ) {
				unset( $_SERVER['SERVER_PORT'] );
			} else {
				$_SERVER['SERVER_PORT'] = $port;
			}
		}
	}

	/**
	 * @covers ::bbp_is_title_too_long
	 */
	public function test_bbp_is_title_too_long_uses_character_length_and_filter() {
		$max_filter = function() {
			return 3;
		};
		$filter = function( $result, $title, $max, $len ) {
			$this->assertTrue( $result );
			$this->assertSame( '猫猫猫猫', $title );
			$this->assertSame( 3, $max );
			$this->assertSame( 4, $len );

			return false;
		};
		try {
			add_filter( 'bbp_get_title_max_length', $max_filter );
			$this->assertFalse( bbp_is_title_too_long( '猫猫猫' ) );
			$this->assertTrue( bbp_is_title_too_long( '猫猫猫猫' ) );

			add_filter( 'bbp_is_title_too_long', $filter, 10, 4 );
			$this->assertFalse( bbp_is_title_too_long( '猫猫猫猫' ) );
		} finally {
			remove_filter( 'bbp_get_title_max_length', $max_filter );
			remove_filter( 'bbp_is_title_too_long', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_sanitize_anonymous_post_author
	 */
	public function test_bbp_sanitize_anonymous_post_author_normalizes_input() {
		$data = array(
			'bbp_anonymous_name'    => "Invalid \xC3\x28 Author",
			'bbp_anonymous_email'   => 'anonymous@example.org',
			'bbp_anonymous_website' => 'https://example.org/',
			'custom'                => 'preserved',
		);
		$filter = function( $sanitized, $original ) use ( $data ) {
			$this->assertSame( $data, $original );

			return $sanitized;
		};
		$this->assertSame( array(), bbp_sanitize_anonymous_post_author( 'invalid' ) );
		add_filter( 'bbp_sanitize_anonymous_post_author', $filter, 10, 2 );

		try {
			$sanitized = bbp_sanitize_anonymous_post_author( $data );
			$this->assertNotSame( $data['bbp_anonymous_name'], $sanitized['bbp_anonymous_name'] );
			$this->assertStringNotContainsString( "\xC3", $sanitized['bbp_anonymous_name'] );
			$this->assertSame( 'anonymous@example.org', $sanitized['bbp_anonymous_email'] );
			$this->assertSame( 'https://example.org/', $sanitized['bbp_anonymous_website'] );
			$this->assertSame( 'preserved', $sanitized['custom'] );
		} finally {
			remove_filter( 'bbp_sanitize_anonymous_post_author', $filter, 10 );
		}
	}

	/**
	 * @covers ::bbp_update_anonymous_post_author
	 */
	public function test_bbp_update_anonymous_post_author_updates_and_deletes_meta() {
		$topic_id = $this->factory->topic->create( array( 'post_author' => 0 ) );

		update_post_meta( $topic_id, '_bbp_anonymous_website', 'https://old.example.org/' );
		bbp_update_anonymous_post_author(
			$topic_id,
			array(
				'bbp_anonymous_name'    => 'Anonymous Author',
				'bbp_anonymous_email'   => 'anonymous@example.org',
				'bbp_anonymous_website' => '',
			),
			bbp_get_topic_post_type()
		);

		$this->assertSame( 'Anonymous Author', get_post_meta( $topic_id, '_bbp_anonymous_name', true ) );
		$this->assertSame( 'anonymous@example.org', get_post_meta( $topic_id, '_bbp_anonymous_email', true ) );
		$this->assertFalse( metadata_exists( 'post', $topic_id, '_bbp_anonymous_website' ) );
	}

	/**
	 * @covers ::bbp_check_for_blacklist
	 */
	public function test_bbp_check_for_blacklist_uses_strict_moderation_keys() {
		$old_disallowed_keys = get_option( 'disallowed_keys' );
		$old_moderation_keys = get_option( 'moderation_keys' );
		update_option( 'disallowed_keys', 'blocked phrase' );
		update_option( 'moderation_keys', 'review phrase' );

		try {
			$this->assertFalse( bbp_check_for_blacklist( array(), 0, '', 'Contains a blocked phrase.' ) );
			$this->assertTrue( bbp_check_for_blacklist( array(), 0, '', 'Contains a review phrase.' ) );
			$this->assertTrue( bbp_check_for_blacklist( array(), 0, '', 'Allowed content.' ) );
		} finally {
			update_option( 'disallowed_keys', $old_disallowed_keys );
			update_option( 'moderation_keys', $old_moderation_keys );
		}
	}

	/**
	 * @covers ::bbp_suspend_moderation_notifications
	 * @covers ::bbp_resume_moderation_notifications
	 * @covers ::bbp_is_moderation_notifications_suspended
	 */
	public function test_bbp_moderation_notification_suspension_tracks_nested_depth() {
		$had_global = array_key_exists( '_bbp_moderation_notifications_suspended', $GLOBALS );
		$original   = $had_global ? $GLOBALS['_bbp_moderation_notifications_suspended'] : null;
		unset( $GLOBALS['_bbp_moderation_notifications_suspended'] );

		try {
			$this->assertFalse( bbp_is_moderation_notifications_suspended() );
			$this->assertSame( 1, bbp_suspend_moderation_notifications() );
			$this->assertSame( 2, bbp_suspend_moderation_notifications() );
			$this->assertTrue( bbp_is_moderation_notifications_suspended() );
			$this->assertSame( 1, bbp_resume_moderation_notifications() );
			$this->assertTrue( bbp_is_moderation_notifications_suspended() );
			$this->assertSame( 0, bbp_resume_moderation_notifications() );
			$this->assertFalse( bbp_is_moderation_notifications_suspended() );
			$this->assertSame( 0, bbp_resume_moderation_notifications() );
		} finally {
			if ( $had_global ) {
				$GLOBALS['_bbp_moderation_notifications_suspended'] = $original;
			} else {
				unset( $GLOBALS['_bbp_moderation_notifications_suspended'] );
			}
		}
	}

	/**
	 * @covers ::bbp_get_email_addresses_from_user_ids
	 */
	public function test_bbp_get_email_addresses_from_user_ids_chunks_and_deduplicates_users() {
		$user_ids = array(
			$this->factory->user->create( array( 'user_email' => 'first@example.org' ) ),
			$this->factory->user->create( array( 'user_email' => 'second@example.org' ) ),
			$this->factory->user->create( array( 'user_email' => 'third@example.org' ) ),
		);
		$filter = function() {
			return 2;
		};
		$queries = 0;
		$count_queries = function() use ( &$queries ) {
			$queries++;
		};
		add_filter( 'bbp_get_users_chunk_limit', $filter );
		add_action( 'pre_get_users', $count_queries );

		try {
			$this->assertEqualSets(
				array( 'first@example.org', 'second@example.org', 'third@example.org' ),
				bbp_get_email_addresses_from_user_ids( array( $user_ids[0], $user_ids[1], $user_ids[0], $user_ids[2] ) )
			);
			$this->assertSame( 2, $queries );
			$this->assertSame( array(), bbp_get_email_addresses_from_user_ids() );

			if ( is_multisite() ) {
				$user_id = $this->factory->user->create( array( 'user_email' => 'network@example.org' ) );
				remove_user_from_blog( $user_id, get_current_blog_id() );
				$this->assertSame( array( 'network@example.org' ), bbp_get_email_addresses_from_user_ids( array( $user_id ) ) );
			}
		} finally {
			remove_filter( 'bbp_get_users_chunk_limit', $filter );
			remove_action( 'pre_get_users', $count_queries );
		}
	}

	/**
	 * @covers ::bbp_chunk_emails
	 */
	public function test_bbp_chunk_emails_sends_additional_chunks_and_returns_first_chunk() {
		$mail   = array();
		$limit  = function() {
			return 2;
		};
		$capture = function( $return, $atts ) use ( &$mail ) {
			$mail[] = $atts;

			return true;
		};
		$args = array(
			'to'          => 'forum@example.org',
			'subject'     => 'New reply',
			'message'     => 'A reply was posted.',
			'headers'     => array(
				'X-bbPress: test',
				'Bcc: first@example.org',
				'Bcc: second@example.org',
				'Bcc: third@example.org',
				'Bcc: fourth@example.org',
				'Bcc: fifth@example.org',
			),
			'attachments' => array(),
		);
		add_filter( 'bbp_get_bcc_chunk_limit', $limit );
		add_filter( 'pre_wp_mail', $capture, 10, 2 );

		try {
			$filtered = bbp_chunk_emails( $args );

			$this->assertSame(
				array( 'X-bbPress: test', 'Bcc: first@example.org', 'Bcc: second@example.org' ),
				$filtered['headers']
			);
			$this->assertCount( 2, $mail );
			$this->assertSame( array( 'X-bbPress: test', 'Bcc: third@example.org', 'Bcc: fourth@example.org' ), $mail[0]['headers'] );
			$this->assertSame( array( 'X-bbPress: test', 'Bcc: fifth@example.org' ), $mail[1]['headers'] );
			$this->assertSame( $args['to'], $mail[0]['to'] );
			$this->assertSame( $args['subject'], $mail[0]['subject'] );

			$below_limit            = $args;
			$below_limit['headers'] = array( 'X-bbPress: test', 'Bcc: first@example.org' );
			$this->assertSame( $below_limit, bbp_chunk_emails( $below_limit ) );

			$exact_limit            = $args;
			$exact_limit['headers'] = array( 'X-bbPress: test', 'Bcc: first@example.org', 'Bcc: second@example.org' );
			$this->assertSame( $exact_limit, bbp_chunk_emails( $exact_limit ) );

			$without_bcc            = $args;
			$without_bcc['headers'] = array( 'X-bbPress: test' );
			$this->assertSame( $without_bcc, bbp_chunk_emails( $without_bcc ) );
			$this->assertCount( 2, $mail );
		} finally {
			remove_filter( 'bbp_get_bcc_chunk_limit', $limit );
			remove_filter( 'pre_wp_mail', $capture, 10 );
		}
	}

	/**
	 * @covers ::bbp_get_email_header
	 */
	public function test_bbp_get_email_header_includes_version_and_applies_filter() {
		$this->assertSame( 'X-bbPress: ' . bbp_get_version(), bbp_get_email_header() );

		$filter = function( $header ) {
			$this->assertSame( 'X-bbPress: ' . bbp_get_version(), $header );

			return 'X-bbPress: filtered';
		};
		add_filter( 'bbp_get_email_header', $filter );

		try {
			$this->assertSame( 'X-bbPress: filtered', bbp_get_email_header() );
		} finally {
			remove_filter( 'bbp_get_email_header', $filter );
		}
	}

	/**
	 * @covers ::bbp_filter_child_counts_list
	 * @covers ::bbp_get_non_public_child_count
	 */
	public function test_bbp_child_count_filters_select_types_and_non_public_statuses() {
		$forum_id = $this->factory->forum->create();
		$this->factory->forum->create( array( 'post_parent' => $forum_id ) );
		$this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_pending_status_id(),
			)
		);
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_spam_status_id(),
			)
		);
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_id,
				'post_status' => bbp_get_closed_status_id(),
			)
		);

		$counts = bbp_filter_child_counts_list(
			$forum_id,
			array( bbp_get_topic_post_type() ),
			array( bbp_get_public_status_id(), bbp_get_pending_status_id() )
		);

		$this->assertSame( 1, $counts[ bbp_get_topic_post_type() ][ bbp_get_public_status_id() ] );
		$this->assertSame( 1, $counts[ bbp_get_topic_post_type() ][ bbp_get_pending_status_id() ] );
		$this->assertArrayNotHasKey( bbp_get_spam_status_id(), $counts[ bbp_get_topic_post_type() ] );
		$this->assertArrayNotHasKey( bbp_get_closed_status_id(), $counts[ bbp_get_topic_post_type() ] );
		$this->assertArrayNotHasKey( bbp_get_forum_post_type(), $counts );
		$this->assertSame( 2, bbp_get_non_public_child_count( $forum_id, bbp_get_topic_post_type() ) );
		$this->assertFalse( bbp_get_non_public_child_count( 0, bbp_get_topic_post_type() ) );
	}

	/**
	 * @covers ::bbp_update_post_author_caches
	 */
	public function test_bbp_update_post_author_caches_primes_post_authors() {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create( array( 'post_author' => $user_id ) );

		clean_user_cache( $user_id );
		$this->assertFalse( wp_cache_get( $user_id, 'users' ) );
		$this->assertTrue( bbp_update_post_author_caches( array( $post_id, $post_id ) ) );
		$cached_user = wp_cache_get( $user_id, 'users' );
		$this->assertIsObject( $cached_user );
		$this->assertSame( $user_id, (int) $cached_user->ID );
		$this->assertFalse( bbp_update_post_author_caches() );

		$anonymous_id = $this->factory->post->create( array( 'post_author' => 0 ) );
		$this->assertFalse( bbp_update_post_author_caches( array( $anonymous_id ) ) );
	}

	/**
	 * @covers ::bbp_update_post_family_caches
	 */
	public function test_bbp_update_post_family_caches_primes_related_posts_and_authors() {
		$user_id    = $this->factory->user->create();
		$related_id = $this->factory->post->create( array( 'post_author' => $user_id ) );
		$source_id  = $this->factory->post->create();

		update_post_meta( $source_id, '_bbp_last_reply_id', $related_id );
		clean_post_cache( $related_id );
		clean_user_cache( $user_id );

		try {
			$this->assertFalse( wp_cache_get( $related_id, 'posts' ) );
			$this->assertFalse( wp_cache_get( $user_id, 'users' ) );
			$this->assertTrue( bbp_update_post_family_caches( array( $source_id ) ) );
			$cached_post = wp_cache_get( $related_id, 'posts' );
			$cached_user = wp_cache_get( $user_id, 'users' );
			$this->assertIsObject( $cached_post );
			$this->assertSame( $related_id, (int) $cached_post->ID );
			$this->assertIsObject( $cached_user );
			$this->assertSame( $user_id, (int) $cached_user->ID );
			$this->assertFalse( bbp_update_post_family_caches() );
		} finally {
			clean_post_cache( $related_id );
			clean_user_cache( $user_id );
		}
	}

	/**
	 * @covers ::bbp_set_404
	 */
	public function test_bbp_set_404() {
		$query = new WP_Query();
		bbp_set_404( $query );
		$this->assertTrue( $query->is_404() );
	}
}
