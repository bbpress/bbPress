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
	 * Paired newline sequences produce one HTML line break each.
	 *
	 * @covers BBCode::nl2br
	 * @ticket 3770
	 */
	public function test_nl2br_treats_paired_newlines_as_single_sequences() {
		$parser = BBCode::getInstance();

		$this->assertSame( "a<br />\nb", $parser->nl2br( "a\r\nb" ) );
		$this->assertSame( "a<br />\nb", $parser->nl2br( "a\n\rb" ) );
		$this->assertSame( "a<br />\n<br />\nb", $parser->nl2br( "a\r\n\r\nb" ) );
	}

	/**
	 * Scalar parser settings retain their configured values.
	 *
	 * @covers BBCode::SetPreTrim
	 * @covers BBCode::GetPreTrim
	 * @covers BBCode::SetPostTrim
	 * @covers BBCode::GetPostTrim
	 * @covers BBCode::SetRoot
	 * @covers BBCode::SetRootInline
	 * @covers BBCode::SetRootBlock
	 * @covers BBCode::GetRoot
	 * @covers BBCode::SetDebug
	 * @covers BBCode::GetDebug
	 * @covers BBCode::SetTagMarker
	 * @covers BBCode::GetTagMarker
	 * @covers BBCode::SetIgnoreNewlines
	 * @covers BBCode::GetIgnoreNewlines
	 * @covers BBCode::SetLimit
	 * @covers BBCode::GetLimit
	 * @covers BBCode::SetLimitTail
	 * @covers BBCode::GetLimitTail
	 * @covers BBCode::SetLimitPrecision
	 * @covers BBCode::GetLimitPrecision
	 * @covers BBCode::SetPlainMode
	 * @covers BBCode::GetPlainMode
	 * @covers BBCode::SetDetectURLs
	 * @covers BBCode::GetDetectURLs
	 * @covers BBCode::SetURLPattern
	 * @covers BBCode::GetURLPattern
	 * @covers BBCode::SetURLTargetable
	 * @covers BBCode::GetURLTargetable
	 * @covers BBCode::SetURLTarget
	 * @covers BBCode::GetURLTarget
	 * @ticket BBP3706
	 */
	public function test_scalar_parser_settings_round_trip() {
		$parser = BBCode::getInstance();

		$this->assertSame( 'block', $parser->GetRoot() );
		$this->assertSame( '...', $parser->GetLimitTail() );
		$this->assertSame( 0.15, $parser->GetLimitPrecision() );
		$this->assertFalse( $parser->GetURLTarget() );

		$parser->SetPreTrim( '<' );
		$parser->SetPostTrim( '>' );
		$this->assertSame( '<', $parser->GetPreTrim() );
		$this->assertSame( '>', $parser->GetPostTrim() );

		$parser->SetRoot( 'columns' );
		$this->assertSame( 'columns', $parser->GetRoot() );
		$parser->SetRootInline();
		$this->assertSame( 'inline', $parser->GetRoot() );
		$parser->SetRootBlock();
		$this->assertSame( 'block', $parser->GetRoot() );

		$parser->SetDebug( false );
		$this->assertFalse( $parser->GetDebug() );
		$parser->SetDebug();
		$parser->SetTagMarker( '<' );
		$parser->SetIgnoreNewlines( false );
		$this->assertFalse( $parser->GetIgnoreNewlines() );
		$parser->SetIgnoreNewlines();
		$parser->SetLimit( 42 );
		$parser->SetLimitTail( 'more' );
		$parser->SetLimitPrecision( 0.5 );
		$parser->SetPlainMode( false );
		$this->assertFalse( $parser->GetPlainMode() );
		$parser->SetPlainMode();
		$parser->SetDetectURLs( false );
		$this->assertFalse( $parser->GetDetectURLs() );
		$parser->SetDetectURLs();
		$parser->SetURLPattern( '<a data-url="{$url/h}">{$text/h}</a>' );
		$parser->SetURLTargetable( 'override' );
		$this->assertSame( 'override', $parser->GetURLTargetable() );
		$parser->SetURLTargetable( true );
		$parser->SetURLTarget( '_blank' );

		$this->assertTrue( $parser->GetDebug() );
		$this->assertSame( '<', $parser->GetTagMarker() );
		$this->assertTrue( $parser->GetIgnoreNewlines() );
		$this->assertSame( 42, $parser->GetLimit() );
		$this->assertSame( 'more', $parser->GetLimitTail() );
		$this->assertSame( 0.5, $parser->GetLimitPrecision() );
		$this->assertTrue( $parser->GetPlainMode() );
		$this->assertTrue( $parser->GetDetectURLs() );
		$this->assertSame( '<a data-url="{$url/h}">{$text/h}</a>', $parser->GetURLPattern() );
		$this->assertTrue( $parser->GetURLTargetable() );
		$this->assertSame( '_blank', $parser->GetURLTarget() );
	}

	/**
	 * Parser rules can be added, removed, cleared, and restored from defaults.
	 *
	 * @covers BBCode::AddRule
	 * @covers BBCode::RemoveRule
	 * @covers BBCode::GetRule
	 * @covers BBCode::ClearRules
	 * @covers BBCode::GetDefaultRule
	 * @covers BBCode::SetDefaultRule
	 * @covers BBCode::GetDefaultRules
	 * @covers BBCode::SetDefaultRules
	 * @ticket BBP3706
	 */
	public function test_parser_rule_collection_and_defaults() {
		$parser       = BBCode::getInstance();
		$default_bold = $parser->GetDefaultRule( 'b' );
		$custom       = array( 'simple_start' => '<custom>', 'simple_end' => '</custom>' );

		$this->assertIsArray( $default_bold );
		$this->assertFalse( $parser->GetDefaultRule( 'missing' ) );
		$parser->ClearRules();
		$this->assertSame( array(), $parser->tag_rules );
		$this->assertFalse( $parser->GetRule( 'b' ) );

		$parser->AddRule( 'custom', $custom );
		$this->assertSame( $custom, $parser->GetRule( 'custom' ) );
		$parser->RemoveRule( 'custom' );
		$this->assertFalse( $parser->GetRule( 'custom' ) );

		$parser->SetDefaultRule( 'b' );
		$this->assertSame( $default_bold, $parser->GetRule( 'b' ) );
		$parser->AddRule( 'missing', $custom );
		$parser->SetDefaultRule( 'missing' );
		$this->assertFalse( $parser->GetRule( 'missing' ) );

		$parser->ClearRules();
		$parser->SetDefaultRules();
		$this->assertSame( $parser->GetDefaultRules(), $parser->tag_rules );
	}

	/**
	 * Parser resource paths and smiley collections expose their defaults.
	 *
	 * @covers BBCode::SetWikiURL
	 * @covers BBCode::GetWikiURL
	 * @covers BBCode::GetDefaultWikiURL
	 * @covers BBCode::SetLocalImgDir
	 * @covers BBCode::GetLocalImgDir
	 * @covers BBCode::GetDefaultLocalImgDir
	 * @covers BBCode::SetLocalImgURL
	 * @covers BBCode::GetLocalImgURL
	 * @covers BBCode::GetDefaultLocalImgURL
	 * @covers BBCode::SetRuleHTML
	 * @covers BBCode::GetRuleHTML
	 * @covers BBCode::GetDefaultRuleHTML
	 * @covers BBCode::AddSmiley
	 * @covers BBCode::RemoveSmiley
	 * @covers BBCode::GetSmiley
	 * @covers BBCode::ClearSmileys
	 * @covers BBCode::GetDefaultSmiley
	 * @covers BBCode::SetDefaultSmiley
	 * @covers BBCode::GetDefaultSmileys
	 * @covers BBCode::SetDefaultSmileys
	 * @covers BBCode::SetSmileyDir
	 * @covers BBCode::GetSmileyDir
	 * @covers BBCode::GetDefaultSmileyDir
	 * @covers BBCode::SetSmileyURL
	 * @covers BBCode::GetSmileyURL
	 * @covers BBCode::GetDefaultSmileyURL
	 * @covers BBCode::SetEnableSmileys
	 * @covers BBCode::GetEnableSmileys
	 * @ticket BBP3706
	 */
	public function test_parser_resource_paths_and_smiley_defaults() {
		$parser = BBCode::getInstance();

		$this->assertSame( '/?page=', $parser->GetDefaultWikiURL() );
		$this->assertSame( 'img', $parser->GetDefaultLocalImgDir() );
		$this->assertSame( 'img', $parser->GetDefaultLocalImgURL() );
		$this->assertSame( "\n<hr class=\"bbcode_rule\" />\n", $parser->GetDefaultRuleHTML() );
		$this->assertSame( 'smileys', $parser->GetDefaultSmileyDir() );
		$this->assertSame( 'smileys', $parser->GetDefaultSmileyURL() );

		$parser->SetWikiURL( '/wiki/' );
		$parser->SetLocalImgDir( '/tmp/images' );
		$parser->SetLocalImgURL( 'https://example.com/images' );
		$parser->SetRuleHTML( '<hr>' );
		$parser->SetSmileyDir( '/tmp/smileys' );
		$parser->SetSmileyURL( 'https://example.com/smileys' );
		$parser->SetEnableSmileys( false );

		$this->assertSame( '/wiki/', $parser->GetWikiURL( 'ignored' ) );
		$this->assertSame( '/tmp/images', $parser->GetLocalImgDir() );
		$this->assertSame( 'https://example.com/images', $parser->GetLocalImgURL() );
		$this->assertSame( '<hr>', $parser->GetRuleHTML() );
		$this->assertSame( '/tmp/smileys', $parser->GetSmileyDir() );
		$this->assertSame( 'https://example.com/smileys', $parser->GetSmileyURL() );
		$this->assertFalse( $parser->GetEnableSmileys() );

		$this->assertSame( 'smile.gif', $parser->GetDefaultSmiley( ':)' ) );
		$this->assertFalse( $parser->GetDefaultSmiley( ':missing:' ) );
		$parser->ClearSmileys();
		$this->assertFalse( $parser->GetSmiley( ':)' ) );
		$parser->AddSmiley( ':custom:', 'custom.png' );
		$this->assertSame( 'custom.png', $parser->GetSmiley( ':custom:' ) );
		$parser->RemoveSmiley( ':custom:' );
		$this->assertFalse( $parser->GetSmiley( ':custom:' ) );
		$parser->SetDefaultSmiley( ':)' );
		$this->assertSame( 'smile.gif', $parser->GetSmiley( ':)' ) );
		$parser->AddSmiley( ':missing:', 'custom.png' );
		$parser->SetDefaultSmiley( ':missing:' );
		$this->assertFalse( $parser->GetSmiley( ':missing:' ) );

		$parser->ClearSmileys();
		$parser->SetDefaultSmileys();
		$this->assertSame( $parser->GetDefaultSmileys(), $parser->smileys );
	}

	/**
	 * Changing smileys invalidates the parser's cached smiley expression.
	 *
	 * @covers BBCode::AddSmiley
	 * @covers BBCode::Parse
	 * @ticket BBP3706
	 */
	public function test_adding_smiley_after_parsing_rebuilds_smiley_expression() {
		$parser = BBCode::getInstance();

		$parser->ClearSmileys();
		$parser->AddSmiley( ':first:', 'first.png' );
		$this->parse_without_errors( ':first:', $parser );
		$parser->AddSmiley( ':second:', 'second.png' );

		$this->assertStringContainsString( 'second.png', $this->parse_without_errors( ':second:', $parser ) );
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
