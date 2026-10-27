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
			'non-ASCII decimal reference' => array( '<li>&#233;</li>', '<ul><li>&#233;</li></ul>' ),
			'decimal reference boundary' => array( '<li>&#1000;</li>', '<ul><li>&#1000;</li></ul>' ),
			'non-linefeed hexadecimal reference' => array( '<li>&#xAA;</li>', '<ul><li>&#xAA;</li></ul>' ),
			'bare ampersand' => array( '<li>Fish & chips</li>', '<ul><li>Fish & chips</li></ul>' ),
			'escaped ampersand' => array( '<li>Fish &#038; chips</li>', '<ul><li>Fish &#038; chips</li></ul>' ),
			'named reference' => array( '<li>&copy; Example</li>', '<ul><li>&copy; Example</li></ul>' ),
			'references in attributes' => array( '<li title="&quot;&copy;">One</li>', '<ul><li title="&quot;&copy;">One</li></ul>' ),
			'unquoted legacy reference' => array( '<li title=&copy>One</li>', '<ul><li title=&copy>One</li></ul>' ),
			'ambiguous attribute ampersand' => array( '<li title="a&amp=b">One</li>', '<ul><li title="a&amp=b">One</li></ul>' ),
			'ampersand in attribute name' => array( '<li>&copy;</li><span data-&copy;="x">Two</span>', '<ul><li>&copy;</li></ul><span data-&copy;="x">Two</span>' ),
			'private-use characters' => array( "<li>\u{E000}\u{E001} &copy;</li>", "<ul><li>\u{E000}\u{E001} &copy;</li></ul>" ),
			'null byte in text' => array( "<li>bbp_amper\x00sand_ &copy;</li>", "<ul><li>bbp_amper\x00sand_ &copy;</li></ul>" ),
			'encoded spacing between items' => array( '<li>One</li>&#32;&NewLine;<li>Two</li>', '<ul><li>One</li>&#32;&NewLine;<li>Two</li></ul>' ),
			'encoded text between items' => array( '<li>One</li>&copy;<li>Two</li>', '<ul><li>One</li></ul>&copy;<ul><li>Two</li></ul>' ),
			'encoded leading pre newline' => array( '<pre>&#10;x</pre><li>One</li>', '<pre>&#10;x</pre><ul><li>One</li></ul>' ),
			'encoded leading textarea newline' => array( '<textarea>&#10;x</textarea><li>One</li>', '<textarea>&#10;x</textarea><ul><li>One</li></ul>' ),
			'mixed pre newlines' => array( "<pre>\n&#10;x</pre><li>One</li>", "<pre>\n&#10;x</pre><ul><li>One</li></ul>" ),
			'mixed listing newlines' => array( "<listing>\n&NewLine;x</listing><li>One</li>", "<listing>\n&NewLine;x</listing><ul><li>One</li></ul>" ),
			'mixed textarea newlines' => array( "<textarea>\n&#10;x</textarea><li>One</li>", "<textarea>\n&#10;x</textarea><ul><li>One</li></ul>" ),
			'attributes and case' => array( "<LI class='item'>One</LI>", "<ul><LI class='item'>One</LI></ul>" ),
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
			'implicit closing boundaries are unchanged' => array( '<div><li>One</div><li>Two', '<div><li>One</div><li>Two' ),
			'unclosed adjacent items are unchanged' => array( '<li>One<li>Two', '<li>One<li>Two' ),
			'text boundary' => array( '<li>One</li>Text<li>Two</li>', '<ul><li>One</li></ul>Text<ul><li>Two</li></ul>' ),
			'literal list tags in comments' => array( '<!-- <li>Example</li> --><li>One</li>', '<!-- <li>Example</li> --><ul><li>One</li></ul>' ),
			'comment between items' => array( '<li>One</li><!-- next --><li>Two</li>', '<ul><li>One</li><!-- next --><li>Two</li></ul>' ),
			'ignored list end' => array( '<!-- <ul> --><li>One</li></ul><li>Two</li>', '<!-- <ul> --><li>One</li></ul><li>Two</li>' ),
			'ignored list end inside item' => array( '<li>One</ul>Two</li>', '<li>One</ul>Two</li>' ),
			'ignored item end in nested list is unchanged' => array( '<li>One<ul><li>Two</li></li></ul><li>Three</li>', '<li>One<ul><li>Two</li></li></ul><li>Three</li>' ),
			'nested unordered list in orphan' => array( '<li>One<ul><li>Two</li></ul></li>', '<ul><li>One<ul><li>Two</li></ul></li></ul>' ),
			'list end in comment' => array( '<li>One<!-- </ul> --></li>', '<ul><li>One<!-- </ul> --></li></ul>' ),
			'list end in attribute' => array( '<li title="</ul>">One</li>', '<ul><li title="</ul>">One</li></ul>' ),
			'list end in script' => array( '<li>One<script>"</ul>"</script></li>', '<ul><li>One<script>"</ul>"</script></li></ul>' ),
			'list end in SVG style' => array( '<li><svg><style></ul></style></svg></li><li>Two</li>', '<li><svg><style></ul></style></svg></li><li>Two</li>' ),
			'list end in MathML style' => array( '<li><math><style></ul></style></math></li><li>Two</li>', '<li><math><style></ul></style></math></li><li>Two</li>' ),
			'foreign content inside item' => array( '<li><svg><circle /></svg></li>', '<ul><li><svg><circle /></svg></li></ul>' ),
			'ambiguous list tags in cdata are unchanged' => array( '<![CDATA[<li>Example</li>]]><li>One</li>', '<![CDATA[<li>Example</li>]]><li>One</li>' ),
			'processing instruction' => array( "<?example?><LI data-x='&copy;'>One</LI>", "<?example?><ul><LI data-x='&copy;'>One</LI></ul>" ),
			'unchanged case and quotes' => array( "<UL><LI class='item'>One</LI></UL>", "<UL><LI class='item'>One</LI></UL>" ),
			'incomplete input' => array( '<li>One</li><!--', '<li>One</li><!--' ),
			'unsupported parsing' => array( '<li>One</li><a><div><a></div></a>', '<li>One</li><a><div><a></div></a>' ),
			'ampersand opening syntax' => array( '<li>One</li><&x>', '<ul><li>One</li></ul><&x>' ),
			'ampersand closing syntax' => array( '<li>One</li></&x>', '<ul><li>One</li></ul></&x>' ),
			'ampersand in tag name' => array( '<li>One</li><p&x>Two</p&x>', '<ul><li>One</li></ul><p&x>Two</p&x>' ),
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
			'textarea quoted angle bracket' => array( "<textarea title='>'>\n\nx</textarea><li>One</li>", "<textarea title='>'>\n\nx</textarea><ul><li>One</li></ul>" ),
			'invalid textarea text' => array( "<textarea>\n\ncaf\xE9</textarea><li>One</li>", "<textarea>\n\ncaf\xE9</textarea><ul><li>One</li></ul>" ),
			'nested orphans are unchanged' => array( '<li>One<blockquote><li>Two</li></blockquote></li>', '<li>One<blockquote><li>Two</li></blockquote></li>' ),
			'quoted greater-than attribute' => array( '<LI title=">">One</LI>', '<ul><LI title=">">One</LI></ul>' ),
			'literal list tags in attributes' => array( '<li title="<li>">One</li>', '<ul><li title="<li>">One</li></ul>' ),
			'literal list tags in scripts' => array( '<script>"<li>Example</li>"</script><li>One</li>', '<script>"<li>Example</li>"</script><ul><li>One</li></ul>' ),
			'nonstandard closing item is unchanged' => array( '<li>One</li title=x>', '<li>One</li title=x>' ),
			'ignored non-list tag is unchanged' => array( '<li>One</li></span>', '<li>One</li></span>' ),
			'later malformed run discards earlier repair' => array( '<li>One</li><p>Text</p><li>Two</li></ul>', '<li>One</li><p>Text</p><li>Two</li></ul>' ),
			'whitespace following the last item stays outside the list' => array( "<li>One</li>\n<p>Text</p>", "<ul><li>One</li></ul>\n<p>Text</p>" ),
			'textarea' => array( '<textarea><li>Example</li></textarea>', '<textarea><li>Example</li></textarea>' ),
			'script' => array( '<script>"<li>Example</li>"</script>', '<script>"<li>Example</li>"</script>' ),
			'escaped code' => array( '<code>&lt;li&gt;Example&lt;/li&gt;</code>', '<code>&lt;li&gt;Example&lt;/li&gt;</code>' ),
			'implied table body after orphan' => array( '<li>One</li><table><tr><td>Two</td></tr></table>', '<ul><li>One</li></ul><table><tr><td>Two</td></tr></table>' ),
			'implied table body before orphan' => array( '<table><tr><td>Two</td></tr></table><li>One</li>', '<table><tr><td>Two</td></tr></table><ul><li>One</li></ul>' ),
			'implied table body inside orphan' => array( '<li>One<table><tr><td>Two</td></tr></table></li>', '<ul><li>One<table><tr><td>Two</td></tr></table></li></ul>' ),
			'explicit table body' => array( '<li>One</li><table><tbody><tr><td>Two</td></tr></tbody></table>', '<ul><li>One</li></ul><table><tbody><tr><td>Two</td></tr></tbody></table>' ),
			'implied paragraph end' => array( '<p>Text<li>One</li></p>', '<p>Text<ul><li>One</li></ul></p>' ),
			'svg after orphan' => array( '<li>One</li><svg><circle /></svg>', '<ul><li>One</li></ul><svg><circle /></svg>' ),
			'svg before orphan' => array( '<svg><circle /></svg><li>One</li>', '<svg><circle /></svg><ul><li>One</li></ul>' ),
			'math after orphan' => array( '<li>One</li><math><mi>x</mi></math>', '<ul><li>One</li></ul><math><mi>x</mi></math>' ),
			'math before orphan' => array( '<math><mi>x</mi></math><li>One</li>', '<math><mi>x</mi></math><ul><li>One</li></ul>' ),
			'literal list opener in preceding attribute' => array( '<span title="<li>">Text</span><li>One</li>', '<span title="<li>">Text</span><ul><li>One</li></ul>' ),
			'literal list closer in attribute' => array( '<li title="</li>">One</li>', '<ul><li title="</li>">One</li></ul>' ),
			'quoted angle brackets beside orphan' => array( '<span title="\'><li>Example</li>">Text</span><li>One</li>', '<span title="\'><li>Example</li>">Text</span><ul><li>One</li></ul>' ),
			'single quoted list example' => array( '<span title=\'><li>Example</li>\'>Text</span><li>One</li>', '<span title=\'><li>Example</li>\'>Text</span><ul><li>One</li></ul>' ),
			'unquoted attribute example' => array( '<span title=<li>Text</span><li>One</li>', '<span title=<li>Text</span><ul><li>One</li></ul>' ),
			'literal list tags in joining comment' => array( '<li>One</li><!-- <li>Example</li> --><li>Two</li>', '<ul><li>One</li><!-- <li>Example</li> --><li>Two</li></ul>' ),
			'literal list tags in trailing comment' => array( '<li>One</li><!-- <li>Example</li> -->', '<ul><li>One</li></ul><!-- <li>Example</li> -->' ),
			'unterminated comment with list example' => array( '<li>One</li><!-- <li>Example</li>', '<li>One</li><!-- <li>Example</li>' ),
			'unterminated attribute with list example' => array( '<li>One</li><span title="<li>Example</li>', '<li>One</li><span title="<li>Example</li>' ),
			'offset attribute collision' => array( '<li data-bbp-list-offset="99" data-bbp-list-offsetx="100">One</li>', '<ul><li data-bbp-list-offset="99" data-bbp-list-offsetx="100">One</li></ul>' ),
			'namespace integration point' => array( '<svg><foreignObject><li>One</li></foreignObject></svg>', '<svg><foreignObject><ul><li>One</li></ul></foreignObject></svg>' ),
			'slash in unquoted attribute is unchanged' => array( '<div a=<li/b=" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>', '<div a=<li/b=" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>' ),
			'closing slash in unquoted attribute is unchanged' => array( '<div a=</li/b=" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>', '<div a=</li/b=" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>' ),
			'self closing list example in attribute is unchanged' => array( '<li title="<li/>">One</li>', '<li title="<li/>">One</li>' ),
			'self closing orphan is unchanged' => array( '<li/>One</li>', '<li/>One</li>' ),
			'self closing orphan discards earlier repair' => array( '<li>One</li><li/>Two</li>', '<li>One</li><li/>Two</li>' ),
			'unquoted list example followed by quoted attribute' => array( '<div a=<li b="x"><li title="><li>Example</li>">One</li></div>', '<div a=<li b="x"><ul><li title="><li>Example</li>">One</li></ul></div>' ),
			'html cdata before foreign content is unchanged' => array( '<li>One</li><![CDATA[<li>Example</li>]]><svg><circle /></svg>', '<li>One</li><![CDATA[<li>Example</li>]]><svg><circle /></svg>' ),
			'foreign cdata before html breakout is unchanged' => array( '<svg><![CDATA[><li>x</li>]]><li>One</li>', '<svg><![CDATA[><li>x</li>]]><li>One</li>' ),
			'void elements inside orphan' => array( '<li>One<br>Two<img src="x"></li>', '<ul><li>One<br>Two<img src="x"></li></ul>' ),
			'whitespace in item closer' => array( '<li>One</li >', '<ul><li>One</li ></ul>' ),
			'slash tag cannot borrow an attribute marker' => array( '<li/a=<li >One</li>', '<li/a=<li >One</li>' ),
			'slash tag cannot borrow a closing attribute marker' => array( '<li/a=</li >One</li>', '<li/a=</li >One</li>' ),
			'slash list example in comment is unchanged' => array( '<!-- <li/> --><li>One</li>', '<!-- <li/> --><li>One</li>' ),
			'list lookalike attribute name before equals' => array( '<div <li =" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>', '<div <li =" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>' ),
			'closing lookalike attribute name before equals' => array( '<div </li =" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>', '<div </li =" ><li>One</li><li title="><li>Example</li><li>Second">Two</li></div>' ),
			'line break before equals is unchanged' => array( "<div <li\n=\" ><li>One</li><li title=\"><li>Example</li><li>Second\">Two</li></div>", "<div <li\n=\" ><li>One</li><li title=\"><li>Example</li><li>Second\">Two</li></div>" ),
			'real item with equals delimiter is unchanged' => array( '<li ="x">One</li>', '<li ="x">One</li>' ),
			'equals tag cannot borrow an attribute marker' => array( '<li ="x" a=<li >One</li>', '<li ="x" a=<li >One</li>' ),
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

	public function test_content_filters_repair_items_beside_unrelated_markup() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		$fixtures = array(
			'<li>One</li><table><tr><td>Two</td></tr></table>',
			'<li>One</li><svg><circle /></svg>',
			'<li>One</li><math><mi>x</mi></math>',
			'<!-- <li>Example</li> --><li>One</li>',
		);
		foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $filter ) {
			foreach ( $fixtures as $content ) {
				$wrapped = $modern ? str_replace( '<li>One</li>', '<ul><li>One</li></ul>', $content ) : $content;
				remove_filter( $filter, 'bbp_wrap_orphan_list_items', 35 );
				try {
					$expected = apply_filters( $filter, $wrapped );
				} finally {
					add_filter( $filter, 'bbp_wrap_orphan_list_items', 35, 2 );
				}
				$this->assertSame( $expected, apply_filters( $filter, $content ) );
			}

		}
	}

	public function test_ignored_list_end_surviving_tag_balancing_is_unchanged() {
		$content = '<!-- <ul> --><li>One</li></ul><li>Two</li>';
		// This fixture reaches the filter because the balancer counts commented tags.
		$this->assertSame( $content, force_balance_tags( $content ) );
		$this->assertSame( $content, bbp_wrap_orphan_list_items( force_balance_tags( $content ) ) );
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
			foreach ( array( '', 'x', "\nx", "\n\nx", "\n\n\nx", "\r\n\r\nx", "\n\n&amp;lt;&amp;#10;&lt;/textarea&gt;", "\n&#10;x", "\r\n&#x0A;x", "\r&NewLine;x", "\n&#10x" ) as $text ) {
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

	public function test_opaque_payloads_are_preserved() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		foreach ( array( 'iframe', 'noembed', 'noframes', 'script', 'style', 'textarea', 'title', 'xmp' ) as $tag ) {
			$content = '<' . $tag . '>Example &amp;lt; &lt;b&gt; <li>Literal</li></' . $tag . '><li>One</li>';
			$output  = bbp_wrap_orphan_list_items( $content );
			$this->assertSame( $modern ? str_replace( '<li>One</li>', '<ul><li>One</li></ul>', $content ) : $content, $output, $tag );
		}
	}

	public function test_ascii_references_do_not_change_later_content_formatting() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		foreach ( array( '&#10;', '&#010;', '&#00010;', '&#10', '&#xA;', '&#Xa;', '&#x000a;', '&#xA', '&NewLine;', '&#13;', '&#9;', '&#58;', '&#64;' ) as $reference ) {
			$fixtures = array(
				array( '<li>One' . $reference . 'Two</li>', '<ul><li>One' . $reference . 'Two</li></ul>' ),
				array( '<p>a' . $reference . $reference . 'b</p><li>x</li>', '<p>a' . $reference . $reference . 'b</p><ul><li>x</li></ul>' ),
				array( '<listing>' . $reference . 'x</listing><li>One</li>', '<listing>' . $reference . 'x</listing><ul><li>One</li></ul>' ),
				array( '<textarea>a' . $reference . 'z</textarea><li>One</li>', '<textarea>a' . $reference . 'z</textarea><ul><li>One</li></ul>' ),
			);
			foreach ( $fixtures as $fixture ) {
				list( $content, $wrapped ) = $fixture;
				$this->assertSame( $modern ? $wrapped : $content, bbp_wrap_orphan_list_items( $content ) );
				// convert_chars() escapes incomplete references before this filter.
				if ( ';' !== substr( $reference, -1 ) ) {
					continue;
				}
				foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $filter ) {
					remove_filter( $filter, 'bbp_wrap_orphan_list_items', 35 );
					try {
						$before = apply_filters( $filter, $modern ? $wrapped : $content );
					} finally {
						add_filter( $filter, 'bbp_wrap_orphan_list_items', 35, 2 );
					}
					$this->assertSame( $before, apply_filters( $filter, $content ), $reference );
				}
			}
		}
	}

	public function test_encoded_punctuation_does_not_create_links() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		foreach ( array( 'http&#58;//example.com', 'name&#64;example.com', 'www&#46;example&#46;com', 'http&colon;//example.com', 'name&commat;example.com', 'www&period;example&period;com' ) as $text ) {
			$content = '<li>' . $text . '</li>';
			$this->assertSame( $modern ? '<ul>' . $content . '</ul>' : $content, bbp_wrap_orphan_list_items( $content ) );
			foreach ( array( 'bbp_get_topic_content', 'bbp_get_reply_content' ) as $filter ) {
				$this->assertStringNotContainsString( '<a ', apply_filters( $filter, $content ) );
			}
		}
	}

	public function test_getters_repair_reference_content_without_changing_storage() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		$forum_id = $this->factory->forum->create();
		$topic_id = $this->factory->topic->create( array( 'post_parent' => $forum_id ) );
		$reply_id = $this->factory->reply->create( array( 'post_parent' => $topic_id ) );
		foreach ( array( 'topic' => $topic_id, 'reply' => $reply_id ) as $type => $post_id ) {
			$getter = 'bbp_get_' . $type . '_content';
			foreach ( array( 'Fish & chips', 'Fish &#038; chips', '&copy; Example', 'http&#58;//example.com', 'name&commat;example.com', 'One&#10;Two' ) as $text ) {
				$content = '<li>' . $text . '</li>';
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
				$stored = get_post_field( 'post_content', $post_id, 'raw' );
				remove_filter( $getter, 'bbp_wrap_orphan_list_items', 35 );
				try {
					$before = $getter( $post_id );
				} finally {
					add_filter( $getter, 'bbp_wrap_orphan_list_items', 35, 2 );
				}
				$output = $getter( $post_id );
				if ( $modern ) {
					$this->assertStringContainsString( '<ul>', $output );
					$this->assertStringContainsString( '</ul>', $output );
				} else {
					$this->assertStringNotContainsString( '<ul>', $output );
				}
				// WordPress may decode punctuation on save; retain its existing links.
				preg_match_all( '#<a\b[^>]*>.*?</a>#s', $before, $links_before );
				preg_match_all( '#<a\b[^>]*>.*?</a>#s', $output, $links_after );
				$this->assertSame( $links_before, $links_after );
				preg_match_all( '/&(?:\#(?:[0-9]+|x[0-9a-f]+)|[a-z][a-z0-9]*);/i', $before, $references );
				foreach ( $references[0] as $reference ) {
					$this->assertStringContainsString( $reference, $output );
				}
				$this->assertSame( $stored, get_post_field( 'post_content', $post_id, 'raw' ) );
			}
		}
	}

	public function test_implied_table_and_foreign_markup_retains_its_source() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		$fixtures = array(
			'<li>One</li><math><annotation-xml encoding="text/html"><p>Two</p></annotation-xml></math>',
			'<li>One</li><table> <tr><td>Two</td></tr></table>',
			'<li>One</li><math><annotation-xml encoding="text&#47;html"><p>Two</p></annotation-xml></math>',
			'<li>One</li><table>&#32;<tr><td>Two</td></tr></table>',
		);
		foreach ( $fixtures as $content ) {
			$expected = $modern ? str_replace( '<li>One</li>', '<ul><li>One</li></ul>', $content ) : $content;
			$this->assertSame( $expected, bbp_wrap_orphan_list_items( $content ) );
			$this->assertSame( $expected, bbp_wrap_orphan_list_items( $expected ) );
		}
	}

	public function test_discarded_markup_does_not_create_character_references() {
		$modern = class_exists( 'WP_HTML_Processor' ) && is_callable( array( WP_HTML_Processor::create_fragment( '' ), 'serialize_token' ) );
		$malformed = '<li>&</div>lt;b&</div>gt;</li>';
		$this->assertSame( $malformed, bbp_wrap_orphan_list_items( $malformed ) );
		$content = "<li>&\x00amp;</li>";
		$this->assertSame( $modern ? '<ul>' . $content . '</ul>' : $content, bbp_wrap_orphan_list_items( $content ) );
	}

	public function test_lookalike_tags_do_not_load_stored_content() {
		global $wpdb;
		foreach ( array( '<link href="style.css">', '<listing>Example</listing>', 'No list items.', '&lt;li&gt;Example&lt;/li&gt;' ) as $content ) {
			$queries = $wpdb->num_queries;
			$this->assertSame( $content, bbp_wrap_orphan_list_items( $content, PHP_INT_MAX ) );
			$this->assertSame( $queries, $wpdb->num_queries );
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
