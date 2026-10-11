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
