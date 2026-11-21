<?php

/**
 * Tests for common widgets.
 *
 * @group common
 * @group widgets
 */
class BBP_Tests_Common_Widgets extends BBP_UnitTestCase {

	/**
	 * @covers BBP_Topics_Widget::widget
	 */
	public function test_topics_widget_primes_activity_posts_when_showing_dates() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array(
			'post_parent' => $forum_id,
			'topic_meta'  => array( 'forum_id' => $forum_id ),
		) );
		$this->factory->reply->create( array(
			'post_parent' => $topic_id,
			'reply_meta'  => array( 'forum_id' => $forum_id, 'topic_id' => $topic_id ),
		) );

		$widget = new BBP_Topics_Widget();
		$args   = array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
		);
		$meta_cache = null;
		$capture_query = function( $query ) use ( &$meta_cache ) {
			if ( bbp_get_topic_post_type() === $query->get( 'post_type' ) ) {
				$meta_cache = $query->get( 'update_post_meta_cache' );
			}
		};
		add_action( 'pre_get_posts', $capture_query, 999 );

		try {
			ob_start();
			$widget->widget( $args, array(
				'parent_forum' => $forum_id,
				'max_shown'    => 1,
				'show_date'    => true,
			) );
			$dated_html = ob_get_clean();
			$this->assertTrue( $meta_cache );
			$this->assertStringContainsString( '<div>', $dated_html );

			$meta_cache = null;
			ob_start();
			$widget->widget( $args, array(
				'parent_forum' => $forum_id,
				'max_shown'    => 1,
				'show_date'    => false,
			) );
			$undated_html = ob_get_clean();
			$this->assertFalse( $meta_cache );
			$this->assertStringNotContainsString( '<div>', $undated_html );
		} finally {
			remove_action( 'pre_get_posts', $capture_query, 999 );
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
