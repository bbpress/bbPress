<?php

/**
 * Tests for common widgets.
 *
 * @group common
 * @group widgets
 */
class BBP_Tests_Common_Widgets extends BBP_UnitTestCase {

	/**
	 * @covers BBP_Login_Widget::__construct
	 * @covers BBP_Views_Widget::__construct
	 * @covers BBP_Search_Widget::__construct
	 * @covers BBP_Forums_Widget::__construct
	 * @covers BBP_Topics_Widget::__construct
	 * @covers BBP_Stats_Widget::__construct
	 * @covers BBP_Replies_Widget::__construct
	 * @ticket 3706
	 */
	public function test_widget_constructors_apply_option_filters() {
		$widgets = array(
			'BBP_Login_Widget'   => 'bbp_login_widget_options',
			'BBP_Views_Widget'   => 'bbp_views_widget_options',
			'BBP_Search_Widget'  => 'bbp_search_widget_options',
			'BBP_Forums_Widget'  => 'bbp_forums_widget_options',
			'BBP_Topics_Widget'  => 'bbp_topics_widget_options',
			'BBP_Stats_Widget'   => 'bbp_stats_widget_options',
			'BBP_Replies_Widget' => 'bbp_replies_widget_options',
		);

		foreach ( $widgets as $class => $filter ) {
			$callback = function( $options ) use ( $class ) {
				$options['classname'] = strtolower( $class );
				return $options;
			};

			add_filter( $filter, $callback );
			$widget = new $class();
			remove_filter( $filter, $callback );

			$this->assertSame( strtolower( $class ), $widget->widget_options['classname'] );
			$this->assertTrue( $widget->widget_options['customize_selective_refresh'] );
		}
	}

	/**
	 * @covers BBP_Login_Widget::register_widget
	 * @covers BBP_Views_Widget::register_widget
	 * @covers BBP_Search_Widget::register_widget
	 * @covers BBP_Forums_Widget::register_widget
	 * @covers BBP_Topics_Widget::register_widget
	 * @covers BBP_Stats_Widget::register_widget
	 * @covers BBP_Replies_Widget::register_widget
	 * @ticket 3706
	 */
	public function test_widget_registration_helpers() {
		global $wp_widget_factory;

		$classes = array(
			'BBP_Login_Widget',
			'BBP_Views_Widget',
			'BBP_Search_Widget',
			'BBP_Forums_Widget',
			'BBP_Topics_Widget',
			'BBP_Stats_Widget',
			'BBP_Replies_Widget',
		);
		$widgets = $wp_widget_factory->widgets;

		try {
			foreach ( $classes as $class ) {
				unset( $wp_widget_factory->widgets[ $class ] );
				call_user_func( array( $class, 'register_widget' ) );
				$this->assertInstanceOf( $class, $wp_widget_factory->widgets[ $class ] );
			}
		} finally {
			$wp_widget_factory->widgets = $widgets;
		}
	}

