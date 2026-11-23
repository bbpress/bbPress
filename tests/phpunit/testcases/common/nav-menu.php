<?php

/**
 * Tests for forum navigation menu classes.
 *
 * @group common
 * @ticket 2204
 */
class BBP_Tests_Common_Nav_Menu extends BBP_UnitTestCase {

	private function get_menu_items( $menu_id ) {
		$items   = array();
		$capture = function( $menu_items ) use ( &$items ) {
			$items = $menu_items;
			return $menu_items;
		};

		add_filter( 'wp_nav_menu_objects', $capture, PHP_INT_MAX );

		try {
			wp_nav_menu( array( 'menu' => $menu_id, 'echo' => false ) );
		} finally {
			remove_filter( 'wp_nav_menu_objects', $capture, PHP_INT_MAX );
		}

		return array_column( $items, null, 'db_id' );
	}

	public function test_forum_archive_page_marks_menu_item_and_ancestors() {
		$this->set_permalink_structure( '/%postname%/' );

		$menu_id    = wp_create_nav_menu( 'Forum archive page' );
		$parent_id  = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$archive_id = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => bbp_get_root_slug() ) );
		$posts_id   = $this->factory->post->create( array( 'post_type' => 'page' ) );
		update_option( 'page_for_posts', $posts_id );

		$parent_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $parent_id,
			'menu-item-status'    => 'publish'
		) );
		$archive_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $archive_id,
			'menu-item-parent-id' => $parent_item,
			'menu-item-status'    => 'publish'
		) );
		$posts_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $posts_id,
			'menu-item-status'    => 'publish'
		) );

		$archive_urls = array(
			bbp_get_forums_url(),
			add_query_arg( 'post_type', bbp_get_forum_post_type(), home_url( '/' ) )
		);

		foreach ( $archive_urls as $index => $url ) {
			WP_UnitTestCase::go_to( $url );
			$items = $this->get_menu_items( $menu_id );

			if ( 1 === $index ) {
				$this->assertTrue( bbp_is_forum_archive() );
				$this->assertFalse( is_page() );
			}

			$this->assertContains( 'current-menu-item', $items[ $archive_item ]->classes );
			$this->assertTrue( $items[ $archive_item ]->current );
			$this->assertContains( 'current-menu-parent', $items[ $parent_item ]->classes );
			$this->assertContains( 'current_page_parent', $items[ $parent_item ]->classes );
			$this->assertNotContains( 'current_page_parent', $items[ $posts_item ]->classes );
		}
	}

	public function test_custom_archive_and_root_page_links_with_nonstandard_permalinks() {
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$page_id  = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => bbp_get_root_slug() ) );

		foreach ( array( '', '/index.php/%postname%/' ) as $index => $structure ) {
			$this->set_permalink_structure( $structure );
			$menu_id     = wp_create_nav_menu( 'Forum root permalink ' . $index );
			$archive_url = get_post_type_archive_link( bbp_get_forum_post_type() );
			$page_url    = get_permalink( $page_id );

			$this->assertNotSame( bbp_get_forums_url(), $archive_url );
			if ( '' !== $structure ) {
				$this->assertNotSame( bbp_get_forums_url(), $page_url );
			}

			$archive_item = wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-type'   => 'custom',
				'menu-item-title'  => 'Forums archive',
				'menu-item-url'    => $archive_url,
				'menu-item-status' => 'publish'
			) );
			if ( '' !== $structure ) {
				$page_item = wp_update_nav_menu_item( $menu_id, 0, array(
					'menu-item-type'   => 'custom',
					'menu-item-title'  => 'Forums page',
					'menu-item-url'    => $page_url,
					'menu-item-status' => 'publish'
				) );
			}

			WP_UnitTestCase::go_to( get_permalink( $topic_id ) );
			$this->assertTrue( bbp_is_single_topic() );
			$items = $this->get_menu_items( $menu_id );

			$this->assertContains( 'current-menu-parent', $items[ $archive_item ]->classes );
			if ( '' !== $structure ) {
				$this->assertContains( 'current-menu-parent', $items[ $page_item ]->classes );
			}
		}
	}

	public function test_empty_root_slug_does_not_mark_home_link_as_parent() {
		$this->set_permalink_structure( '/%postname%/' );

		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$menu_id  = wp_create_nav_menu( 'Home link without a forum root slug' );
		$home_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'   => 'custom',
			'menu-item-title'  => 'Home',
			'menu-item-url'    => home_url( '/' ),
			'menu-item-status' => 'publish'
		) );

		WP_UnitTestCase::go_to( get_permalink( $topic_id ) );
		add_filter( 'bbp_get_root_slug', '__return_empty_string' );

		try {
			$items = $this->get_menu_items( $menu_id );
			$this->assertNotContains( 'current-menu-parent', $items[ $home_item ]->classes );
		} finally {
			remove_filter( 'bbp_get_root_slug', '__return_empty_string' );
		}
	}

	public function test_posts_page_keeps_parent_class_when_it_is_the_menu_parent() {
		$this->set_permalink_structure( '/%postname%/' );

		$forum_id = $this->factory->forum->create();
		$posts_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$menu_id  = wp_create_nav_menu( 'Forum beneath posts page' );
		update_option( 'page_for_posts', $posts_id );

		$posts_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $posts_id,
			'menu-item-status'    => 'publish'
		) );
		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => bbp_get_forum_post_type(),
			'menu-item-object-id' => $forum_id,
			'menu-item-parent-id' => $posts_item,
			'menu-item-status'    => 'publish'
		) );

		WP_UnitTestCase::go_to( get_permalink( $forum_id ) );
		$this->assertTrue( bbp_is_single_forum() );
		$items = $this->get_menu_items( $menu_id );

		$this->assertContains( 'current-menu-parent', $items[ $posts_item ]->classes );
		$this->assertContains( 'current_page_parent', $items[ $posts_item ]->classes );
		$this->assertTrue( $items[ $posts_item ]->current_item_parent );
	}

	public function test_forum_topic_marks_exact_root_link_and_ancestors() {
		$this->set_permalink_structure( '/%postname%/' );

		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$menu_id  = wp_create_nav_menu( 'Forum topic' );
		$parent_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$posts_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		update_option( 'page_for_posts', $posts_id );
		$parent_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $parent_id,
			'menu-item-status'    => 'publish'
		) );
		$root_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'custom',
			'menu-item-title'     => 'Forums',
			'menu-item-url'       => bbp_get_forums_url(),
			'menu-item-parent-id' => $parent_item,
			'menu-item-status'    => 'publish'
		) );
		$other_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'   => 'custom',
			'menu-item-title'  => 'Other forums',
			'menu-item-url'    => bbp_get_forums_url( '/other/' ),
			'menu-item-status' => 'publish'
		) );
		$posts_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $posts_id,
			'menu-item-status'    => 'publish'
		) );

		WP_UnitTestCase::go_to( get_permalink( $topic_id ) );
		$items = $this->get_menu_items( $menu_id );

		$this->assertContains( 'current-menu-parent', $items[ $root_item ]->classes );
		$this->assertContains( 'current-menu-ancestor', $items[ $root_item ]->classes );
		$this->assertTrue( $items[ $root_item ]->current_item_parent );
		$this->assertContains( 'current-menu-ancestor', $items[ $parent_item ]->classes );
		$this->assertNotContains( 'current-menu-parent', $items[ $other_item ]->classes );
		$this->assertNotContains( 'current_page_parent', $items[ $posts_item ]->classes );

		$post_id = $this->factory->post->create();
		WP_UnitTestCase::go_to( get_permalink( $post_id ) );
		$items = $this->get_menu_items( $menu_id );
		$this->assertContains( 'current_page_parent', $items[ $posts_item ]->classes );
	}

	public function test_forum_and_reply_mark_root_page_and_archive_item() {
		$this->set_permalink_structure( '/%postname%/' );

		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		$page_id  = $this->factory->post->create( array( 'post_type' => 'page', 'post_name' => bbp_get_root_slug() ) );
		$menu_id  = wp_create_nav_menu( 'Forum descendants' );
		$page_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-status'    => 'publish'
		) );
		$archive_item = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'   => 'post_type_archive',
			'menu-item-object' => bbp_get_forum_post_type(),
			'menu-item-status' => 'publish'
		) );

		foreach ( array( 'forum' => get_permalink( $forum_id ), 'reply' => get_permalink( $reply_id ) ) as $type => $url ) {
			WP_UnitTestCase::go_to( $url );
			$items = $this->get_menu_items( $menu_id );

			$this->assertTrue( 'forum' === $type ? bbp_is_single_forum() : bbp_is_single_reply() );
			$this->assertContains( 'current-menu-parent', $items[ $page_item ]->classes );
			$this->assertContains( 'current_page_parent', $items[ $page_item ]->classes );
			$this->assertContains( 'current-menu-parent', $items[ $archive_item ]->classes );
		}
	}
}
