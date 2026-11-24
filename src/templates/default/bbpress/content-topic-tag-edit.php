<?php

/**
 * Topic Tag Edit Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php bbp_breadcrumb(); ?>

	<?php
	/** This action is documented in src/templates/default/bbpress/content-archive-topic.php. */
	do_action( 'bbp_template_before_topic_tag_description' ); ?>

	<?php bbp_topic_tag_description(
		array(
			'before' => '<div class="bbp-template-notice info"><ul><li>',
			'after'  => '</li></ul></div>'
		)
	); ?>

	<?php
	/** This action is documented in src/templates/default/bbpress/content-archive-topic.php. */
	do_action( 'bbp_template_after_topic_tag_description' ); ?>

	<?php
	/**
	 * Fires before the topic tag edit template section.
	 *
	 * @since 2.0.0 bbPress (r3348)
	 */
	do_action( 'bbp_template_before_topic_tag_edit' ); ?>

	<?php bbp_get_template_part( 'form', 'topic-tag' ); ?>

	<?php
	/**
	 * Fires after the topic tag edit template section.
	 *
	 * @since 2.0.0 bbPress (r3348)
	 */
	do_action( 'bbp_template_after_topic_tag_edit' ); ?>

</div>
