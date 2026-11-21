<?php

/**
 * Optional translation of forum URL slugs.
 *
 * @package bbPress
 * @subpackage Administration
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the site's language without using the administrator's language.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @return string Site locale.
 */
function bbp_admin_slug_translation_locale() {
	$locale = get_option( 'WPLANG', '' );

	if ( ( ! is_string( $locale ) || empty( $locale ) ) && is_multisite() ) {
		$locale = get_site_option( 'WPLANG', '' );
	}

	return ( is_string( $locale ) && ! empty( $locale ) ) ? $locale : 'en_US';
}

/**
 * Get English and translated defaults for URL slugs.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @return array<string, array{string, string}> Slugs keyed by option name.
 */
function bbp_admin_slug_translation_strings() {
	$locale   = bbp_admin_slug_translation_locale();
	$switched = switch_to_locale( $locale );

	// A failed switch must not turn an administrator's translations into URLs.
	if ( ! $switched && ( determine_locale() !== $locale ) ) {
		return array();
	}

	$strings = array(
		'_bbp_root_slug'          => array( 'forums', _x( 'forums', 'default URL slug', 'bbpress' ) ),
		'_bbp_forum_slug'         => array( 'forum', _x( 'forum', 'default URL slug', 'bbpress' ) ),
		'_bbp_topic_slug'         => array( 'topic', _x( 'topic', 'default URL slug', 'bbpress' ) ),
		'_bbp_reply_slug'         => array( 'reply', _x( 'reply', 'default URL slug', 'bbpress' ) ),
		'_bbp_topic_tag_slug'     => array( 'topic-tag', _x( 'topic-tag', 'default URL slug', 'bbpress' ) ),
		'_bbp_user_slug'          => array( 'users', _x( 'users', 'default URL slug', 'bbpress' ) ),
		'_bbp_user_engs_slug'     => array( 'engagements', _x( 'engagements', 'default URL slug', 'bbpress' ) ),
		'_bbp_user_mentions_slug' => array( 'mentions', _x( 'mentions', 'default URL slug', 'bbpress' ) ),
		'_bbp_user_favs_slug'     => array( 'favorites', _x( 'favorites', 'default URL slug', 'bbpress' ) ),
		'_bbp_user_subs_slug'     => array( 'subscriptions', _x( 'subscriptions', 'default URL slug', 'bbpress' ) ),
		'_bbp_topic_archive_slug' => array( 'topics', _x( 'topics', 'default URL slug', 'bbpress' ) ),
		'_bbp_reply_archive_slug' => array( 'replies', _x( 'replies', 'default URL slug', 'bbpress' ) ),
		'_bbp_view_slug'          => array( 'view', _x( 'view', 'default URL slug', 'bbpress' ) ),
		'_bbp_search_slug'        => array( 'search', _x( 'search', 'default URL slug', 'bbpress' ) ),
		'_bbp_unsubscribe_slug'   => array( 'unsubscribe', _x( 'unsubscribe', 'default URL slug', 'bbpress' ) ),
		'_bbp_edit_slug'          => array( 'edit', _x( 'edit', 'default URL slug', 'bbpress' ) ),
	);

	if ( $switched ) {
		restore_previous_locale();
	}

	return $strings;
}

/**
 * Find safe suggestions without replacing custom slugs.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @param array<string, mixed> $applied Slugs previously applied by this feature.
 * @return array<string, array{current: mixed, new: string}> Suggested slugs keyed by option name.
 */
function bbp_admin_slug_translation_suggestions( $applied = array() ) {
	$suggestions = array();

	foreach ( bbp_admin_slug_translation_strings() as $option => $strings ) {
		$english = $strings[0];
		$current = get_option( $option, $english );
		$slug    = sanitize_title( $strings[1] );

		if ( empty( $slug ) || ( $slug === $current ) ||
			( $current !== $english && ( ! isset( $applied[ $option ] ) || $current !== $applied[ $option ] ) ) ) {
			continue;
		}

		$suggestions[ $option ] = array(
			'current' => $current,
			'new'     => $slug,
		);
	}

	return $suggestions;
}

/**
 * Show every default slug, including values that would stay the same.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @param array<string, array{current: mixed, new: string}> $suggestions Eligible slug changes.
 * @return array<string, array{current: mixed, new: mixed}> Current and resulting slugs keyed by option name.
 */
function bbp_admin_slug_translation_preview( $suggestions ) {
	$preview = array();

	foreach ( bbp_admin_slug_translation_strings() as $option => $strings ) {
		$current = get_option( $option, $strings[0] );
		$preview[ $option ] = array(
			'current' => $current,
			'new'     => isset( $suggestions[ $option ] ) ? $suggestions[ $option ]['new'] : $current,
		);
	}

	return $preview;
}

