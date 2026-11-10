<?php

/**
 * Tests for the topic tag template functions.
 *
 * @group topics
 * @group topic_tags
 * @group template
 * @group topic
 * @group topic_tag
 */
class BBP_Tests_Topic_Tags_Template_Topic_Tag extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_topic_tag_list
	 * @covers ::bbp_get_topic_tag_list
	 */
	public function test_bbp_get_topic_tag_list() {
		$topic_id = $this->factory->topic->create();
		$args = array( 'before' => '<p>', 'sep' => ' | ', 'after' => '</p>', 'none' => 'No tags' );
		$this->assertSame( 'No tags', bbp_get_topic_tag_list( $topic_id, $args ) );

		wp_set_object_terms( $topic_id, array( 'alpha', 'beta' ), bbp_get_topic_tag_tax_id() );
		$html = bbp_get_topic_tag_list( $topic_id, $args );
		$this->assertStringStartsWith( '<p>', $html );
		$this->assertStringContainsString( '>alpha</a> | <a ', $html );
		$this->assertStringEndsWith( '</p>', $html );
		$this->expectOutputString( $html );
		bbp_topic_tag_list( $topic_id, $args );

		add_filter( 'bbp_allow_topic_tags', '__return_false' );
		try {
			$this->assertSame( '', bbp_get_topic_tag_list( $topic_id, $args ) );
		} finally {
			remove_filter( 'bbp_allow_topic_tags', '__return_false' );
		}
	}

	/**
	 * @covers ::bbp_topic_tag_tax_id
	 * @covers ::bbp_get_topic_tag_tax_id
	 */
	public function test_bbp_get_topic_tag_tax_id() {
		$this->assertSame( bbpress()->topic_tag_tax_id, bbp_get_topic_tag_tax_id() );
		$this->expectOutputString( bbpress()->topic_tag_tax_id );
		bbp_topic_tag_tax_id();
	}

	/**
	 * @covers ::bbp_get_topic_tag_tax_labels
	 */
	public function test_bbp_get_topic_tag_tax_labels() {
		$labels = bbp_get_topic_tag_tax_labels();
		$this->assertSame( 'Topic Tags', $labels['name'] );
		$this->assertSame( 'Topic Tag', $labels['singular_name'] );
		$this->assertArrayHasKey( 'not_found', $labels );
	}

	/**
	 * @covers ::bbp_get_topic_tag_tax_rewrite
	 */
	public function test_bbp_get_topic_tag_tax_rewrite() {
		$this->assertSame( array( 'slug' => bbp_get_topic_tag_tax_slug(), 'with_front' => false ), bbp_get_topic_tag_tax_rewrite() );
	}

	/**
	 * @covers ::bbp_topic_tag_id
	 * @covers ::bbp_get_topic_tag_id
	 */
	public function test_bbp_get_topic_tag_id() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$this->assertSame( $term['term_id'], bbp_get_topic_tag_id( 'alpha' ) );
		$this->assertSame( 0, bbp_get_topic_tag_id( 'missing-tag' ) );
		$this->expectOutputString( (string) $term['term_id'] );
		bbp_topic_tag_id( 'alpha' );
	}

	/**
	 * @covers ::bbp_topic_tag_name
	 * @covers ::bbp_get_topic_tag_name
	 */
	public function test_bbp_get_topic_tag_name() {
		$term = wp_insert_term(
			'Rock & Roll\'s &lt;tag&gt;',
			bbp_get_topic_tag_tax_id(),
			array( 'slug' => 'rock-and-roll' )
		);

		$this->assertFalse( is_wp_error( $term ) );
		$topic_tag_name = bbp_get_topic_tag_name( 'rock-and-roll' );

		$this->assertSame( 'Rock &amp; Roll&#039;s &lt;tag&gt;', $topic_tag_name );
		$this->assertSame( 'Rock &amp; Roll&#039;s &lt;tag&gt;', esc_attr( $topic_tag_name ) );

		$filter = static function() {
			return '<img src=x onerror=alert(document.domain)>';
		};

		add_filter( 'bbp_get_topic_tag_name', $filter, 9 );
		$topic_tag_name = bbp_get_topic_tag_name( 'rock-and-roll' );
		remove_filter( 'bbp_get_topic_tag_name', $filter, 9 );

		$this->assertSame( '&lt;img src=x onerror=alert(document.domain)&gt;', $topic_tag_name );
	}

	/**
	 * @covers ::bbp_topic_tag_slug
	 * @covers ::bbp_get_topic_tag_slug
	 */
	public function test_bbp_get_topic_tag_slug() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$this->assertSame( 'alpha', bbp_get_topic_tag_slug( 'alpha' ) );
		$this->assertSame( '', bbp_get_topic_tag_slug( 'missing-tag' ) );
		$this->expectOutputString( 'alpha' );
		bbp_topic_tag_slug( 'alpha' );
	}

	/**
	 * @covers ::bbp_topic_tag_link
	 * @covers ::bbp_get_topic_tag_link
	 */
	public function test_bbp_get_topic_tag_link() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$url = get_term_link( $term['term_id'], bbp_get_topic_tag_tax_id() );
		$this->assertSame( $url, bbp_get_topic_tag_link( 'alpha' ) );
		$this->assertSame( '', bbp_get_topic_tag_link( 'missing-tag' ) );
		$this->expectOutputString( esc_url( $url ) );
		bbp_topic_tag_link( 'alpha' );
	}

	/**
	 * @ticket BBP2989
	 *
	 * @covers ::bbp_topic_tag_subscription_link
	 * @covers ::bbp_get_topic_tag_subscription_link
	 */
	public function test_bbp_get_topic_tag_subscription_link() {
		$user_id = $this->factory->user->create( array( 'role' => bbp_get_participant_role() ) );
		$term    = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha' ) );
		$args    = array(
			'object_id'   => $term['term_id'],
			'redirect_to' => bbp_get_topic_tag_link( 'alpha' )
		);

		$this->set_current_user( $user_id );

		$link = bbp_get_topic_tag_subscription_link( $args );
		$this->assertStringContainsString( 'Subscribe to this topic tag', $link );
		$this->assertStringContainsString( 'id="subscribe-term-' . $term['term_id'] . '"', $link );
		$this->assertStringContainsString( 'data-bbp-object-type="term"', $link );

		bbp_add_user_subscription( $user_id, $term['term_id'], 'term' );
		$link = bbp_get_topic_tag_subscription_link( $args );
		$this->assertStringContainsString( 'Unsubscribe from this topic tag', $link );

		$this->expectOutputString( $link );
		bbp_topic_tag_subscription_link( $args );
	}

	/**
	 * @covers ::bbp_topic_tag_edit_link
	 * @covers ::bbp_get_topic_tag_edit_link
	 */
	public function test_bbp_get_topic_tag_edit_link() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$link = bbp_get_topic_tag_link( 'alpha' );
		$this->assertSame( '', bbp_get_topic_tag_edit_link( 'missing-tag' ) );

		add_filter( 'bbp_pretty_urls', '__return_false' );
		try {
			$edit_link = add_query_arg( array( bbp_get_edit_rewrite_id() => '1' ), $link );
			$this->assertSame( $edit_link, bbp_get_topic_tag_edit_link( 'alpha' ) );
			$this->expectOutputString( esc_url( $edit_link ) );
			bbp_topic_tag_edit_link( 'alpha' );
			remove_filter( 'bbp_pretty_urls', '__return_false' );
			add_filter( 'bbp_pretty_urls', '__return_true' );
			$this->assertSame( user_trailingslashit( trailingslashit( $link ) . bbp_get_edit_slug() ), bbp_get_topic_tag_edit_link( 'alpha' ) );
		} finally {
			remove_filter( 'bbp_pretty_urls', '__return_false' );
			remove_filter( 'bbp_pretty_urls', '__return_true' );
		}
	}

	/**
	 * @covers ::bbp_topic_tag_description
	 * @covers ::bbp_get_topic_tag_description
	 */
	public function test_bbp_get_topic_tag_description() {
		$term = wp_insert_term( 'Alpha', bbp_get_topic_tag_tax_id(), array( 'slug' => 'alpha', 'description' => 'Alpha description' ) );
		$this->assertFalse( is_wp_error( $term ) );
		$args = array( 'tag' => 'alpha', 'before' => '<p>', 'after' => '</p>' );
		$seen = array();
		$filter = function ( $description, $parsed, $original, $tag, $term ) use ( &$seen ) {
			$seen[] = array( $tag, $term->term_id );
			return $description;
		};
		add_filter( 'bbp_get_topic_tag_description', $filter, 10, 5 );

		try {
			$this->assertSame( '<p>Alpha description</p>', bbp_get_topic_tag_description( $args ) );
			$this->assertSame( array( array( 'alpha', $term['term_id'] ) ), $seen );
			$this->expectOutputString( '<p>Alpha description</p>' );
			bbp_topic_tag_description( $args );
		} finally {
			remove_filter( 'bbp_get_topic_tag_description', $filter, 10 );
		}
	}
}
