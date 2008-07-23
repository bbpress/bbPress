<?php
require_once('admin.php');

if ($_POST['action'] == 'update') {
	
	bb_check_admin_referer( 'options-general-update' );
	
	// Deal with avatars checkbox when it isn't checked
	if (!isset($_POST['avatars_show'])) {
		$_POST['avatars_show'] = false;
	}
	
	foreach ( (array) $_POST as $option => $value ) {
		if ( !in_array( $option, array('_wpnonce', '_wp_http_referer', 'action', 'submit') ) ) {
			$option = trim( $option );
			$value = is_array( $value ) ? $value : trim( $value );
			$value = stripslashes_deep( $value );
			if ($option == 'uri' && !empty($value)) {
				$value = rtrim($value) . '/';
			}
			if ( $value ) {
				bb_update_option( $option, $value );
			} else {
				bb_delete_option( $option );
			}
		}
	}
	
	$goback = add_query_arg('updated', 'true', wp_get_referer());
	bb_safe_redirect($goback);
	
}

if ($_GET['updated']) {
	bb_admin_notice( __('Settings saved.') );
}

bb_get_admin_header();
?>

<div class="wrap">

<h2><?php _e('General Settings'); ?></h2>

<form class="settings" method="post" action="<?php bb_uri('bb-admin/options-general.php', null, BB_URI_CONTEXT_FORM_ACTION + BB_URI_CONTEXT_BB_ADMIN); ?>">
	<fieldset>
		<div>
			<label for="name">
				<?php _e('Site title'); ?>
			</label>
			<div>
				<input class="text long" name="name" id="name" value="<?php bb_form_option('name'); ?>" />
			</div>
		</div>
		<div>
			<label for="description">
				<?php _e('Site description'); ?>
			</label>
			<div>
				<input class="text long" name="description" id="description" value="<?php bb_form_option('description'); ?>" />
			</div>
		</div>
		<div>
			<label for="uri">
				<?php _e('bbPress address (URL)'); ?>
			</label>
			<div>
				<input class="text long" name="uri" id="uri" value="<?php bb_form_option('uri'); ?>" />
				<p><?php _e('The full URL of your bbPress install.'); ?></p>
			</div>
		</div>
		<div>
			<label for="from_email">
				<?php _e('E-mail address') ?>
			</label>
			<div>
				<input class="text" name="from_email" id="from_email" value="<?php bb_form_option('from_email'); ?>" />
				<p><?php _e('Emails sent by the site will appear to come from this address.'); ?></p>
			</div>
		</div>
		<div>
			<label for="mod_rewrite">
				<?php _e('Pretty permalink type') ?>
			</label>
			<div>
				<select name="mod_rewrite" id="mod_rewrite">
<?php
$selected = array();
$selected[bb_get_option('mod_rewrite')] = ' selected="selected"';
?>
					<option value="0"<?php echo $selected[0]; ?>><?php _e('None'); ?>&nbsp;&nbsp;&nbsp;.../forums.php?id=1</option>
					<option value="1"<?php echo $selected[1]; ?>><?php _e('Numeric'); ?>&nbsp;&nbsp;&nbsp;.../forums/1</option>
					<option value="slugs"<?php echo $selected['slugs']; ?>><?php _e('Name based'); ?>&nbsp;&nbsp;&nbsp;.../forums/first-forum</option>
<?php
unset($selected);
?>
				</select>
				<p><?php printf(__('If you activate "Numeric" or "Name based" permalinks, you will need to create a file at <code>%s</code> containing the url rewriting rules <a href="%s">provided here</a>.'), BB_PATH . '.htaccess', bb_get_uri('bb-admin/rewrite-rules.php', null, BB_URI_CONTEXT_A_HREF + BB_URI_CONTEXT_BB_ADMIN)); ?></p>
			</div>
		</div>
		<div>
			<label for="page_topics">
				<?php _e('Items per page') ?>
			</label>
			<div>
				<input class="text short" name="page_topics" id="page_topics" value="<?php bb_form_option('page_topics'); ?>" />
				<p><?php _e('Number of topics, posts or tags to show per page.') ?></p>
			</div>
		</div>
		<div>
			<label for="edit_lock">
				<?php _e('Lock post editing after') ?>
			</label>
			<div>
				<input class="text short" name="edit_lock" id="edit_lock" value="<?php bb_form_option('edit_lock'); ?>" />
				<?php _e('minutes') ?>
				<p><?php _e('A user can edit a post for this many minutes after submitting.'); ?></p>
			</div>
		</div>
	</fieldset>
	<fieldset>
		<legend><?php _e('Date and Time') ?></legend>
		<div>
			<label>
				<?php _e('<abbr title="Coordinated Universal Time">UTC</abbr> time is') ?>
			</label>
			<div>
				<p><?php echo gmdate(__('Y-m-d g:i:s a')); ?></p>
			</div>
		</div>
		<div>
			<label for="gmt_offset">
				<?php _e('Times should differ<br />from UTC by') ?>
			</label>
			<div>
				<input class="text short" name="gmt_offset" id="gmt_offset" value="<?php bb_form_option('gmt_offset'); ?>" />
				<?php _e('hours') ?>
				<p><?php _e('Example: -7 for Pacific Daylight Time.'); ?></p>
			</div>
		</div>
		<div>
			<label for="datetime_format">
				<?php _e('Date and time format') ?>
			</label>
			<div>
				<input class="text" name="datetime_format" id="datetime_format" value="<?php echo(attribute_escape(bb_get_datetime_formatstring_i18n())); ?>" />
				<p><?php printf(__('Output: <strong>%s</strong>'), bb_datetime_format_i18n( bb_current_time() )); ?></p>
			</div>
		</div>
		<div>
			<label for="date_format">
				<?php _e('Date format') ?>
			</label>
			<div>
				<input class="text" name="date_format" id="date_format" value="<?php echo(attribute_escape(bb_get_datetime_formatstring_i18n('date'))); ?>" />
				<p><?php printf(__('Output: <strong>%s</strong>'), bb_datetime_format_i18n( bb_current_time(), 'date' )); ?></p>
				<p><?php _e('Click "Update settings" to update sample output.') ?></p>
				<p><?php _e('<a href="http://codex.wordpress.org/Formatting_Date_and_Time">Documentation on date formatting</a>.'); ?></p>
			</div>
		</div>
	</fieldset>
	<fieldset>
		<legend><?php _e('Avatars'); ?></legend>
		<p>
			<?php _e('bbPress includes built-in support for <a href="http://gravatar.com/">Gravatars</a>, you can enable this feature here.'); ?>
		</p>
		<div>
			<label for="avatars_show">
				<?php _e('Show avatars') ?>
			</label>
			<div>
