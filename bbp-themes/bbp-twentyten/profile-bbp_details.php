
				<?php if ( have_posts() ) the_post(); ?>

				<span class="page-title author"><?php printf( __( 'Profile: %s', 'twentyten' ), "<span class='vcard'><a class='url fn n' href='" . get_author_posts_url( get_the_author_meta( 'ID' ) ) . "' title='" . esc_attr( get_the_author() ) . "' rel='me'>" . get_the_author() . "</a></span>" ); ?></span>

				<div id="entry-author-info">
					<div id="author-avatar">

						<?php echo get_avatar( get_the_author_meta( 'user_email' ), apply_filters( 'twentyten_author_bio_avatar_size', 60 ) ); ?>

					</div><!-- #author-avatar -->
					<div id="author-description">
						<h1><?php printf( __( 'About %s', 'twentyten' ), get_the_author() ); ?></h1>

						<?php the_author_meta( 'description' ); ?>

					</div><!-- #author-description	-->
				</div><!-- #entry-author-info -->
