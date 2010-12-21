<?php

/**
 * @todo
 *
 * phpDoc
 * Improve separation of HTML and PHP
 * Add filters where applicable
 * Code clean-up
 */

class BBP_Forums_Widget extends WP_Widget {

	function BBP_Forums_Widget() {
		$widget_ops = array(
			'classname'   => 'widget_display_forums',
			'description' => __( 'A list of forums.', 'bbpress' )
		);

		parent::WP_Widget( false, $name = 'bbPress Forum List', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title        = apply_filters( 'widget_title', $instance['title'] );
		$parent_forum = !empty( $instance['parent_forum'] ) ? $instance['parent_forum'] : '';

		$default = array(
			'post_parent'    => $parent_forum,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC'
		);

		echo $before_widget;
		echo $before_title . $title . $after_title;

		if ( bbp_has_forums( $default ) ) :
			echo "<ul>";
			while ( bbp_forums() ) : bbp_the_forum();
?>

				<li><a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>"><?php bbp_forum_title(); ?></a></li>

<?php
				endwhile;
				echo "</ul>";
			endif;
			echo $after_widget;
		}

		function update( $new_instance, $old_instance ) {
			$instance                 = $old_instance;
			$instance['title']        = strip_tags( $new_instance['title']        );
			$instance['parent_forum'] = strip_tags( $new_instance['parent_forum'] );
			return $instance;
		}

		function form( $instance ) {
			$title        = !empty( $instance['title'] )        ? esc_attr( $instance['title'] ) : '';
			$parent_forum = !empty( $instance['parent_forum'] ) ? esc_attr( $instance['parent_forum'] ) : ''; ?>

			<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
			<p>
				<label for="<?php echo $this->get_field_id( 'parent_forum' ); ?>"><?php _e( 'Parent forum:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'parent_forum' ); ?>" name="<?php echo $this->get_field_name( 'parent_forum' ); ?>" type="text" value="<?php echo $parent_forum; ?>" /></label>
				<br />
				<small><?php _e( 'Forum ID number. Blank to display all top level forums, "null" to display all forums.', 'bbpress' ); ?></small>
			</p>
<?php
		}

	}

class BBP_Topics_Widget extends WP_Widget {

	function BBP_Topics_Widget() {
		$widget_ops = array(
			'classname'   => 'widget_display_topics',
			'description' => __( 'A list of recent topics, sorted by popularity or freshness.', 'bbpress' )
		);
		parent::WP_Widget( false, $name = 'bbPress Topics List', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title        = apply_filters( 'widget_title', $instance['title'] );
		$max_shown    = !empty( $instance['max_shown']    ) ? $instance['max_shown']    : '5';
		$show_date    = !empty( $instance['show_date']    ) ? 'on'                      : false;
		$parent_forum = !empty( $instance['parent_forum'] ) ? $instance['parent_forum'] : '';
		$pop_check    = ( $instance['pop_check'] < $max_shown || empty( $instance['pop_check'] ) ) ? -1 : $instance['pop_check'];

		$default = array(
			'post_parent'    => $parent_forum,
			'posts_per_page' => $max_shown > $pop_check ? $max_shown : $pop_check,
			'orderby'        => 'modified',
			'order'          => 'DESC'
		);

		echo $before_widget;
		echo $before_title . $title . $after_title;

		if ( $pop_check < $max_shown && bbp_has_topics( $default ) ) :
			echo "<ul>";
			while ( bbp_topics() ) : bbp_the_topic();
?>

				<li><a class="bbp-forum-title" href="<?php bbp_topic_permalink(); ?>" title="<?php bbp_topic_title(); ?>"><?php bbp_topic_title(); ?></a><?php if ( $show_date == 'on' )
					_e( ', ' . bbp_get_topic_last_active() . ' ago' ); ?></li>

<?php
			endwhile;
			echo "</ul>";
		endif;

		if ( $pop_check >= $max_shown && bbp_has_topics( $default ) ) :
			echo "<ul>";
			while ( bbp_topics () ) : bbp_the_topic();
				$topics[bbp_get_topic_id()] = bbp_get_topic_reply_count();
			endwhile;
			arsort( $topics );
			$topic_count = 1;
			foreach ( $topics as $topic_id => $topic_reply_count ) {
?>

				<li><a class="bbp-forum-title" href="<?php bbp_topic_permalink( $topic_id ); ?>" title="<?php bbp_topic_title( $topic_id ); ?>"><?php bbp_topic_title( $topic_id ); ?></a><?php if ( $show_date == 'on' )
					_e( ', ' . bbp_get_topic_last_active( $topic_id ) . ' ago' ); ?></li>

<?php
				$topic_count++;
				if ( $topic_count > $max_shown ) {
					break;
				}
			}
			echo "</ul>";
		endif;
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance              = $old_instance;
		$instance['title']     = strip_tags( $new_instance['title']     );
		$instance['max_shown'] = strip_tags( $new_instance['max_shown'] );
		$instance['show_date'] = strip_tags( $new_instance['show_date'] );
		$instance['pop_check'] = strip_tags( $new_instance['pop_check'] );
		return $instance;
	}

	function form( $instance ) {
		$title     = !empty( $instance['title'] )     ? esc_attr( $instance['title']     ) : '';
		$max_shown = !empty( $instance['max_shown'] ) ? esc_attr( $instance['max_shown'] ) : '';
		$show_date = !empty( $instance['show_date'] ) ? esc_attr( $instance['show_date'] ) : '';
		$pop_check = !empty( $instance['pop_check'] ) ? esc_attr( $instance['pop_check'] ) : '';
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id( 'max_shown' ); ?>"><?php _e( 'Maximum topics to show:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'max_shown' ); ?>" name="<?php echo $this->get_field_name( 'max_shown' ); ?>" type="text" value="<?php echo $max_shown; ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Show post date:', 'bbpress' ); ?> <input type="checkbox" id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" <?php echo ($show_date == 'on') ? 'checked="checked"' : ''; ?>/></label></p>
		<p>
			<label for="<?php echo $this->get_field_id( 'pop_check' ); ?>"><?php _e( 'Popularity check:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'pop_check' ); ?>" name="<?php echo $this->get_field_name( 'pop_check' ); ?>" type="text" value="<?php echo $pop_check; ?>" /></label>
			<br /><small><?php _e( 'Number of topics back to check reply count to determine popularity. A number less than the maximum number of topics to show disables the check.', 'bbpress' ); ?></small>
		</p>

<?php
	}
}

class BBP_Replies_Widget extends WP_Widget {

	function BBP_Replies_Widget() {
		$widget_ops = array(
			'classname'   => 'widget_display_replies',
			'description' => __( 'A list of bbPress recent replies.', 'bbpress' )
		);
		parent::WP_Widget( false, $name = 'bbPress Reply List', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title     = apply_filters( 'widget_title', $instance['title'] );
		$max_shown = !empty( $instance['max_shown'] ) ? $instance['max_shown'] : '5';
		$show_date = !empty( $instance['show_date'] ) ? 'on'                   : false;

		$default = array(
			'post_parent'    => null,
			'posts_per_page' => $max_shown,
			'orderby'        => 'modified',
			'order'          => 'DESC'
		);

		echo $before_widget;
		echo $before_title . $title . $after_title;

		if ( bbp_has_replies( $default ) ) :
			echo "<ul>";

			while ( bbp_replies() ) : bbp_the_reply();
?>

				<li>
					<a class="bbp-forum-title" href="<?php bbp_reply_permalink(); ?>" title="<?php bbp_reply_title(); ?>"><?php bbp_reply_title(); ?></a>

					<?php if ( $show_date == 'on' ) _e( ', ' . get_the_date() . ', ' . get_the_time() ); ?>

				</li>

<?php
			endwhile;
			echo "</ul>";
		endif;

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance              = $old_instance;
		$instance['title']     = strip_tags( $new_instance['title']     );
		$instance['max_shown'] = strip_tags( $new_instance['max_shown'] );
		$instance['show_date'] = strip_tags( $new_instance['show_date'] );
		return $instance;
	}

	function form( $instance ) {
		$title     = !empty( $instance['title']     ) ? esc_attr( $instance['title']     ) : '';
		$max_shown = !empty( $instance['max_shown'] ) ? esc_attr( $instance['max_shown'] ) : '';
		$show_date = !empty( $instance['show_date'] ) ? esc_attr( $instance['show_date'] ) : '';
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id( 'max_shown' ); ?>"><?php _e( 'Maximum replies to show:', 'bbpress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'max_shown' ); ?>" name="<?php echo $this->get_field_name( 'max_shown' ); ?>" type="text" value="<?php echo $max_shown; ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Show post date:', 'bbpress' ); ?> <input type="checkbox" id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" <?php echo ($show_date == 'on') ? 'checked="checked"' : ''; ?>/></label></p>

<?php
	}

}

add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Forums_Widget");'  ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Topics_Widget");'  ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("BBP_Replies_Widget");' ) );

?>
