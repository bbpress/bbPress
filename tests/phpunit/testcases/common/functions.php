<?php
/**
 * Tests for the common functions.
 *
 * @group common
 * @group functions
 */

class BBP_Tests_Common_Functions extends BBP_UnitTestCase {

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
	 * @covers ::bbp_past_edit_lock
	 * @todo   Implement test_bbp_past_edit_lock().
	 */
	public function test_bbp_past_edit_lock() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_statistics
	 * @todo   Implement test_bbp_get_statistics().
	 */
	public function test_bbp_get_statistics() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
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
	 * @todo   Implement test_bbp_check_for_moderation().
	 */
	public function test_bbp_check_for_moderation() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_check_for_blacklist
	 * @todo   Implement test_bbp_check_for_blacklist().
	 */
	public function test_bbp_check_for_blacklist() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_get_do_not_reply_address
	 * @todo   Implement test_bbp_get_do_not_reply_address().
	 */
	public function test_bbp_get_do_not_reply_address() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_notify_topic_subscribers
	 * @todo   Implement test_bbp_notify_topic_subscribers().
	 */
	public function test_bbp_notify_topic_subscribers() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers ::bbp_notify_forum_subscribers
	 * @todo   Implement test_bbp_notify_forum_subscribers().
	 */
	public function test_bbp_notify_forum_subscribers() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
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
	 * @todo   Implement test_bbp_logout_url().
	 */
	public function test_bbp_logout_url() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
		);
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
	 * @todo   Implement test_bbp_request_feed_trap().
	 */
	public function test_bbp_request_feed_trap() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
			'This test has not been implemented yet.'
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
