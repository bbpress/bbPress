<?php

/**
 * Pagination for pages of replies (when viewing a topic)
 *
 * @package bbPress
 * @subpackage Themes
 */

?>

	<div class="bbp-pagination">
		<div class="bbp-pagination-count">

			<?php bbp_topic_pagination_count(); ?>

		</div>

		<div class="bbp-pagination-links">

			<?php bbp_topic_pagination_links(); ?>

		</div>
	</div>
