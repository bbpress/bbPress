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
	 * Automatic URL detection supports one-character email local parts.
	 *
	 * @covers BBCode::Internal_AutoDetectURLs
	 * @ticket 3773
	 */
	public function test_auto_detect_urls_links_single_character_email_local_part() {
		$parser = BBCode::getInstance();
		$parser->SetDetectURLs();

		$this->assertSame( 'Mail <a href="mailto:a@example.com">a@example.com</a>', $parser->FixupOutput( 'Mail a@example.com' ) );
	}

	/**
	 * Output helpers decode, encode, normalize, and link parser text.
	 *
	 * @covers BBCode::UnHTMLEncode
	 * @covers BBCode::_UnHTMLEncode_chr_callback
	 * @covers BBCode::_UnHTMLEncode_chr_hexdec_callback
	 * @covers BBCode::Wikify
	 * @covers BBCode::HTMLEncode
	 * @covers BBCode::FixupOutput
	 * @ticket BBP3706
	 * @ticket 3772
	 */
	public function test_parser_output_helpers() {
		$parser = BBCode::getInstance();

		$this->assertSame( 'Tom & Jerry', $parser->UnHTMLEncode( 'Tom &amp; Jerry' ) );
		$this->assertSame( 'A A', $parser->UnHTMLEncode( '&#x41; &#65;' ) );
		$this->assertSame( 'A', $parser->_UnHTMLEncode_chr_callback( array( '', '41' ) ) );
		$this->assertSame( 'A', $parser->_UnHTMLEncode_chr_hexdec_callback( array( '', '65' ) ) );
		$this->assertSame( 'Hello_world', $parser->Wikify( '  Hello__world!  ' ) );
		$this->assertSame( 'A &amp; B &lt; C &quot;D&quot;', $parser->HTMLEncode( 'A & B < C "D"' ) );

		$parser->SetAllowAmpersand();
		$this->assertSame( 'A & B &lt; C &quot;D&quot;', $parser->HTMLEncode( 'A & B < C "D"' ) );

		$parser->SetDetectURLs();
		$this->assertSame( 'Visit <a href="https://example.com/">https://example.com</a>', $parser->FixupOutput( 'Visit https://example.com' ) );

		$parser->SetDetectURLs( false );
		$parser->ClearSmileys();
		$parser->AddSmiley( ':custom:', 'custom.png' );
		$smiley = $parser->FixupOutput( ':custom:' );
		$this->assertStringContainsString( 'custom.png', $smiley );
		$this->assertStringContainsString( 'class="bbcode_smiley"', $smiley );
	}

	/**
	 * Template expansion supports nested values, defaults, scalar types, and flags.
	 *
	 * @covers BBCode::FillTemplate
	 * @ticket BBP3706
	 */
	public function test_fill_template_expands_values_and_formatting_flags() {
		$parser = BBCode::getInstance();
		$values = array(
			'user'  => array( 'name' => 'John & Jane' ),
			'flag'  => true,
			'count' => 3,
			'ratio' => 1.5,
			'text'  => "  Alpha\t Beta  ",
			'path'  => '/tmp/file.txt',
			'wiki'  => 'Hello world!',
			'url'   => 'a b',
			'lines' => "a\nb",
			'html'   => "<x>\ny & z",
			'null'   => null,
			'object' => (object) array( 'name' => 'Object value' ),
			'array'  => array( 'value' ),
			'scalar' => 'value',
		);

		$this->assertSame( 'John &amp; Jane', $parser->FillTemplate( '{$user.name/h}', $values ) );
		$this->assertSame( 'fallback', $parser->FillTemplate( '{$missing}', $values, array( 'missing' => 'fallback' ) ) );
		$this->assertSame( 'true-3-1.5', $parser->FillTemplate( '{$flag}-{$count}-{$ratio}', $values ) );
		$this->assertSame( 'Alpha Beta', $parser->FillTemplate( '{$text/wt}', $values ) );
		$this->assertSame( 'file.txt', $parser->FillTemplate( '{$path/b}', $values ) );
		$this->assertSame( 'Hello_world', $parser->FillTemplate( '{$wiki/k}', $values ) );
		$this->assertSame( 'a+b', $parser->FillTemplate( '{$url/u}', $values ) );
		$this->assertSame( "a<br />\nb", $parser->FillTemplate( '{$lines/n}', $values ) );
		$this->assertSame( "<x>\ny & z", $parser->FillTemplate( '{$html/vh}', $values ) );
		$this->assertSame( "&lt;x&gt;<br />\ny &amp; z", $parser->FillTemplate( '{$html/hn}', $values ) );

		$parser->SetAllowAmpersand();
		$this->assertSame( "&lt;x&gt;\ny & z", $parser->FillTemplate( '{$html/eh}', $values ) );
		$this->assertSame( "&lt;x&gt;\ny &amp; z", $parser->FillTemplate( '{$html/h}', $values ) );

		$this->assertSame( 'insert', $parser->FillTemplate( '{$value}', array( 'value' => 'insert' ), array( 'value' => 'default' ) ) );
		$this->assertSame( 'default', $parser->FillTemplate( '{$null}', $values, array( 'null' => 'default' ) ) );
		$this->assertSame( '', $parser->FillTemplate( '{$scalar.child}', $values ) );
		$this->assertSame( 'Object value', $parser->FillTemplate( '{$object.name}', $values ) );
		$this->assertSame( '', $parser->FillTemplate( '{$array}', $values ) );
		$this->assertSame( 'No placeholders', $parser->FillTemplate( 'No placeholders', $values ) );
	}

	/**
	 * Link renderers validate destinations and honor target settings.
	 *
	 * @covers BBCodeLibrary::DoURL
	 * @covers BBCodeLibrary::DoEmail
	 * @covers BBCodeLibrary::DoWiki
	 * @ticket BBP3706
	 * @ticket 3771
	 */
	public function test_library_link_renderers() {
		$parser  = BBCode::getInstance();
		$library = new BBCodeLibrary();
		$params  = array(
			'_tag'    => '[url]',
			'_endtag' => '[/url]',
		);

		$this->assertTrue( $library->DoURL( $parser, BBCODE_CHECK, 'url', false, $params, '' ) );
		$this->assertSame( '<a href="https://example.com" class="bbcode_url">Example</a>', $library->DoURL( $parser, BBCODE_OUTPUT, 'url', 'https://example.com', $params, 'Example' ) );
		$this->assertSame( '[url]Example[/url]', $library->DoURL( $parser, BBCODE_OUTPUT, 'url', 'javascript:alert(1)', $params, 'Example' ) );

		$parser->SetURLTargetable( true );
		$params['target'] = '_self';
		$this->assertStringContainsString( ' target="_self"', $library->DoURL( $parser, BBCODE_OUTPUT, 'url', 'https://example.com', $params, 'Example' ) );
		$parser->SetURLTarget( '_blank' );
		$this->assertStringContainsString( ' target="_blank"', $library->DoURL( $parser, BBCODE_OUTPUT, 'url', 'https://example.com', $params, 'Example' ) );
		$parser->SetURLTargetable( 'override' );
		$this->assertStringContainsString( ' target="_self"', $library->DoURL( $parser, BBCODE_OUTPUT, 'url', 'https://example.com', $params, 'Example' ) );

		$email_params = array(
			'_tag'    => '[email]',
			'_endtag' => '[/email]',
		);
		$this->assertTrue( $library->DoEmail( $parser, BBCODE_CHECK, 'email', false, $email_params, '' ) );
		$this->assertSame( '<a href="mailto:a@example.com" class="bbcode_email">Email</a>', $library->DoEmail( $parser, BBCODE_OUTPUT, 'email', 'a@example.com', $email_params, 'Email' ) );
		$this->assertSame( '[email]Email[/email]', $library->DoEmail( $parser, BBCODE_OUTPUT, 'email', 'invalid', $email_params, 'Email' ) );

		$this->assertTrue( $library->DoWiki( $parser, BBCODE_CHECK, 'wiki', 'Page Name', array(), '' ) );
		$this->assertFalse( $library->DoWiki( $parser, BBCODE_CHECK, 'wiki', '!!!', array(), '' ) );
		$this->assertSame( '<a href="/?page=Page_Name" class="bbcode_wiki">Custom Title</a>', $library->DoWiki( $parser, BBCODE_OUTPUT, 'wiki', 'Page Name', array( 'title' => ' Custom Title ' ), '' ) );
	}

	/**
	 * Style and rule renderers normalize supported values.
	 *
	 * @covers BBCodeLibrary::DoSize
	 * @covers BBCodeLibrary::DoFont
	 * @covers BBCodeLibrary::DoRule
	 * @ticket BBP3706
	 */
	public function test_library_style_and_rule_renderers() {
		$parser  = BBCode::getInstance();
		$library = new BBCodeLibrary();

		$this->assertSame( '<span style="font-size:.5em">Text</span>', $library->DoSize( $parser, BBCODE_OUTPUT, 'size', '0', array(), 'Text' ) );
		$this->assertSame( '<span style="font-size:1.0em">Text</span>', $library->DoSize( $parser, BBCODE_OUTPUT, 'size', 'invalid', array(), 'Text' ) );
		$this->assertSame( '<span style="font-size:2.5em">Text</span>', $library->DoSize( $parser, BBCODE_OUTPUT, 'size', '7', array(), 'Text' ) );
		$this->assertSame( '<span style="font-family:\'Arial\',sans-serif,monospace">Text</span>', $library->DoFont( $parser, BBCODE_OUTPUT, 'font', ' Arial, sans, mono ', array(), 'Text' ) );
		$this->assertTrue( $library->DoRule( $parser, BBCODE_CHECK, 'rule', false, array(), '' ) );
		$this->assertSame( $parser->GetRuleHTML(), $library->DoRule( $parser, BBCODE_OUTPUT, 'rule', false, array(), '' ) );
	}

	/**
	 * Image and quote renderers preserve valid content and reject invalid destinations.
	 *
	 * @covers BBCodeLibrary::DoImage
	 * @covers BBCodeLibrary::DoQuote
	 * @ticket BBP3706
	 */
	public function test_library_image_and_quote_renderers() {
		$parser  = BBCode::getInstance();
		$library = new BBCodeLibrary();
		$params  = array(
			'_tag'    => '[img]',
			'_endtag' => '[/img]',
		);

		$this->assertTrue( $library->DoImage( $parser, BBCODE_CHECK, 'img', false, $params, '' ) );
		$this->assertSame( '<img src="https://example.com/a.png" alt="a.png" class="bbcode_img" />', $library->DoImage( $parser, BBCODE_OUTPUT, 'img', false, $params, ' https://example.com/a.png ' ) );
		$this->assertSame( '[img]javascript:alert(1).png[/img]', $library->DoImage( $parser, BBCODE_OUTPUT, 'img', false, $params, 'javascript:alert(1).png' ) );

		$this->assertTrue( $library->DoQuote( $parser, BBCODE_CHECK, 'quote', false, array(), '' ) );
		$quote = $library->DoQuote(
			$parser,
			BBCODE_OUTPUT,
			'quote',
			false,
			array(
				'name' => 'Author',
				'date' => 'Today',
				'url'  => 'https://example.com',
			),
			'Quoted text'
		);
		$this->assertStringContainsString( '<a href="https://example.com">Author wrote on Today:</a>', $quote );
		$this->assertStringContainsString( '<div class="bbcode_quote_body">Quoted text</div>', $quote );
		$this->assertStringContainsString( '<div class="bbcode_quote_head">Quote:</div>', $library->DoQuote( $parser, BBCODE_OUTPUT, 'quote', false, array(), 'Text' ) );
	}

	/**
	 * List rendering validates and maps ordered and unordered list styles.
	 *
	 * @covers BBCodeLibrary::DoList
	 * @ticket BBP3706
	 */
	public function test_library_list_renderer() {
		$parser  = BBCode::getInstance();
		$library = new BBCodeLibrary();

		$this->assertTrue( $library->DoList( $parser, BBCODE_CHECK, 'list', '', array(), '' ) );
		$this->assertTrue( $library->DoList( $parser, BBCODE_CHECK, 'list', 'I', array(), '' ) );
		$this->assertTrue( $library->DoList( $parser, BBCODE_CHECK, 'list', 'CIRCLE', array(), '' ) );
		$this->assertFalse( $library->DoList( $parser, BBCODE_CHECK, 'list', 'invalid', array(), '' ) );
		$this->assertSame( "\n<ul class=\"bbcode_list\">\n<li>One</li>\n</ul>\n", $library->DoList( $parser, BBCODE_OUTPUT, 'list', '', array(), "<li>One</li>\n" ) );
		$this->assertSame( "\n<ol class=\"bbcode_list\">\n<li>One</li>\n</ol>\n", $library->DoList( $parser, BBCODE_OUTPUT, 'list', '1', array(), "<li>One</li>\n" ) );
		$this->assertSame( "\n<ol class=\"bbcode_list\" style=\"list-style-type:upper-roman\">\n<li>One</li>\n</ol>\n", $library->DoList( $parser, BBCODE_OUTPUT, 'list', 'I', array(), "<li>One</li>\n" ) );
		$this->assertSame( "\n<ul class=\"bbcode_list\" style=\"list-style-type:circle\">\n<li>One</li>\n</ul>\n", $library->DoList( $parser, BBCODE_OUTPUT, 'list', 'CIRCLE', array(), "<li>One</li>\n" ) );
	}

	/**
	 * Scalar parser settings retain their configured values.
	 *
	 * @covers BBCode::__construct
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
	 * Email validation enforces local, domain, and length constraints.
	 *
	 * @covers BBCodeEmailAddressValidator::check_email_address
	 * @covers BBCodeEmailAddressValidator::check_local_portion
	 * @covers BBCodeEmailAddressValidator::check_domain_portion
	 * @covers BBCodeEmailAddressValidator::check_text_length
	 * @ticket BBP3706
	 */
	public function test_email_validator_components() {
		$validator = new BBCodeEmailAddressValidator();

		$this->assertTrue( $validator->check_email_address( 'first.last@example.com' ) );
		$this->assertTrue( $validator->check_email_address( '"a@b"@example.com' ) );
		$this->assertFalse( $validator->check_email_address( 'a@b@example.com' ) );
		$this->assertFalse( $validator->check_email_address( "a\0b@example.com" ) );

		$this->assertTrue( $validator->check_local_portion( 'first.last' ) );
		$this->assertTrue( $validator->check_local_portion( '"quoted value"' ) );
		$this->assertTrue( $validator->check_local_portion( str_repeat( 'a', 64 ) ) );
		$this->assertFalse( $validator->check_local_portion( 'first..last' ) );
		$this->assertFalse( $validator->check_local_portion( str_repeat( 'a', 65 ) ) );

		$this->assertTrue( $validator->check_domain_portion( 'example.com' ) );
		$this->assertTrue( $validator->check_domain_portion( str_repeat( 'a', 63 ) . '.com' ) );
		$this->assertTrue( $validator->check_domain_portion( '127.0.0.1' ) );
		$this->assertTrue( $validator->check_domain_portion( '[127.0.0.1]' ) );
		$this->assertFalse( $validator->check_domain_portion( 'localhost' ) );
		$this->assertFalse( $validator->check_domain_portion( '[256.0.0.1]' ) );
		$this->assertFalse( $validator->check_domain_portion( '-example.com' ) );
		$this->assertFalse( $validator->check_domain_portion( str_repeat( 'a', 64 ) . '.com' ) );

		$this->assertFalse( $validator->check_text_length( '', 1, 2 ) );
		$this->assertTrue( $validator->check_text_length( 'ab', 1, 2 ) );
		$this->assertFalse( $validator->check_text_length( 'abc', 1, 2 ) );
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

	/**
	 * The legacy lexer constructor alias reinitializes the input stream.
	 *
	 * @covers BBCodeLexer::BBCodeLexer
	 * @ticket BBP3706
	 */
	public function test_legacy_lexer_constructor_reinitializes_input() {
		$lexer = new BBCodeLexer( 'first [b]' );
		$this->assertSame( BBCODE_TEXT, $lexer->NextToken() );
		$this->assertSame( BBCODE_WS, $lexer->NextToken() );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$lexer->BBCodeLexer( '<i>y', '<' );

		$this->assertSame( '<', $lexer->tagmarker );
		$this->assertSame( BBCODE_TAG, $lexer->NextToken() );
		$this->assertSame( 'i', $lexer->tag['_name'] );
		$this->assertSame( BBCODE_TEXT, $lexer->NextToken() );
		$this->assertSame( 'y', $lexer->text );
	}

	/**
	 * Parser internals rebuild caches and inspect stack text and token types.
	 *
	 * @covers BBCode::Internal_RebuildSmileys
	 * @covers BBCode::Internal_CollectText
	 * @covers BBCode::Internal_CollectTextReverse
	 * @covers BBCode::Internal_DumpStack
	 * @ticket BBP3706
	 */
	public function test_internal_cache_text_and_stack_helpers() {
		$parser = BBCode::getInstance();
		$parser->ClearSmileys();
		$parser->AddSmiley( ':)', 'smile.png' );
		$parser->AddSmiley( '8-)', 'bigeyes.png' );
		$parser->Internal_RebuildSmileys();

		$this->assertSame( 1, preg_match( $parser->smiley_regex, ':)' ) );
		$this->assertSame( 1, preg_match( $parser->smiley_regex, '8-)' ) );
		$this->assertSame( 0, preg_match( $parser->smiley_regex, 'a:)' ) );

		$text_tokens = array(
			array( BBCODE_STACK_TEXT => 'a' ),
			array( BBCODE_STACK_TEXT => 'b' ),
			array( BBCODE_STACK_TEXT => 'c' ),
		);
		$this->assertSame( 'bc', $parser->Internal_CollectText( $text_tokens, 1 ) );
		$this->assertSame( 'cba', $parser->Internal_CollectTextReverse( $text_tokens, 2, 0 ) );
		$this->assertSame( 'cb', $parser->Internal_CollectTextReverse( $text_tokens, 2, 1 ) );

		$stack = array(
			array( BBCODE_STACK_TOKEN => BBCODE_TEXT, BBCODE_STACK_TEXT => '<x>', BBCODE_STACK_CLASS => 'inline' ),
			array( BBCODE_STACK_TOKEN => BBCODE_WS ),
			array( BBCODE_STACK_TOKEN => BBCODE_NL ),
			array( BBCODE_STACK_TOKEN => BBCODE_TAG, BBCODE_STACK_TAG => array( '_name' => 'b' ) ),
			array( BBCODE_STACK_TOKEN => -1 ),
		);
		$this->assertSame( '"&lt;x&gt;" WS NL [b] unknown ', $parser->Internal_DumpStack( $stack, true ) );
		$parser->stack = $stack;
		$this->assertSame( "<span style='color: #00C;'>\"&lt;x&gt;\" WS NL [b] unknown </span>", $parser->Internal_DumpStack() );
	}

	/**
	 * Whitespace cleanup and output limits update parser state consistently.
	 *
	 * @covers BBCode::Internal_ComputeCurrentClass
	 * @covers BBCode::Internal_CleanupWSByPoppingStack
	 * @covers BBCode::Internal_CleanupWSByEatingInput
	 * @covers BBCode::Internal_CleanupWSByIteratingPointer
	 * @covers BBCode::Internal_LimitText
	 * @covers BBCode::Internal_DoLimit
	 * @ticket BBP3706
	 */
	public function test_internal_whitespace_and_limit_helpers() {
		$parser = BBCode::getInstance();
		$text   = array( BBCODE_STACK_TOKEN => BBCODE_TEXT, BBCODE_STACK_TEXT => 'alpha', BBCODE_STACK_CLASS => 'inline' );
		$ws     = array( BBCODE_STACK_TOKEN => BBCODE_WS, BBCODE_STACK_TEXT => ' ', BBCODE_STACK_CLASS => 'inline' );
		$nl     = array( BBCODE_STACK_TOKEN => BBCODE_NL, BBCODE_STACK_TEXT => "\n", BBCODE_STACK_CLASS => 'inline' );

		$parser->stack = array( $text );
		$parser->Internal_ComputeCurrentClass();
		$this->assertSame( 'inline', $parser->current_class );
		$parser->stack = array();
		$parser->Internal_ComputeCurrentClass();
		$this->assertSame( 'block', $parser->current_class );

		$stack = array( $text, $nl, $ws );
		$parser->Internal_CleanupWSByPoppingStack( 'sn', $stack );
		$this->assertSame( array( $text ), $stack );

		$tokens = array( $ws, $nl, $text );
		$this->assertSame( 2, $parser->Internal_CleanupWSByIteratingPointer( 'sn', 0, $tokens ) );
		$newline_tokens = array( $nl, $nl, $text );
		$this->assertSame( 1, $parser->Internal_CleanupWSByIteratingPointer( 'n', 0, $newline_tokens ) );
		$this->assertSame( 2, $parser->Internal_CleanupWSByIteratingPointer( 'a', 0, $newline_tokens ) );

		$parser->lexer = new BBCodeLexer( " \t\ntext" );
		$parser->Internal_CleanupWSByEatingInput( 'a' );
		$this->assertSame( BBCODE_TEXT, $parser->lexer->NextToken() );
		$this->assertSame( 'text', $parser->lexer->text );
		$parser->lexer = new BBCodeLexer( " \ttext" );
		$parser->Internal_CleanupWSByEatingInput( 's' );
		$this->assertSame( BBCODE_TEXT, $parser->lexer->NextToken() );
		$parser->lexer = new BBCodeLexer( "\ntext" );
		$parser->Internal_CleanupWSByEatingInput( 'n' );
		$this->assertSame( BBCODE_TEXT, $parser->lexer->NextToken() );

		$this->assertSame( 'alpha beta', $parser->Internal_LimitText( 'alpha beta gamma', 10 ) );
		$parser->stack         = array( $text, $nl, $ws );
		$parser->current_class = 'inline';
		$parser->SetLimitTail( ' [more]' );
		$this->assertFalse( $parser->WasLimited() );
		$parser->Internal_DoLimit();
		$this->assertSame( array( 'alpha', ' [more]' ), array_column( $parser->stack, BBCODE_STACK_TEXT ) );
		$this->assertSame( 'inline', $parser->stack[1][BBCODE_STACK_CLASS] );
		$this->assertTrue( $parser->WasLimited() );
	}

	/**
	 * Tag dispatch validates parameters and supports each output strategy.
	 *
	 * @covers BBCode::DoTag
	 * @covers BBCode::Internal_DoEnhancedTag
	 * @ticket BBP3706
	 */
	public function test_tag_dispatch_modes_and_plain_output() {
		$parser = BBCode::getInstance();

		$this->assertTrue( $parser->DoTag( BBCODE_CHECK, 'font', 'Arial', array( '_default' => 'Arial' ), '' ) );
		$this->assertFalse( $parser->DoTag( BBCODE_CHECK, 'font', '<script>', array( '_default' => '<script>' ), '' ) );
		$this->assertSame( '<b>Text</b>', $parser->DoTag( BBCODE_OUTPUT, 'b', '', array(), 'Text' ) );
		$this->assertSame( '<span class="bbcode_acronym" title="Title">ABC</span>', $parser->DoTag( BBCODE_OUTPUT, 'acronym', 'Title', array( '_default' => 'Title' ), 'ABC' ) );
		$this->assertSame( '<a href="https://example.com" class="bbcode_url">Example</a>', $parser->DoTag( BBCODE_OUTPUT, 'url', 'https://example.com', array(), 'Example' ) );

		$parser->AddRule(
			'callback',
			array(
				'mode'   => BBCODE_MODE_CALLBACK,
				'method' => function ( $bbcode, $action, $name, $default, $params, $content ) {
					return BBCODE_CHECK === $action ? true : $name . ':' . $content;
				},
			)
		);
		$this->assertTrue( $parser->DoTag( BBCODE_CHECK, 'callback', '', array(), '' ) );
		$this->assertSame( 'callback:Text', $parser->DoTag( BBCODE_OUTPUT, 'callback', '', array(), 'Text' ) );
		$this->assertFalse( $parser->DoTag( -1, 'b', '', array(), 'Text' ) );
		$parser->AddRule(
			'enhanced',
			array(
				'mode'     => BBCODE_MODE_ENHANCED,
				'allow'    => array(
					'_content'        => '/^Body$/',
					'_defaultcontent' => '/^(Label|Body)$/',
					'tone'            => '/^(red|blue)$/',
				),
				'default'  => array( 'tone' => 'blue' ),
				'template' => '<x data-tone="{$tone}">{$_defaultcontent}:{$_content}</x>',
			)
		);
		$this->assertTrue( $parser->DoTag( BBCODE_CHECK, 'enhanced', 'Label', array(), 'Body' ) );
		$this->assertTrue( $parser->DoTag( BBCODE_CHECK, 'enhanced', '', array(), 'Body' ) );
		$this->assertFalse( $parser->DoTag( BBCODE_CHECK, 'enhanced', 'Label', array(), 'Invalid' ) );
		$this->assertSame( '<x data-tone="blue">Label:Body</x>', $parser->DoTag( BBCODE_OUTPUT, 'enhanced', 'Label', array( '_default' => 'Label' ), 'Body' ) );
		$this->assertSame( '<x data-tone="blue">Body:Body</x>', $parser->DoTag( BBCODE_OUTPUT, 'enhanced', '', array(), 'Body' ) );

		$parser->SetPlainMode();
		$this->assertSame( '<b>Text</b>', $parser->DoTag( BBCODE_OUTPUT, 'b', '', array(), 'Text' ) );
		$this->assertSame(
			'<a href="https://example.com">Example</a>',
			$parser->DoTag( BBCODE_OUTPUT, 'url', 'https://example.com', array( '_default' => 'https://example.com' ), 'Example' )
		);
		$this->assertSame(
			'<a href="https://example.com">https://example.com</a>',
			$parser->DoTag( BBCODE_OUTPUT, 'url', 'https://example.com', array( '_default' => 'https://example.com' ), '' )
		);
	}

	/**
	 * Missing end tags use the closing marker paired with the configured opener.
	 *
	 * @covers BBCode::Internal_UpdateParamsForMissingEndTag
	 * @ticket BBP3706
	 */
	public function test_missing_end_tag_markers() {
		$parser = BBCode::getInstance();

		foreach (
			array(
				'[' => '[/b]',
				'<' => '</b>',
				'{' => '{/b}',
				'(' => '(/b)',
				'|' => '|/b|',
			) as $marker => $expected
		) {
			$parser->SetTagMarker( $marker );
			$params = $parser->Internal_UpdateParamsForMissingEndTag( array( '_name' => 'b' ) );
			$this->assertSame( $expected, $params['_endtag'] );
		}
	}

	/**
	 * Start and end tokens preserve unknown tags and finish supported tag stacks.
	 *
	 * @covers BBCode::Internal_GenerateOutput
	 * @covers BBCode::Internal_RewindToClass
	 * @covers BBCode::Internal_FinishTag
	 * @covers BBCode::Internal_ProcessIsolatedTag
	 * @covers BBCode::Internal_ParseStartTagToken
	 * @covers BBCode::Internal_ParseEndTagToken
	 * @ticket BBP3706
	 */
	public function test_tag_token_state_transitions() {
		$parser = BBCode::getInstance();
		$parser->stack = array();

		$this->assertTrue( $parser->Internal_RewindToClass( array( 'block' ) ) );
		$this->assertFalse( $parser->Internal_RewindToClass( array( 'list' ) ) );
		$this->assertSame( '[missing]x[/missing]', $this->parse_without_errors( '[missing]x[/missing]', $parser ) );
		$this->assertSame( '[list=invalid]a[/list]', $this->parse_without_errors( '[list=invalid]a[/list]', $parser ) );
		$this->assertSame( '[*]a', $this->parse_without_errors( '[*]a', $parser ) );
		$this->assertSame( '[[!!!]]', $this->parse_without_errors( '[[!!!]]', $parser ) );
		$this->assertSame( "<br />\n", $this->parse_without_errors( '[br]', $parser ) );
		$this->assertSame( "a<br />\nb", $this->parse_without_errors( 'a  [br]  b', $parser ) );
		$this->assertStringContainsString( '<div class="bbcode_code_body" style="white-space:pre">x</div>', $this->parse_without_errors( '[code]x[/code]', $parser ) );
		$this->assertSame( '<b>x</b>', $this->parse_without_errors( '[b]x[/b]', $parser ) );
		$this->assertSame( '<b>x</b>', $this->parse_without_errors( '[b]x', $parser ) );
		$this->assertSame( '<b><b>x</b></b>', $this->parse_without_errors( '[b][b]x[/b][/b]', $parser ) );
		$this->assertSame( '[/b]', $this->parse_without_errors( '[/b]', $parser ) );
		$this->assertSame(
			"x\n<div class=\"bbcode_quote\">\n<div class=\"bbcode_quote_head\">Quote:</div>\n<div class=\"bbcode_quote_body\">q</div>\n</div>\ny",
			$this->parse_without_errors( "x [quote]\n q \n[/quote]\n y", $parser )
		);
		$this->assertSame(
			"<a href=\"https://example.com\" class=\"bbcode_url\"></a>\n<div class=\"bbcode_quote\">\n<div class=\"bbcode_quote_head\">Quote:</div>\n<div class=\"bbcode_quote_body\">q</div>\n</div>\n",
			$this->parse_without_errors( '[url=https://example.com][quote]q[/quote][/url]', $parser )
		);
	}
}