<?php
$checked = array();
$checked[bb_get_option('avatars_show')] = ' checked="checked"';
?>
				<input type="checkbox" class="checkbox" name="avatars_show" id="avatars_show" value="1"<?php echo $checked[1]; ?> />
<?php
unset($checked);
?>
			</div>
		</div>
		<div>
			<label for="avatars_default">
				<?php _e('Gravatar default image'); ?>
			</label>
			<div>
				<select name="avatars_default" id="avatars_default">
<?php
$selected = array();
$selected[bb_get_option('avatars_default')] = ' selected="selected"';
?>
					<option value="default"<?php echo $selected['default']; ?>><?php _e('Default'); ?></option>
					<option value="logo"<?php echo $selected['logo']; ?>><?php _e('Gravatar Logo'); ?></option>
					<option value="monsterid"<?php echo $selected['monsterid']; ?>><?php _e('MonsterID'); ?></option>
					<option value="wavatar"<?php echo $selected['wavatar']; ?>><?php _e('Wavatar'); ?></option>
					<option value="identicon"<?php echo $selected['identicon']; ?>><?php _e('Identicon'); ?></option>
<?php
unset($selected);
?>
				</select>
				<p>Select what style of avatar to display to users without a Gravatar</p>
				<p class="gravatarDefault">
					<?php echo bb_get_avatar( 'anotherexample', 30, 'default' ); ?><?php _e('Default'); ?>
				</p>
				<p class="gravatarDefault">
					<?php echo bb_get_avatar( 'anotherexample', 30, 'logo' ); ?><?php _e('Gravatar Logo'); ?>
				</p>
				<p class="gravatarDefault">
					<?php echo bb_get_avatar( 'anotherexample', 30, 'monsterid' ); ?><?php _e('MonsterID'); ?>
				</p>
				<p class="gravatarDefault">
					<?php echo bb_get_avatar( 'anotherexample', 30, 'wavatar' ); ?><?php _e('Wavatar'); ?>
				</p>
				<p class="gravatarDefault">
					<?php echo bb_get_avatar( 'anotherexample', 30, 'identicon' ); ?><?php _e('Identicon'); ?>
				</p>
			</div>
		</div>
		<div>
			<label for="avatars_rating">
				<?php _e('Gravatar maximum rating'); ?>
			</label>
			<div>
				<select name="avatars_rating" id="avatars_rating">
<?php
$selected = array();
$selected[bb_get_option('avatars_rating')] = ' selected="selected"';
?>
					<option value="0"<?php echo $selected[0]; ?>><?php _e('None'); ?></option>
					<option value="x"<?php echo $selected['x']; ?>><?php _e('X'); ?></option>
					<option value="r"<?php echo $selected['r']; ?>><?php _e('R'); ?></option>
					<option value="pg"<?php echo $selected['pg']; ?>><?php _e('PG'); ?></option>
					<option value="g"<?php echo $selected['g']; ?>><?php _e('G'); ?></option>
<?php
unset($selected);
?>
				</select>
				<p class="gravatarRating">
					<img src="http://site.gravatar.com/images/gravatars/ratings/3.gif" alt="Rated X" />
					<?php _e('X rated gravatars may contain hardcore sexual imagery or extremely disturbing violence.'); ?>
				</p>
				<p class="gravatarRating">
					<img src="http://site.gravatar.com/images/gravatars/ratings/2.gif" alt="Rated R" />
					<?php _e('R rated gravatars may contain such things as harsh profanity, intense violence, nudity, or hard drug use.'); ?>
				</p>
				<p class="gravatarRating">
					<img src="http://site.gravatar.com/images/gravatars/ratings/1.gif" alt="Rated PG" />
					<?php _e('PG rated gravatars may contain rude gestures, provocatively dressed individuals, the lesser swear words, or mild violence.'); ?>
				</p>
				<p class="gravatarRating">
					<img src="http://site.gravatar.com/images/gravatars/ratings/0.gif" alt="Rated G" />
					<?php _e('A G rated gravatar is suitable for display on all websites with any audience type.'); ?>
				</p>
			</div>
		</div>
	</fieldset>
	<fieldset class="submit">
		<?php bb_nonce_field( 'options-general-update' ); ?>
		<input type="hidden" name="action" value="update" />
		<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</fieldset>
</form>

</div>

<?php
bb_get_admin_footer();
?>