	/**
	 * @covers BBP_Login_Widget::parse_settings
	 * @covers BBP_Views_Widget::parse_settings
	 * @covers BBP_Search_Widget::parse_settings
	 * @covers BBP_Forums_Widget::parse_settings
	 * @covers BBP_Topics_Widget::parse_settings
	 * @covers BBP_Stats_Widget::parse_settings
	 * @covers BBP_Replies_Widget::parse_settings
	 * @ticket 3706
	 */
	public function test_widget_settings_defaults_and_overrides() {
		$widgets = array(
			'BBP_Login_Widget' => array(
				'filter'   => 'login_widget_settings',
				'defaults' => array(
					'title'    => '',
					'register' => '',
					'lostpass' => '',
				),
				'override' => array( 'register' => 'https://example.org/register' ),
			),
			'BBP_Views_Widget' => array(
				'filter'   => 'view_widget_settings',
				'defaults' => array( 'title' => '' ),
				'override' => array(),
			),
			'BBP_Search_Widget' => array(
				'filter'   => 'search_widget_settings',
				'defaults' => array( 'title' => 'Search Forums' ),
				'override' => array(),
			),
			'BBP_Forums_Widget' => array(
				'filter'   => 'forum_widget_settings',
				'defaults' => array(
					'title'        => 'Forums',
					'parent_forum' => 0,
				),
				'override' => array( 'parent_forum' => 42 ),
			),
			'BBP_Topics_Widget' => array(
				'filter'   => 'topic_widget_settings',
				'defaults' => array(
					'title'        => 'Recent Topics',
					'max_shown'    => 5,
					'show_date'    => false,
					'show_user'    => false,
					'parent_forum' => 'any',
					'order_by'     => false,
				),
				'override' => array( 'max_shown' => 10 ),
			),
			'BBP_Stats_Widget' => array(
				'filter'   => 'stats_widget_settings',
				'defaults' => array( 'title' => 'Forum Statistics' ),
				'override' => array(),
			),
			'BBP_Replies_Widget' => array(
				'filter'   => 'replies_widget_settings',
				'defaults' => array(
					'title'     => 'Recent Replies',
					'max_shown' => 5,
					'show_date' => false,
					'show_user' => false,
				),
				'override' => array( 'show_user' => true ),
			),
		);

		foreach ( $widgets as $class => $expected ) {
			$widget = new $class();
			$this->assertSame( $expected['defaults'], $widget->parse_settings() );

			$before_called = false;
			$after_called  = false;
			$before_filter = function( $settings ) use ( &$before_called ) {
				$before_called              = true;
				$settings['before_filtered'] = true;
				return $settings;
			};
			$after_filter = function( $settings ) use ( &$after_called ) {
				$after_called              = true;
				$settings['after_filtered'] = true;
				return $settings;
			};
			$settings = array_merge(
				array(
					'title'       => 'Custom title',
					'unknown_key' => 'preserved',
				),
				$expected['override']
			);

			add_filter( "bbp_before_{$expected['filter']}_parse_args", $before_filter );
			add_filter( "bbp_after_{$expected['filter']}_parse_args", $after_filter );
			try {
				$parsed = $widget->parse_settings( $settings );
			} finally {
				remove_filter( "bbp_before_{$expected['filter']}_parse_args", $before_filter );
				remove_filter( "bbp_after_{$expected['filter']}_parse_args", $after_filter );
			}

			$this->assertTrue( $before_called );
			$this->assertTrue( $after_called );
			$this->assertSame(
				array_merge(
					$expected['defaults'],
					$settings,
					array(
						'before_filtered' => true,
						'after_filtered'  => true,
					)
				),
				$parsed
			);
		}
	}

