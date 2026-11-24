<?php

/**
 * Archive Topic Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php bbp_breadcrumb(); ?>

	<?php if ( bbp_is_topic_tag() ) : ?>

		<?php bbp_topic_tag_subscription_link(); ?>

		<?php bbp_get_template_part( 'form', 'topic-search' ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'form', 'search' ); ?>

	<?php endif; ?>

	<?php
	/**
	 * Fires before the topic tag description template section.
	 *
	 * @since 2.6.0 bbPress (r5310)
	 */
	do_action( 'bbp_template_before_topic_tag_description' ); ?>

	<?php if ( bbp_is_topic_tag() ) : ?>

		<?php bbp_topic_tag_description(
			array(
				'before' => '<div class="bbp-template-notice info"><ul><li>',
				'after'  => '</li></ul></div>'
			)
		); ?>

	<?php endif; ?>

	<?php
	/**
	 * Fires after the topic tag description template section.
	 *
	 * @since 2.6.0 bbPress (r5310)
	 */
	do_action( 'bbp_template_after_topic_tag_description' ); ?>

	<?php
	/**
	 * Fires before the topics index template section.
	 *
	 * @since 2.0.0 bbPress (r3115)
	 */
	do_action( 'bbp_template_before_topics_index' ); ?>

	<?php if ( bbp_has_topics() ) : ?>

		<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

		<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

		<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

	<?php endif; ?>

	<?php
	/**
	 * Fires after the topics index template section.
	 *
	 * @since 2.0.0 bbPress (r3115)
	 */
	do_action( 'bbp_template_after_topics_index' ); ?>

</div>
