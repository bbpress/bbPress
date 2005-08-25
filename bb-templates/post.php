		<div class="threadauthor">
			<p><strong><?php post_author_link(); ?></strong><br />
			  <small><?php post_author_type(); ?> <?php user_flag_link( $bb_post->poster_id ); ?></small></p>
		</div>
		
		<div class="threadpost">
			<div class="post"><?php post_text(); ?></div>
			<div class="poststuff">Posted: <?php bb_post_time(); ?> <a href="<?php post_anchor_link(); ?>">#</a> <?php post_ip_link(); ?> <?php post_edit_link(); ?> <?php post_delete_link(); ?></div>
		</div>