	/**
	 * @covers BBP_Login_Widget::update
	 * @covers BBP_Views_Widget::update
	 * @covers BBP_Search_Widget::update
	 * @covers BBP_Forums_Widget::update
	 * @covers BBP_Topics_Widget::update
	 * @covers BBP_Stats_Widget::update
	 * @covers BBP_Replies_Widget::update
	 * @ticket 3706
	 */
	public function test_widget_updates_sanitize_settings() {
		$old = array( 'preserved' => 'value' );

		$login = new BBP_Login_Widget();
		$this->assertSame(
			array(
				'preserved' => 'value',
				'title'     => 'Login',
				'register'  => 'https://example.org/register',
				'lostpass'  => '',
			),
			$login->update(
				array(
					'title'    => '<b>Login</b>',
					'register' => 'https://example.org/register',
					'lostpass' => 'javascript:alert(1)',
				),
				$old
			)
		);

		foreach ( array( 'BBP_Views_Widget', 'BBP_Search_Widget', 'BBP_Stats_Widget' ) as $class ) {
			$widget = new $class();
			$this->assertSame( array( 'preserved' => 'value', 'title' => 'Title' ), $widget->update( array( 'title' => '<b>Title</b>' ), $old ) );
		}

		$forums = new BBP_Forums_Widget();
		$this->assertSame( '42', $forums->update( array( 'title' => 'Forums', 'parent_forum' => '42' ), $old )['parent_forum'] );
		$this->assertSame( '0', $forums->update( array( 'title' => 'Forums', 'parent_forum' => '0' ), $old )['parent_forum'] );
		$this->assertSame( 'any', $forums->update( array( 'title' => 'Forums', 'parent_forum' => 'any' ), $old )['parent_forum'] );
		$this->assertSame( 'any', $forums->update( array( 'title' => 'Forums', 'parent_forum' => 'invalid' ), $old )['parent_forum'] );

		$topics = new BBP_Topics_Widget();
		$topic_settings = array(
			'title'        => 'Topics',
			'order_by'     => 'popular',
			'parent_forum' => '42',
			'max_shown'    => '12',
		);
		$this->assertSame( '42', $topics->update( $topic_settings, $old )['parent_forum'] );
		$topic_settings['parent_forum'] = '0';
		$this->assertSame( '0', $topics->update( $topic_settings, $old )['parent_forum'] );
		$topic_settings['parent_forum'] = 'any';
		$this->assertSame( 'any', $topics->update( $topic_settings, $old )['parent_forum'] );

		$this->assertSame(
			array(
				'preserved'    => 'value',
				'title'        => 'Topics',
				'order_by'     => 'popular',
				'parent_forum' => 'any',
				'max_shown'    => 12,
				'show_date'    => true,
				'show_user'    => false,
			),
			$topics->update(
				array(
					'title'        => '<b>Topics</b>',
					'order_by'     => '<b>popular</b>',
					'parent_forum' => 'invalid',
					'max_shown'    => '12 posts',
					'show_date'    => '1',
				),
				$old
			)
		);

		$replies = new BBP_Replies_Widget();
		$this->assertSame(
			array(
				'preserved' => 'value',
				'title'     => 'Replies',
				'max_shown' => 7,
				'show_date' => false,
				'show_user' => true,
			),
			$replies->update(
				array(
					'title'     => '<b>Replies</b>',
					'max_shown' => '7',
					'show_user' => '1',
				),
				$old
			)
		);
	}

