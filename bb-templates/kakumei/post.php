		<div class="threadauthor">
			<?php post_author_avatar(); ?>
			<p>
				<strong><?php post_author_link(); ?></strong><br />
				<small><?php post_author_title(); ?></small>
			</p>
		</div>
		
		<div<?php alt_class('post', 'threadpost ' . $del_class); ?>>
			<div class="post"><?php post_text(); ?></div>
			<div class="poststuff"><?php printf( __('Posted %s ago'), bb_get_post_time() ); ?> <a href="<?php post_anchor_link(); ?>">#</a> <?php post_ip_link(); ?> <?php post_edit_link(); ?> <?php post_delete_link(); ?></div>
		</div>