/**
 * Find suggested slugs that would collide with another known route base.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @param array<string, array{current: mixed, new: string}> $suggestions Suggested slug changes.
 * @return list<string> Option names with conflicting suggestions.
 */
function bbp_admin_slug_translation_conflicts( $suggestions ) {
	$slugs = array();
	foreach ( bbp_admin_slug_translation_strings() as $option => $strings ) {
		$slugs[ $option ] = isset( $suggestions[ $option ] )
			? $suggestions[ $option ]['new']
			: get_option( $option, $strings[0] );
	}

	$tag_base      = get_option( 'tag_base', 'tag' );
	$category_base = get_option( 'category_base', 'category' );
	$reserved      = array(
		'post',
		'page',
		'revision',
		'attachment',
		'nav_menu_item',
		! empty( $tag_base ) ? $tag_base : 'tag',
		! empty( $category_base ) ? $category_base : 'category',
	);
	if ( defined( 'BP_VERSION' ) && ! empty( buddypress()->pages ) ) {
		foreach ( buddypress()->pages as $page ) {
			$reserved[] = $page->slug;
		}
	}
	$conflicts = array();
	foreach ( $suggestions as $option => $values ) {
		$others = $slugs;
		unset( $others[ $option ] );
		if ( in_array( $values['new'], $others, true ) || in_array( $values['new'], $reserved, true ) ) {
			$conflicts[] = $option;
		}
	}

	return $conflicts;
}

/**
 * Get prompt state and reset a one-time dismissal after a language change.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @return array<string, mixed> Prompt state.
 */
function bbp_admin_slug_translation_state() {
	$state  = (array) get_option( '_bbp_slug_translation_prompt', array() );
	$locale = bbp_admin_slug_translation_locale();

	if ( ! isset( $state['locale'] ) || $state['locale'] !== $locale ) {
		$state['locale']    = $locale;
		$state['dismissed'] = false;
		update_option( '_bbp_slug_translation_prompt', $state, false );
	}

	return $state;
}

/**
 * Get suggestions currently eligible for an automatic prompt.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @return array<string, array{current: mixed, new: string}> Suggested slugs.
 */
function bbp_admin_slug_translation_prompt() {
	$state = bbp_admin_slug_translation_state();

	if ( ! empty( $state['disabled'] ) || ! empty( $state['dismissed'] ) ) {
		return array();
	}

	return bbp_admin_slug_translation_suggestions( isset( $state['applied'] ) ? (array) $state['applied'] : array() );
}

/**
 * Get the settings URL for the active integration mode.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @return string Admin URL.
 */
function bbp_admin_slug_translation_url() {
	$page = ( 'basic' === bbp_settings_integration() ) ? 'bbpress' : 'bbpress-slugs';
	return admin_url( 'options-general.php?page=' . $page );
}

/**
 * Get the manual review link for Slugs Help.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @return string Help tab link, or an empty string.
 */
function bbp_admin_slug_translation_help_link() {
	if ( ! current_user_can( 'bbp_settings_page' ) ) {
		return '';
	}

	$state       = bbp_admin_slug_translation_state();
	$suggestions = bbp_admin_slug_translation_suggestions( isset( $state['applied'] ) ? (array) $state['applied'] : array() );
	if ( empty( $suggestions ) ) {
		return '';
	}

	$hidden = ( empty( $state['disabled'] ) && empty( $state['dismissed'] ) ) ? ' hidden' : '';
	return '<p id="bbp-slug-translation-help-link"' . $hidden . '><a href="#bbp-slug-translation">' . esc_html__( 'Review suggested slugs', 'bbpress' ) . '</a></p>';
}