	/**
	 * @covers BBP_Login_Widget::form
	 * @covers BBP_Views_Widget::form
	 * @covers BBP_Search_Widget::form
	 * @covers BBP_Forums_Widget::form
	 * @covers BBP_Topics_Widget::form
	 * @covers BBP_Stats_Widget::form
	 * @covers BBP_Replies_Widget::form
	 * @ticket 3706
	 */
	public function test_widget_forms_render_configured_controls() {
		$widgets = array(
			'BBP_Login_Widget' => array(
				'instance' => array( 'title' => 'Title "with" <tags>', 'register' => 'https://example.org/register', 'lostpass' => 'https://example.org/lost-password' ),
				'fields'   => array( 'title', 'register', 'lostpass' ),
				'values'   => array( 'title' => 'Title &quot;with&quot; &lt;tags&gt;', 'register' => 'https://example.org/register', 'lostpass' => 'https://example.org/lost-password' ),
			),
			'BBP_Views_Widget' => array(
				'instance' => array( 'title' => 'Views title' ),
				'fields'   => array( 'title' ),
				'values'   => array( 'title' => 'Views title' ),
			),
			'BBP_Search_Widget' => array(
				'instance' => array( 'title' => 'Search title' ),
				'fields'   => array( 'title' ),
				'values'   => array( 'title' => 'Search title' ),
			),
			'BBP_Forums_Widget' => array(
				'instance' => array( 'title' => 'Forums title', 'parent_forum' => 42 ),
				'fields'   => array( 'title', 'parent_forum' ),
				'values'   => array( 'title' => 'Forums title', 'parent_forum' => '42' ),
			),
			'BBP_Topics_Widget' => array(
				'instance' => array( 'title' => 'Topics title', 'max_shown' => 8, 'show_date' => true, 'show_user' => true, 'parent_forum' => 42, 'order_by' => 'popular' ),
				'fields'   => array( 'title', 'max_shown', 'show_date', 'show_user', 'parent_forum', 'order_by' ),
				'values'   => array( 'title' => 'Topics title', 'max_shown' => '8', 'parent_forum' => '42' ),
				'checked'  => array( 'show_date', 'show_user' ),
				'selected' => 'popular',
			),
			'BBP_Stats_Widget' => array(
				'instance' => array( 'title' => 'Stats title' ),
				'fields'   => array( 'title' ),
				'values'   => array( 'title' => 'Stats title' ),
			),
			'BBP_Replies_Widget' => array(
				'instance' => array( 'title' => 'Replies title', 'max_shown' => 9, 'show_date' => true, 'show_user' => true ),
				'fields'   => array( 'title', 'max_shown', 'show_date', 'show_user' ),
				'values'   => array( 'title' => 'Replies title', 'max_shown' => '9' ),
				'checked'  => array( 'show_date', 'show_user' ),
			),
		);

		foreach ( $widgets as $class => $expected ) {
			$widget = new $class();
			ob_start();
			$widget->form( $expected['instance'] );
			$html = ob_get_clean();

			foreach ( $expected['fields'] as $field ) {
				$this->assertStringContainsString( 'id="' . $widget->get_field_id( $field ) . '"', $html );
				$this->assertStringContainsString( 'name="' . $widget->get_field_name( $field ) . '"', $html );
			}

			foreach ( $expected['values'] as $field => $value ) {
				$this->assertMatchesRegularExpression(
					'/id="' . preg_quote( $widget->get_field_id( $field ), '/' ) . '"[^>]*value="' . preg_quote( $value, '/' ) . '"/',
					$html
				);
			}

			foreach ( $expected['checked'] ?? array() as $field ) {
				$this->assertMatchesRegularExpression(
					'/id="' . preg_quote( $widget->get_field_id( $field ), '/' ) . '"[^>]*checked=[\'\"]checked[\'\"]/',
					$html
				);
			}

			if ( isset( $expected['selected'] ) ) {
				$this->assertMatchesRegularExpression(
					'/<option\s+selected=[\'\"]selected[\'\"]\s+value="' . preg_quote( $expected['selected'], '/' ) . '">/',
					$html
				);
				$this->assertSame( 1, preg_match_all( '/\sselected=[\'\"]selected[\'\"]/', $html ) );
			}
		}

		foreach ( array( 'BBP_Topics_Widget', 'BBP_Replies_Widget' ) as $class ) {
			$widget = new $class();
			ob_start();
			$widget->form( array() );
			$html = ob_get_clean();

			foreach ( array( 'show_date', 'show_user' ) as $field ) {
				$this->assertDoesNotMatchRegularExpression(
					'/id="' . preg_quote( $widget->get_field_id( $field ), '/' ) . '"[^>]*checked=[\'\"]checked[\'\"]/',
					$html
				);
			}

			if ( 'BBP_Topics_Widget' === $class ) {
				$this->assertDoesNotMatchRegularExpression( '/\sselected=[\'\"]selected[\'\"]/', $html );
			}
		}
	}

	/**
	 * @covers BBP_Topics_Widget::widget
	 */
	public function test_topics_widget_uses_default_post_parent_for_any_forum() {
		$forum_ids = $this->factory->forum->create_many( 2 );
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_ids[0],
				'post_title'  => 'First forum topic',
				'topic_meta'  => array( 'forum_id' => $forum_ids[0] ),
			)
		);
		$this->factory->topic->create(
			array(
				'post_parent' => $forum_ids[1],
				'post_title'  => 'Second forum topic',
				'topic_meta'  => array( 'forum_id' => $forum_ids[1] ),
			)
		);

		$post_parent  = null;
		$capture_query = function( $query ) use ( &$post_parent ) {
			if ( bbp_get_topic_post_type() === $query->get( 'post_type' ) ) {
				$post_parent = $query->get( 'post_parent' );
			}
		};
		add_action( 'pre_get_posts', $capture_query, 999 );

		$widget = new BBP_Topics_Widget();
		ob_start();
		$widget->widget(
			array(
				'before_widget' => '',
				'after_widget'  => '',
				'before_title'  => '',
				'after_title'   => '',
			),
			array(
				'max_shown'    => 10,
				'parent_forum' => 'any',
			)
		);
		$html = ob_get_clean();

		remove_action( 'pre_get_posts', $capture_query, 999 );

		$this->assertSame( '', $post_parent );
		$this->assertStringContainsString( 'First forum topic', $html );
		$this->assertStringContainsString( 'Second forum topic', $html );
	}
}
