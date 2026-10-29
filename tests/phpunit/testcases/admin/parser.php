<?php

/**
 * Tests for the legacy BBCode parser used by forum converters.
 *
 * @group admin
 */
class BBP_Tests_Admin_Parser extends BBP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		require_once BBP_PLUGIN_DIR . 'includes/admin/parser.php';
		$this->reset_parser();
	}

	public function tearDown(): void {
		$this->reset_parser();

		parent::tearDown();
	}

	private function reset_parser() {
		$instance = new ReflectionProperty( 'BBCode', 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance->setAccessible( true );
		}
		$instance->setValue( null, null );
	}

	private function parse_without_errors( $input, $parser = null ) {
		set_error_handler(
			function ( $severity, $message, $file, $line ) {
				if ( error_reporting() & $severity ) {
					throw new ErrorException( $message, 0, $severity, $file, $line );
				}

				return false;
			}
		);

		try {
			$parser = $parser ?: BBCode::getInstance();

			return $parser->Parse( $input );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * @covers BBCode::getInstance
	 * @covers BBCode::Parse
	 * @ticket 3727
	 *
	 * @dataProvider bbcode_provider
	 */
	public function test_common_bbcode_is_converted( $input, $expected ) {
		$this->assertSame( $expected, $this->parse_without_errors( $input ) );
	}

	public static function bbcode_provider() {
		return array(
			'bold'          => array( '[b]Bold[/b]', '<b>Bold</b>' ),
			'italic'        => array( '[i]Italic[/i]', '<i>Italic</i>' ),
			'underline'     => array( '[u]Under[/u]', '<u>Under</u>' ),
			'strikethrough' => array( '[s]Strike[/s]', '<strike>Strike</strike>' ),
			'link'          => array( '[url=https://example.com]Example[/url]', '<a href="https://example.com" class="bbcode_url">Example</a>' ),
			'email'         => array( '[email]a@example.com[/email]', '<a href="mailto:a@example.com" class="bbcode_email">a@example.com</a>' ),
			'image'         => array( '[img]https://example.com/a.png[/img]', '<img src="https://example.com/a.png" alt="a.png" class="bbcode_img" />' ),
			'quote'         => array( '[quote=Name]Q[/quote]', "\n<div class=\"bbcode_quote\">\n<div class=\"bbcode_quote_head\">Name wrote:</div>\n<div class=\"bbcode_quote_body\">Q</div>\n</div>\n" ),
			'size'          => array( '[size=120]Big[/size]', '<span style="font-size:1.0em">Big</span>' ),
			'font'          => array( '[font=Arial]Text[/font]', '<span style="font-family:\'Arial\'">Text</span>' ),
			'list'          => array( '[list][*]a[*]b[/list]', "\n<ul class=\"bbcode_list\">\n<li>a</li>\n<li>b</li>\n</ul>\n" ),
			'numbered list' => array( '[list=01][*]a[*]b[/list]', "\n<ol class=\"bbcode_list\" style=\"list-style-type:decimal-leading-zero\">\n<li>a</li>\n<li>b</li>\n</ol>\n" ),
			'wiki'          => array( '[[Page]]', '<a href="/?page=Page" class="bbcode_wiki">Page</a>' ),
			'wiki title'    => array( '[[Page|Title]]', '<a href="/?page=Page" class="bbcode_wiki">Title</a>' ),
			'newlines'      => array( "line1\nline2", "line1<br />\nline2" ),
		);
	}

	/**
	 * @covers BBCode::Parse
	 * @covers BBCode::Internal_ProcessVerbatimTag
	 * @covers BBCode::Internal_ProcessSmileys
	 * @ticket 3727
	 */
	public function test_verbatim_tags_and_missing_smiley_images_do_not_raise_errors() {
		$parser = BBCode::getInstance();
		$code   = $this->parse_without_errors( '[code]<b>x</b>[/code]', $parser );
		$this->assertStringContainsString( 'class="bbcode_code"', $code );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $code );

		$parser->AddSmiley( ':missing:', 'missing.png' );
		$smiley = $this->parse_without_errors( 'Hi :missing:', $parser );
		$this->assertStringContainsString( 'src="smileys/missing.png"', $smiley );
		$this->assertStringContainsString( 'width="" height=""', $smiley );
		$this->assertStringContainsString( 'alt=":missing:"', $smiley );

		$this->assertSame( '[img]missing.png[/img]', $this->parse_without_errors( '[img]missing.png[/img]', $parser ) );
		$this->assertSame( '<b>x</b>', $this->parse_without_errors( '[b]x', $parser ) );
		$this->assertSame( '[code]x', $this->parse_without_errors( '[code]x', $parser ) );
		$this->assertSame( '', $this->parse_without_errors( null, $parser ) );
	}

	/**
	 * @covers BBCode::SetAllowAmpersand
	 * @covers BBCode::GetAllowAmpersand
	 * @covers BBCode::SetDetectURLs
	 * @covers BBCode::SetLimit
	 * @covers BBCode::WasLimited
	 * @ticket 3727
	 */
	public function test_parser_options_control_output() {
		$parser = BBCode::getInstance();
		$this->assertFalse( $parser->GetAllowAmpersand() );
		$this->assertSame( 'A &amp;amp; B', $this->parse_without_errors( 'A &amp; B', $parser ) );
		$parser->SetAllowAmpersand( true );
		$this->assertTrue( $parser->GetAllowAmpersand() );
		$this->assertSame( 'A &amp; B', $this->parse_without_errors( 'A &amp; B', $parser ) );

		$parser->SetDetectURLs( true );
		$this->assertSame( 'Visit <a href="https://example.com/">https://example.com</a> now', $this->parse_without_errors( 'Visit https://example.com now', $parser ) );

		$parser->SetDetectURLs( false );
		$parser->SetLimit( 5 );
		$this->assertSame( '...', $this->parse_without_errors( 'abcdefghij', $parser ) );
		$this->assertTrue( $parser->WasLimited() );
	}

	/**
	 * @covers BBCode::IsValidURL
	 * @covers BBCode::IsValidEmail
	 * @covers BBCodeEmailAddressValidator::check_email_address
	 * @ticket 3727
	 */
	public function test_url_and_email_validation() {
		$parser = BBCode::getInstance();
		$this->assertTrue( $parser->IsValidURL( 'https://example.com/path' ) );
		$this->assertTrue( $parser->IsValidURL( 'mailto:a@example.com' ) );
		$this->assertFalse( $parser->IsValidURL( 'javascript:alert(1)' ) );
		$this->assertTrue( $parser->IsValidEmail( 'a@example.com' ) );
		$this->assertFalse( $parser->IsValidEmail( 'not-an-email' ) );
	}

	/**
	 * The lexer recognizes structural tokens and decoded tag parameters.
	 *
	 * @covers BBCodeLexer::__construct
	 * @covers BBCodeLexer::NextToken
	 * @covers BBCodeLexer::Internal_DecodeTag
	 * @covers BBCodeLexer::Internal_ClassifyPiece
	 * @covers BBCodeLexer::Internal_StripQuotes
	 * @ticket BBP3706
	 */
	public function test_lexer_tokenizes_comments_wiki_rules_and_tag_parameters() {
		$lexer = new BBCodeLexer( "a\n-----[-- comment][[Page|Title]][url=\"https://example.com\" title=\"Hi there\"]x[/url]" );

		$this->assertSame( BBCODE_TEXT, $lexer->NextToken() );
		$this->assertSame( 'a', $lexer->text );
		$this->assertSame( BBCODE_NL, $lexer->NextToken() );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'rule', $lexer->tag['_name'] );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( array( 'wiki', 'Page', 'Title' ), array( $lexer->tag['_name'], $lexer->tag['_default'], $lexer->tag['title'] ) );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'url', $lexer->tag['_name'] );
		$this->assertSame( 'https://example.com', $lexer->tag['_default'] );
		$this->assertSame( 'Hi there', $lexer->tag['title'] );
		$this->assertSame(
			array(
				array( 'key' => '', 'value' => 'https://example.com' ),
				array( 'key' => 'title', 'value' => 'Hi there' ),
			),
			$lexer->tag['_params']
		);
		$this->assertSame( BBCODE_TEXT, $lexer->NextToken() );
		$this->assertSame( 'x', $lexer->text );
		$this->assertSame( BBCODE_ENDTAG, $lexer->NextToken() );
		$this->assertSame( 'url', $lexer->tag['_name'] );
		$this->assertTrue( $lexer->tag['_end'] );
		$this->assertSame( BBCODE_EOI, $lexer->NextToken() );
	}

	/**
	 * The lexer supports token pushback, verbatim input, and alternate markers.
	 *
	 * @covers BBCodeLexer::__construct
	 * @covers BBCodeLexer::NextToken
	 * @covers BBCodeLexer::UngetToken
	 * @ticket BBP3706
	 */
	public function test_lexer_ungets_tokens_preserves_verbatim_and_falls_back_to_default_marker() {
		$lexer = new BBCodeLexer( '<b>x</b>', '<' );
		$this->assertSame( '<', $lexer->tagmarker );
		$this->assertSame( '>', $lexer->end_tagmarker );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'b', $lexer->tag['_name'] );
		$lexer->UngetToken();
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'b', $lexer->tag['_name'] );
		$this->assertSame( BBCODE_TEXT, $lexer->NextToken() );
		$this->assertSame( 'x', $lexer->text );
		$this->assertSame( BBCODE_ENDTAG, $lexer->NextToken() );
		$this->assertSame( 'b', $lexer->tag['_name'] );

		$verbatim           = new BBCodeLexer( "[b] x\n[/b]" );
		$verbatim->verbatim = true;
		$tokens             = array();
		$text               = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$tokens[] = $verbatim->NextToken();
			$text[]   = $verbatim->text;
		}

		$this->assertSame( array( BBCODE_TEXT, BBCODE_WS, BBCODE_TEXT, BBCODE_NL, BBCODE_TEXT ), $tokens );
		$this->assertSame( array( '[b]', ' ', 'x', "\n", '[/b]' ), $text );
		$this->assertSame( BBCODE_EOI, $verbatim->NextToken() );

		$fallback = new BBCodeLexer( '[b]x[/b]', '!' );
		$this->assertSame( '[', $fallback->tagmarker );
		$this->assertSame( ']', $fallback->end_tagmarker );
	}

	/**
	 * @covers BBCodeLexer::NextToken
	 * @covers BBCodeLexer::PeekToken
	 * @covers BBCodeLexer::GuessTextLength
	 * @covers BBCodeLexer::SaveState
	 * @covers BBCodeLexer::RestoreState
	 * @ticket 3727
	 */
	public function test_lexer_can_peek_and_restore_state() {
		$lexer = new BBCodeLexer( 'Text [b]bold[/b]' );
		$this->assertSame( 9, $lexer->GuessTextLength() );
		$this->assertSame( BBCODE_TEXT, $lexer->PeekToken() );
		$this->assertSame( BBCODE_TEXT, $lexer->NextToken() );
		$this->assertSame( 'Text', $lexer->text );
		$this->assertSame( BBCODE_WS, $lexer->NextToken() );
		$this->assertSame( ' ', $lexer->text );
		$state = $lexer->SaveState();
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'b', $lexer->tag['_name'] );
		$lexer->RestoreState( $state );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'b', $lexer->tag['_name'] );
	}
}
