<?php require_once('admin.php'); require_once(BBPATH . BBINC . '/statistics-functions.php'); ?>

<?php
if (isset($_POST['submit'])) {
	$activetheme = stripslashes($_POST['active_theme']);
	bb_update_option('bb_active_theme',$activetheme);
	bb_admin_notice( sprintf(__('Theme "%s" activated'), basename($activetheme)) );
}

$activetheme = bb_get_option('bb_active_theme');

$themes = bb_get_themes();

if ( !in_array($activetheme, $themes)) {
	$activetheme = BBPATH . 'bb-templates/kakumei';
	bb_update_option('bb_active_theme',$activetheme);
	bb_admin_notice( __('Theme not found.  Default theme applied.'), 'error' );
}

bb_get_admin_header();
?>

<h2><?php _e('Presentation'); ?></h2>

<form method="post">
	<?php
	foreach ($themes as $theme) :
		if ($theme == $activetheme) $checked = "checked='checked' "; else $checked = "";
		$base = basename($theme);
		echo "<p><input type='radio' name='active_theme' value ='$theme' $checked/> $base</p>";
	endforeach;
	?>
	<p class="submit"><input type="submit" name="submit" value="Make Default"></p>
</form>

<?php bb_get_admin_footer(); ?>
