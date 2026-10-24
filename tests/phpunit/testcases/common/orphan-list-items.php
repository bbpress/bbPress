<?php

/**
 * @group common
 * @group formatting
 * @group orphan-list-items
 */
class BBP_Tests_Common_Orphan_List_Items extends BBP_UnitTestCase {

	/**
	 * @dataProvider list_content
	 * @covers ::bbp_wrap_orphan_list_items
	 */
	public function test_list_markup( $content, $expected ) {
		if ( ! class_exists( 'WP_HTML_Processor' ) || ! is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) ) ) {
			$expected = $content;
		}
		$this->assertSame( $expected, bbp_wrap_orphan_list_items( $content ) );
		$this->assertSame( $expected, bbp_wrap_orphan_list_items( $expected ) );
	}

	public static function list_content() {
		return array(
			'plain text' => array( 'No list.', 'No list.' ),
			'one item' => array( '<li>One</li>', '<ul><li>One</li></ul>' ),
			'adjacent items' => array( "<li>One</li>\n<li>Two</li>", "<ul><li>One</li>\n<li>Two</li></ul>" ),
			'non-ASCII decimal reference' => array( '<li>&#233;</li>', "<ul><li>\u{00E9}</li></ul>" ),
			'decimal reference boundary' => array( '<li>&#1000;</li>', "<ul><li>\u{03E8}</li></ul>" ),
			'non-linefeed hexadecimal reference' => array( '<li>&#xAA;</li>', "<ul><li>\u{00AA}</li></ul>" ),
			'attributes and case' => array( "<LI class='item'>One</LI>", '<ul><li class="item">One</li></ul>' ),
			'valid unordered' => array( '<ul><li>One</li></ul>', '<ul><li>One</li></ul>' ),
			'valid ordered' => array( '<ol start="2"><li>Two</li></ol>', '<ol start="2"><li>Two</li></ol>' ),
			'valid menu' => array( '<menu><li>One</li></menu>', '<menu><li>One</li></menu>' ),
			'valid directory' => array( '<dir><li>One</li></dir>', '<dir><li>One</li></dir>' ),
			'directory before orphan' => array( '<dir><li>One</li></dir><li>Two</li>', '<dir><li>One</li></dir><ul><li>Two</li></ul>' ),
			'directory inside orphan' => array( '<li>One<dir><li>Two</li></dir></li>', '<ul><li>One<dir><li>Two</li></dir></li></ul>' ),
			'nested list' => array( '<ul><li>One<ol><li>Two</li></ol></li><li>Three</li></ul>', '<ul><li>One<ol><li>Two</li></ol></li><li>Three</li></ul>' ),
			'orphan before list' => array( '<li>One</li><ol><li>Two</li></ol>', '<ul><li>One</li></ul><ol><li>Two</li></ol>' ),
			'orphan after list' => array( '<ol><li>One</li></ol><li>Two</li>', '<ol><li>One</li></ol><ul><li>Two</li></ul>' ),
			'nested list in orphan' => array( '<li>One<ol><li>Two</li></ol></li>', '<ul><li>One<ol><li>Two</li></ol></li></ul>' ),
			'container boundary' => array( '<div><li>One</li></div><p>Text</p>', '<div><ul><li>One</li></ul></div><p>Text</p>' ),
			'text boundary' => array( '<li>One</li>Text<li>Two</li>', '<ul><li>One</li></ul>Text<ul><li>Two</li></ul>' ),
			'comments' => array( '<!-- <li>Example</li> --><li>One</li>', '<!-- <li>Example</li> --><ul><li>One</li></ul>' ),
			'comment between items' => array( '<li>One</li><!-- next --><li>Two</li>', '<ul><li>One</li><!-- next --><li>Two</li></ul>' ),
			'cdata' => array( '<![CDATA[<li>Example</li>]]><li>One</li>', '<!--[CDATA[<li-->Example]]&gt;<ul><li>One</li></ul>' ),
			'unchanged case and quotes' => array( "<UL><LI class='item'>One</LI></UL>", "<UL><LI class='item'>One</LI></UL>" ),
			'incomplete input' => array( '<li>One</li><!--', '<li>One</li><!--' ),
			'unsupported parsing' => array( '<li>One</li><a><div><a></div></a>', '<li>One</li><a><div><a></div></a>' ),
			'quoted attribute' => array( '<span title="><li>Example</li>">Text</span>', '<span title="><li>Example</li>">Text</span>' ),
			'single quoted attribute' => array( "<span title='><li>Example</li>'>Text</span>", "<span title='><li>Example</li>'>Text</span>" ),
			'code' => array( '<code><li>Example</li></code><li>One</li>', '<code><li>Example</li></code><ul><li>One</li></ul>' ),
			'pre' => array( '<pre><code><li>Example</li></code></pre>', '<pre><code><li>Example</li></code></pre>' ),
			'pre newline before orphan' => array( "<pre>\n\nx</pre><li>One</li>", "<pre>\n\nx</pre><ul><li>One</li></ul>" ),
			'pre newline inside orphan' => array( "<li>One<pre>\n\nx</pre></li>", "<ul><li>One<pre>\n\nx</pre></li></ul>" ),
			'listing newline before orphan' => array( "<listing>\n\nx</listing><li>One</li>", "<listing>\n\nx</listing><ul><li>One</li></ul>" ),
			'listing example before orphan' => array( '<listing><li>Example</li></listing><li>One</li>', '<listing><li>Example</li></listing><ul><li>One</li></ul>' ),
			'listing text before orphan' => array( '<listing>x</listing><li>One</li>', '<listing>x</listing><ul><li>One</li></ul>' ),
			'textarea newline before orphan' => array( "<textarea>\n\nx</textarea><li>One</li>", "<textarea>\n\nx</textarea><ul><li>One</li></ul>" ),
			'textarea quoted angle bracket' => array( "<textarea title='>'>\n\nx</textarea><li>One</li>", "<textarea title=\"&gt;\">\n\nx</textarea><ul><li>One</li></ul>" ),
			'invalid textarea text' => array( "<textarea>\n\ncaf\xE9</textarea><li>One</li>", "<textarea>\n\ncaf\xE9</textarea><li>One</li>" ),
			'nested orphan limitation' => array( '<li>One<blockquote><li>Two</li></blockquote></li>', '<ul><li>One<blockquote><li>Two</li></blockquote></li></ul>' ),
			'textarea' => array( '<textarea><li>Example</li></textarea>', '<textarea><li>Example</li></textarea>' ),
			'script' => array( '<script>"<li>Example</li>"</script>', '<script>"<li>Example</li>"</script>' ),
			'escaped code' => array( '<code>&lt;li&gt;Example&lt;/li&gt;</code>', '<code>&lt;li&gt;Example&lt;/li&gt;</code>' ),
			'block content' => array( '<!-- wp:html --><li>Example</li><!-- /wp:html -->', '<!-- wp:html --><li>Example</li><!-- /wp:html -->' ),
		);
	}

	public function test_topic_and_reply_getters_repair_display_without_changing_storage() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id, 'post_content' => '<li>One</li><li>Two</li>' ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id, 'post_content' => '<li>One</li><li>Two</li>' ) );

		foreach ( array( 'topic' => $topic_id, 'reply' => $reply_id ) as $type => $post_id ) {
			$getter = 'bbp_get_' . $type . '_content';
			if ( $modern ) {
				$this->assertStringContainsString( '<ul>', $getter( $post_id ) );
				$this->assertStringContainsString( '</ul>', $getter( $post_id ) );
			} else {
				$this->assertStringNotContainsString( '<ul>', $getter( $post_id ) );
			}
			$this->assertSame( '<li>One</li><li>Two</li>', get_post_field( 'post_content', $post_id, 'raw' ) );
		}
	}

	public function test_rendered_block_content_is_not_repaired() {
		$post_id = $this->factory->post->create( array( 'post_content' => '<!-- wp:html --><li>Example</li><!-- /wp:html -->' ) );
		$this->assertSame( '<li>Example</li>', bbp_wrap_orphan_list_items( '<li>Example</li>', $post_id ) );
	}

	public function test_content_filters_do_not_add_a_line_break_to_protected_content() {
		foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $filter ) {
			foreach ( array( 'listing', 'textarea', 'pre' ) as $tag ) {
				$content = '<' . $tag . '>x</' . $tag . '>';
				$this->assertStringContainsString( $content, apply_filters( $filter, $content . '<li>One</li>' ) );
			}
		}
	}

	public function test_non_utf8_content_is_not_repaired() {
		$charset = get_option( 'blog_charset' );
		try {
			foreach ( array( 'ISO-8859-1', 'Windows-1252', 'SJIS' ) as $encoding ) {
				update_option( 'blog_charset', $encoding );
				foreach ( array( "<li>caf\xE9</li>", '<li>It&#8217;s</li>', "<p>caf\xE9</p><li>One</li>" ) as $content ) {
					$this->assertSame( $content, bbp_wrap_orphan_list_items( $content ) );
				}
			}
		} finally {
			update_option( 'blog_charset', $charset );
		}
	}

	public function test_protected_element_text_survives_repair() {
		if ( ! class_exists( 'WP_HTML_Processor' ) || ! is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) ) ) {
			$this->assertSame( '<li>One</li>', bbp_wrap_orphan_list_items( '<li>One</li>' ) );
			return;
		}

		foreach ( array( 'pre', 'listing', 'textarea' ) as $tag ) {
			foreach ( array( '', 'x', "\nx", "\n\nx", "\n\n\nx", "\r\n\r\nx", "\n\n&amp;lt;&amp;#10;&lt;/textarea&gt;" ) as $text ) {
				$content  = '<' . $tag . '>' . $text . '</' . $tag . '><li>One</li>';
				$repaired = bbp_wrap_orphan_list_items( $content );
				$this->assertStringContainsString( '<ul><li>One</li></ul>', $repaired );
				$this->assertSame( $this->get_protected_element_text( $content, $tag ), $this->get_protected_element_text( $repaired, $tag ), $tag . ': ' . $text );
				$this->assertSame( $repaired, bbp_wrap_orphan_list_items( $repaired ) );
			}
		}
	}

	private function get_protected_element_text( $content, $tag ) {
		$processor = WP_HTML_Processor::create_fragment( $content );
		$this->assertTrue( $processor->next_tag( strtoupper( $tag ) ) );
		if ( 'textarea' === $tag ) {
			return $processor->get_modifiable_text();
		}
		$text = '';
		while ( $processor->next_token() ) {
			if ( $processor->is_tag_closer() && ( strtoupper( $tag ) === $processor->get_tag() ) ) {
				break;
			}
			if ( '#text' === $processor->get_token_type() ) {
				$text .= $processor->get_modifiable_text();
			}
		}
		return $text;
	}

	public function test_opaque_payloads_are_preserved_or_repair_is_bypassed() {
		foreach ( array( 'iframe', 'noembed', 'noframes', 'script', 'style', 'textarea', 'title', 'xmp' ) as $tag ) {
			$content = '<' . $tag . '>Example &amp;lt; &lt;b&gt;</' . $tag . '><li>One</li>';
			$output  = bbp_wrap_orphan_list_items( $content );
			if ( $output === $content ) {
				$this->assertSame( $content, $output );
				continue;
			}
			$before = new WP_HTML_Tag_Processor( $content );
			$after  = new WP_HTML_Tag_Processor( $output );
			$this->assertTrue( $before->next_tag() );
			$this->assertTrue( $after->next_tag() );
			$this->assertSame( $before->get_modifiable_text(), $after->get_modifiable_text(), $tag );
			$this->assertStringContainsString( '<ul><li>One</li></ul>', $output );
		}
	}

	public function test_ascii_references_do_not_change_later_content_formatting() {
		foreach ( array( '&#10;', '&#010;', '&#00010;', '&#10', '&#xA;', '&#Xa;', '&#x000a;', '&#xA', '&NewLine;', '&#13;', '&#9;', '&#58;', '&#64;' ) as $reference ) {
			$fixtures = array(
				'<li>One' . $reference . 'Two</li>',
				'<p>a' . $reference . $reference . 'b</p><li>x</li>',
				'<listing>' . $reference . 'x</listing><li>One</li>',
				'<textarea>a' . $reference . 'z</textarea><li>One</li>',
			);
			foreach ( $fixtures as $content ) {
				$this->assertSame( $content, bbp_wrap_orphan_list_items( $content ) );
				// convert_chars() escapes incomplete references before this filter.
				if ( ';' !== substr( $reference, -1 ) ) {
					continue;
				}
				foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $filter ) {
					remove_filter( $filter, 'bbp_wrap_orphan_list_items', 35 );
					try {
						$before = apply_filters( $filter, $content );
					} finally {
						add_filter( $filter, 'bbp_wrap_orphan_list_items', 35, 2 );
					}
					$this->assertSame( $before, apply_filters( $filter, $content ), $reference );
				}
			}
		}
	}

	public function test_encoded_punctuation_does_not_create_links() {
		foreach ( array( 'http&#58;//example.com', 'name&#64;example.com', 'www&#46;example&#46;com', 'http&colon;//example.com', 'name&commat;example.com', 'www&period;example&period;com' ) as $text ) {
			$content = '<li>' . $text . '</li>';
			$this->assertSame( $content, bbp_wrap_orphan_list_items( $content ) );
			foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $filter ) {
				$this->assertStringNotContainsString( '<a ', apply_filters( $filter, $content ) );
			}
		}
	}

	public function test_getter_pipeline_preserves_nested_lists_code_and_blocks() {
		$fixtures = array(
			'<ul><li>One<ol><li>Two</li></ol></li><li>Three</li></ul>',
			'<pre><code>&lt;li&gt;Example&lt;/li&gt;</code></pre>',
			'<!-- wp:html --><li>Example</li><!-- /wp:html -->',
		);
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		foreach ( array( 'topic' => $topic_id, 'reply' => $reply_id ) as $type => $post_id ) {
			$filter = 'bbp_get_' . $type . '_content';
			$getter = $filter;
			$this->assertSame( 35, has_filter( $filter, 'bbp_wrap_orphan_list_items' ) );
			foreach ( $fixtures as $content ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
				remove_filter( $filter, 'bbp_wrap_orphan_list_items', 35 );
				try {
					$before = $getter( $post_id );
				} finally {
					add_filter( $filter, 'bbp_wrap_orphan_list_items', 35, 2 );
				}
				$this->assertSame( $before, $getter( $post_id ) );
			}
		}
		$this->assertFalse( has_filter( 'the_content', 'bbp_wrap_orphan_list_items' ) );
	}

	public function test_existing_balancer_closes_unclosed_items_before_repair() {
		$content  = force_balance_tags( '<li>One<li>Two' );
		$expected = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) )
			? '<ul><li>One</li><li>Two</li></ul>'
			: $content;
		$this->assertSame( $expected, bbp_wrap_orphan_list_items( $content ) );
	}
}
