<?php
require_once('admin.php');

if ($_POST['action'] == 'update') {
	
	bb_check_admin_referer( 'options-general-update' );
	
	foreach ( (array) $_POST as $option => $value ) {
		if ( !in_array( $option, array('_wpnonce', '_wp_http_referer', 'action', 'submit') ) ) {
			$option = trim( $option );
			$value = is_array( $value ) ? $value : trim( $value );
			$value = stripslashes_deep( $value );
			if ($option == 'uri') {
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
	wp_redirect($goback);
	
}

if ($_GET['updated']) {
	bb_admin_notice( __('Options saved.') );
}

bb_get_admin_header();
?>

<h2><?php _e('General Options'); ?></h2>

<form class="options" method="post" action="<?php bb_option('uri'); ?>bb-admin/options-general.php">
	<fieldset>
		<label for="name">
			<?php _e('Site title:'); ?>
		</label>
		<div>
			<input class="text" name="name" id="name" value="<?php bb_form_option('name'); ?>" />
		</div>
		<label for="uri">
			<?php _e('bbPress address (URL):'); ?>
		</label>
		<div>
			<input class="text" name="uri" id="uri" value="<?php bb_form_option('uri'); ?>" />
			<p><?php _e('The full URL of your bbPress install.'); ?></p>
		</div>
		<label for="admin_email">
			<?php _e('E-mail address:') ?>
		</label>
		<div>
			<input class="text" name="admin_email" id="admin_email" value="<?php bb_form_option('admin_email'); ?>" />
			<p><?php _e('This address is used only for admin purposes.'); ?></p>
		</div>
		<label for="mod_rewrite">
			<?php _e('Pretty permalink type:') ?>
		</label>
		<div>
			<select name="mod_rewrite" id="mod_rewrite">
<?php
$selected = array();
$selected[bb_get_option('mod_rewrite')] = ' selected="selected"';
?>
				<option value="0"<?php echo $selected[0]; ?>><?php _e('None') ?>&nbsp;&nbsp;&nbsp;.../forums.php?id=1</option>
				<option value="1"<?php echo $selected[1]; ?>><?php _e('Numeric') ?>&nbsp;&nbsp;&nbsp;.../forums/1</option>
				<option value="slugs"<?php echo $selected['slugs']; ?>><?php _e('Name based') ?>&nbsp;&nbsp;&nbsp;.../forums/first-forum</option>
			</select>
		</div>
		<label for="page_topics">
			<?php _e('Items per page:') ?>
		</label>
		<div>
			<input class="text" name="page_topics" id="page_topics" value="<?php bb_form_option('page_topics'); ?>" />
			<p><?php _e('Number of topics, posts or tags to show per page.') ?></p>
		</div>
		<label for="editing">
			<?php _e('Lock post editing after:') ?>
		</label>
		<div>
			<input class="text" name="edit_lock" id="edit_lock" value="<?php bb_form_option('edit_lock'); ?>" />
			<?php _e('minutes') ?>
			<p>A user can edit a post for this many minutes after submitting.</p>
		</div>
	</fieldset>
	<fieldset>
		<legend><?php _e('Date and Time') ?></legend>
		<label>
			<?php _e('<abbr title="Coordinated Universal Time">UTC</abbr> time is:') ?>
		</label>
		<div>
			<?php echo gmdate(__('Y-m-d g:i:s a')); ?>
		</div>
		<label for="gmt_offset">
			<?php _e('Times should differ from UTC by:') ?>
		</label>
		<div>
			<input class="text" name="gmt_offset" id="gmt_offset" value="<?php bb_form_option('gmt_offset'); ?>" />
			<?php _e('hours') ?>
			<p><?php _e('Example: -7 for Pacific Daylight Time.'); ?></p>
		</div>
	</fieldset>
	<fieldset>
		<legend><?php _e('Anti-spam') ?></legend>
		<label for="akismet_key">
			<?php _e('Akismet Key:') ?>
		</label>
		<div>
			<input class="text" name="akismet_key" id="akismet_key" value="<?php bb_form_option('akismet_key'); ?>" />
			<p><?php _e('You do not need a key to run bbPress, but if you want to take advantage of Akismet\'s powerful spam blocking, you\'ll need one.'); ?></p>
			<p><?php _e('You can get an Akismet key at <a href="http://wordpress.com/api-keys/">WordPress.com</a>') ?></p>
		</div>
	</fieldset>
	<fieldset>
		<?php bb_nonce_field( 'options-general-update' ); ?>
		<input type="hidden" name="action" id="action" value="update" />
		<div class="spacer">
			<input type="submit" name="submit" id="submit" value="<?php _e('Update Options &raquo;') ?>" />
		</div>
	</fieldset>
</form>

<?php
bb_get_admin_footer();
?>