/**
 * Add slug help to the separate page used by deep integration.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_help() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	$screen->add_help_tab(
		array(
			'id'      => 'slugs',
			'title'   => esc_html__( 'Slugs', 'bbpress' ),
			'content' => '<ul><li>' .
				esc_html__( 'When the site language has translated defaults, bbPress can suggest new URL slugs. URLs change only if you apply the suggestions.', 'bbpress' ) .
				'</li></ul>' .
				bbp_admin_slug_translation_help_link(),
		)
	);
}

/**
 * Register the Slugs page when needed and mark pending suggestions in the menu.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_menu() {
	global $submenu;
	$basic = ( 'basic' === bbp_settings_integration() );

	if ( ! $basic ) {
		add_options_page(
			esc_html__( 'Forum Slugs', 'bbpress' ),
			esc_html__( 'Forum Slugs', 'bbpress' ),
			'bbp_settings_page',
			'bbpress-slugs',
			'bbp_admin_slug_translation_page'
		);
	}

	if ( ! current_user_can( 'bbp_settings_page' ) || ! bbp_admin_slug_translation_prompt() ) {
		return;
	}

	if ( isset( $submenu['options-general.php'] ) ) {
		foreach ( $submenu['options-general.php'] as &$item ) {
			if ( ( $basic ? 'bbpress' : 'bbpress-slugs' ) === $item[2] ) {
				$item[0] .= ' <span class="awaiting-mod count-1 bbp-slug-translation-count">' .
					'<span class="pending-count" aria-hidden="true">1</span>' .
					'<span class="screen-reader-text">' . esc_html__( 'Forum slug suggestions available', 'bbpress' ) . '</span>' .
					'</span>';
				break;
			}
		}
		unset( $item );
	}
}

/**
 * Point administrators to the review on the Forums settings page.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_notice() {
	if ( ! current_user_can( 'bbp_settings_page' ) || ! bbp_admin_slug_translation_prompt() ) {
		return;
	}

	$screen = get_current_screen();
	if ( $screen && in_array( $screen->id, array( 'settings_page_bbpress', 'settings_page_bbpress-slugs' ), true ) ) {
		return;
	}

	$url = bbp_admin_slug_translation_url() . '#bbp-slug-translation';
	?>
	<div class="notice notice-info bbp-slug-translation-notice"><p>
		<?php esc_html_e( 'Translations are available for your forum URL slugs. Existing URLs will stay the same unless you choose to update them.', 'bbpress' ); ?>
		<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Review suggested slugs', 'bbpress' ); ?></a>
	</p></div>
	<?php
}

/**
 * Show the review when bbPress settings use deep integration.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Forum Slugs', 'bbpress' ); ?></h1>
		<?php bbp_admin_slug_translation_panel(); ?>
	</div>
	<?php
}

/**
 * Show the slug preview and choices above the Forums settings form.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_panel() {
	if ( ! current_user_can( 'bbp_settings_page' ) ) {
		return;
	}

	$state       = bbp_admin_slug_translation_state();
	$suggestions = bbp_admin_slug_translation_suggestions( isset( $state['applied'] ) ? (array) $state['applied'] : array() );
	if ( empty( $suggestions ) ) {
		return;
	}
	$proposal_json = wp_json_encode( $suggestions );
	if ( false === $proposal_json ) {
		return;
	}
	$preview   = bbp_admin_slug_translation_preview( $suggestions );
	$conflicts = bbp_admin_slug_translation_conflicts( $suggestions );
	$prompt    = bbp_admin_slug_translation_prompt();
	$labels    = array(
		'_bbp_user_mentions_slug' => esc_html__( 'Mentions', 'bbpress' ),
	);
	foreach ( bbp_admin_get_settings_fields() as $section ) {
		foreach ( $section as $option => $field ) {
			if ( isset( $field['title'] ) ) {
				$labels[ $option ] = $field['title'];
			}
		}
	}
	?>
	<?php if ( $prompt ) : ?>
		<div class="notice notice-info inline bbp-slug-translation-notice"><p>
			<?php esc_html_e( 'Translations are available for your forum URL slugs. Existing URLs will stay the same unless you choose to update them.', 'bbpress' ); ?>
			<a href="#bbp-slug-translation"><?php esc_html_e( 'Review suggested slugs', 'bbpress' ); ?></a>
		</p></div>
	<?php endif; ?>
	<dialog id="bbp-slug-translation" class="bbp-slug-translation-dialog" aria-labelledby="bbp-slug-translation-title" aria-describedby="bbp-slug-translation-description">
		<h2 id="bbp-slug-translation-title" tabindex="-1"><?php esc_html_e( 'Suggested forum URL slugs', 'bbpress' ); ?></h2>
		<p id="bbp-slug-translation-description"><?php esc_html_e( 'Applying these suggestions changes forum URLs. Existing links may need redirects. Custom slugs are excluded.', 'bbpress' ); ?></p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="bbp_slug_translation" />
			<input type="hidden" name="proposal" value="<?php echo esc_attr( md5( $proposal_json ) ); ?>" />
			<?php wp_nonce_field( 'bbp_slug_translation' ); ?>
			<div class="bbp-slug-translation-table">
				<?php if ( $conflicts ) : ?>
					<p class="notice notice-error"><?php esc_html_e( 'Some suggestions conflict with existing slugs. Change those slugs in settings before applying this set.', 'bbpress' ); ?></p>
				<?php endif; ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Setting', 'bbpress' ); ?></th>
							<th><?php esc_html_e( 'Current slug', 'bbpress' ); ?></th>
							<th><?php esc_html_e( 'After update', 'bbpress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $preview as $option => $values ) : ?>
							<tr>
								<td><?php echo esc_html( isset( $labels[ $option ] ) ? $labels[ $option ] : $option ); ?></td>
								<td><code><?php echo esc_html( $values['current'] ); ?></code></td>
								<td>
									<code><?php echo esc_html( $values['new'] ); ?></code>
									<?php if ( $values['current'] === $values['new'] ) : ?>
										<span class="description"><?php esc_html_e( 'Unchanged', 'bbpress' ); ?></span>
									<?php endif; ?>
									<?php if ( in_array( $option, $conflicts, true ) ) : ?>
										<strong><?php esc_html_e( 'Conflict', 'bbpress' ); ?></strong>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="bbp-slug-translation-actions">
				<?php if ( ! $conflicts ) : ?>
					<button type="submit" class="button button-primary" name="decision" value="yes"><?php esc_html_e( 'Apply suggested slugs', 'bbpress' ); ?></button>
				<?php endif; ?>
				<button type="submit" class="button" name="decision" value="no"><?php esc_html_e( 'Keep current slugs', 'bbpress' ); ?></button>
				<button type="submit" class="button" name="decision" value="never"><?php esc_html_e( 'Don’t ask again', 'bbpress' ); ?></button>
				<button type="submit" class="button bbp-slug-translation-cancel" name="decision" value="cancel"><?php esc_html_e( 'Cancel', 'bbpress' ); ?></button>
			</p>
		</form>
	</dialog>
	<?php
}

/**
 * Save a reviewed set of slug suggestions, restoring prior values on failure.
 *
 * @since 2.7.0 bbPress (r8405)
 *
 * @param array<string, array{current: mixed, new: string}> $suggestions Suggested slug changes.
 * @return bool Whether every slug was saved.
 */
