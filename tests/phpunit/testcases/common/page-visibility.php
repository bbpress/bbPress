<?php

/**
 * Page lookup for forum and topic archive content.
 *
 * @group common
 * @group visibility
 */
class BBP_Tests_Common_Page_Visibility extends BBP_UnitTestCase {

	/**
	 * @covers ::bbp_get_page_by_path
	 */
	public function test_archive_page_lookup_excludes_nonpublic_pages_and_attachments() {
		global $wp_rewrite;

		$old_permalinks = $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$slug = 'private-forum-archive-marker';
		$page = $this->factory->post->create( array(
			'post_type'   => 'page',
			'post_status' => 'private',
			'post_name'   => $slug,
		) );

		try {
			$this->assertFalse( bbp_get_page_by_path( $slug ) );

			wp_update_post( array( 'ID' => $page, 'post_status' => 'publish', 'post_password' => 'secret' ) );
			$this->assertFalse( bbp_get_page_by_path( $slug ) );

			wp_update_post( array( 'ID' => $page, 'post_password' => '' ) );
			$this->assertSame( $page, bbp_get_page_by_path( $slug )->ID );

			wp_delete_post( $page, true );
			$this->factory->post->create( array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'post_name'   => $slug,
			) );
			$this->assertFalse( bbp_get_page_by_path( $slug ) );
		} finally {
			$wp_rewrite->set_permalink_structure( $old_permalinks );
		}
	}
}
