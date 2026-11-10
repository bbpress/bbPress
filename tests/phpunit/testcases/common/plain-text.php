<?php

/**
 * @group common
 * @group formatting
 * @group bbp_make_plain_text
 */
class BBP_Tests_Common_Formatting_Plain_Text extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_make_plain_text
	 */
	public function test_strips_tags_before_decoding_entities() {
		$this->assertSame(
			'Bold <em>literal</em>',
			bbp_make_plain_text( '<strong>Bold</strong> &lt;em&gt;literal&lt;/em&gt;' )
		);
	}

	/**
	 * @covers ::bbp_make_plain_text
	 */
	public function test_decodes_quotes_and_trims_outer_spacing() {
		$this->assertSame(
			'Alice  & Bob "quoted" \'yes\'',
			bbp_make_plain_text( '  Alice  &amp; Bob &quot;quoted&quot; &#039;yes&#039;  ' )
		);
	}
}