function bbp_admin_apply_slug_translations( $suggestions ) {
	if ( empty( $suggestions ) || bbp_admin_slug_translation_conflicts( $suggestions ) ) {
		return false;
	}

	$originals = array();
	foreach ( $suggestions as $option => $values ) {
		$missing = new stdClass();
		$originals[ $option ] = get_option( $option, $missing );
		if ( ! update_option( $option, $values['new'] ) ) {
			foreach ( $originals as $restore_option => $original ) {
				if ( $original instanceof stdClass ) {
					delete_option( $restore_option );
				} else {
					update_option( $restore_option, $original );
				}
			}
			return false;
		}
	}

	flush_rewrite_rules();
	return true;
}

/**
 * Handle the administrator's decision about slug translations.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_handler() {
	if ( ! current_user_can( 'bbp_settings_page' ) ) {
		wp_die( esc_html__( 'You cannot change forum settings.', 'bbpress' ) );
	}

	check_admin_referer( 'bbp_slug_translation' );
	$decision = isset( $_POST['decision'] ) && is_string( $_POST['decision'] )
		? sanitize_key( wp_unslash( $_POST['decision'] ) )
		: '';
	$state    = bbp_admin_slug_translation_state();

	if ( 'never' === $decision ) {
		$state['disabled'] = true;
	} elseif ( 'no' === $decision || 'cancel' === $decision ) {
		$state['dismissed'] = true;
	} elseif ( 'yes' === $decision ) {
		$suggestions = bbp_admin_slug_translation_suggestions( isset( $state['applied'] ) ? (array) $state['applied'] : array() );
		$proposal    = isset( $_POST['proposal'] ) && is_string( $_POST['proposal'] )
			? sanitize_text_field( wp_unslash( $_POST['proposal'] ) )
			: '';
		$proposal_json = wp_json_encode( $suggestions );
		if ( empty( $suggestions ) || false === $proposal_json || ! hash_equals( md5( $proposal_json ), $proposal ) ) {
			wp_die( esc_html__( 'The suggested slugs changed. Review them again before updating.', 'bbpress' ) );
		}
		if ( ! bbp_admin_apply_slug_translations( $suggestions ) ) {
			wp_die( esc_html__( 'The suggested slugs could not be saved. Review the current settings before trying again.', 'bbpress' ) );
		}
		foreach ( $suggestions as $option => $values ) {
			$state['applied'][ $option ] = $values['new'];
		}
		$state['dismissed'] = true;
	} else {
		wp_die( esc_html__( 'Unknown forum slug choice.', 'bbpress' ) );
	}

	update_option( '_bbp_slug_translation_prompt', $state, false );
	wp_safe_redirect( bbp_admin_slug_translation_url() );
	exit;
}

/**
 * Dismiss slug suggestions without navigating away from the settings page.
 *
 * @since 2.7.0 bbPress (r8405)
 * @return void
 */
function bbp_admin_slug_translation_cancel_ajax() {
	if ( ! current_user_can( 'bbp_settings_page' ) ) {
		wp_send_json_error( null, 403 );
	}

	check_ajax_referer( 'bbp_slug_translation' );
	$state              = bbp_admin_slug_translation_state();
	$state['dismissed'] = true;
	update_option( '_bbp_slug_translation_prompt', $state, false );
	wp_send_json_success();
}
