<?php
/**
 * Pagination for pages of topics (when viewing a forum)
 *
 * @package bbPress
 * @subpackage Twenty Ten
 */
?>

	<div class="bbp-pagination">
		<div class="bbp-pagination-count">

			<?php bbp_forum_pagination_count(); ?>

		</div>

		<div class="bbp-pagination-links">

			<?php bbp_forum_pagination_links(); ?>

		</div>
	</div>
