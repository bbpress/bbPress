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
	 * @covers ::bbp_request_feed_trap
	 */
	public function test_bbp_request_feed_trap() {
		$query_vars = array( 'post_type' => bbp_get_topic_post_type(), 'paged' => 2 );
		$this->assertSame( $query_vars, bbp_request_feed_trap( $query_vars ) );
		$this->assertSame( array( 'feed' => 'rss2', 'post_type' => 'post' ), bbp_request_feed_trap( array( 'feed' => 'rss2', 'post_type' => 'post' ) ) );
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
	 * @covers ::bbp_set_404
	 */
	public function test_bbp_set_404() {
		$query = new WP_Query();
		bbp_set_404( $query );
		$this->assertTrue( $query->is_404() );
	}
}
