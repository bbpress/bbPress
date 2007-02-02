<?php
define('BB_INSTALLING', true);
require_once('../bb-load.php');

$step = isset($_GET['step']) ? (int) $_GET['step'] : 0 ;
	if ( 2 == $step && isset($_POST['new_keymaster']) && 'new' == $_POST['new_keymaster'] )
		$step = 1;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php _e('bbPress &rsaquo; Installation'); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" href="install.css" type="text/css" />
<?php if ( 'rtl' == $bb_locale->text_direction ) : ?>
        <link rel="stylesheet" href="install-rtl.css" type="text/css" />
<?php endif; ?>
</head>

<body>
<h1 id="logo"><img alt="bbPress" src="../bb-images/bbpress-large.png" /></h1>
<?php
// Let's check to make sure bb isn't already installed.
$bbdb->hide_errors();
$installed = $bbdb->get_results("SELECT * FROM $bbdb->forums LIMIT 1");
if ( $installed ) :
	if ( !$new_keymaster = bb_get_option( 'new_keymaster' ) )
		die(__('<h1>Already Installed</h1><p>You appear to have already installed bbPress. Perhaps you meant to run the upgrade scripts instead? To reinstall please clear your old database tables first.</p>') . '</body></html>');
	$meta_key = $bb_table_prefix . 'capabilities';
	$keymaster = false;
	if ( $keymasters = $bbdb->get_results("SELECT * FROM $bbdb->usermeta WHERE meta_key = '$meta_key' AND meta_value LIKE '%keymaster%'") ) {
		foreach ( $keymasters as $potential ) {
			$pot_array = unserialize($potential->meta_value);
			if ( is_array($pot_array) && array_key_exists('keymaster', $pot_array) && true === $pot_array['keymaster'] )
				die(__('<h1>Already Installed</h1><p>You appear to have already installed bbPress. Perhaps you meant to run the upgrade scripts instead? To reinstall please clear your old database tables first.</p>') . '</body></html>');
		}
	}

	$user = new BB_User( $new_keymaster );
	if ( $user->data ) :
		$user->set_role( 'keymaster' ); ?>

<p><?php printf(__('%s is now a Key Master'), $user->data->user_login); ?></p>
<p><a href="<?php bb_option( 'uri' ); ?>"><?php _e('Back to the front page'); ?></a></p>

<?php	else : ?>

<p><?php _e('Username not found.  Try again.'); ?></p>
<?php	endif;

$step = 10;
endif;
$bbdb->show_errors();

switch ($step):
	case 0:
?>
<p><?php _e('Welcome to bbPress installation. We&#8217;re now going to go through a few steps to get you up and running with the latest in forums software.'); ?></p>
	<h2 class="step"><a href="install.php?step=1"><?php _e('First Step &raquo;'); ?></a></h2>
<?php
	break;

	case 1:
		$keymaster = false;
		$users = false;
		if ( !isset($_POST['new_keymaster']) ) {
			$bbdb->hide_errors();
			if ( $users = $bbdb->get_var("SELECT ID FROM $bbdb->users LIMIT 1") ) {
				$meta_key = $bb_table_prefix . 'capabilities';
				if ( $keymasters = $bbdb->get_results("SELECT * FROM $bbdb->usermeta WHERE meta_key = '$meta_key' AND meta_value LIKE '%keymaster%'") ) {
					foreach ( $keymasters as $potential ) {
						$pot_array = unserialize($potential->meta_value);
						if ( array_key_exists('keymaster', $pot_array) && true === $pot_array['keymaster'] ) {
							$keymaster = (int) $potential->user_id;
							break;
						}
					}
					if ( $keymaster )
						$keymaster = $bbdb->get_row("SELECT * FROM $bbdb->users WHERE ID = '$keymaster'");
				}
			}
			$bbdb->show_errors();
		}


?>
<h1><?php _e('First Step'); ?></h1>
<p><?php _e('Make sure you have <strong>everything</strong> (database information, email address, etc.) entered correctly in <code>config.php</code> before running this script.'); ?></p>
<?php
	$errors = new WP_Error();
	$notices = new WP_Error();

	$bbd = bb_get_option( 'domain' );
	$bbp = bb_get_option( 'path' );
	if ( '/' == substr($bbd, -1) )
		$errors->add('domain', __('Your <code>$bb->domain</code> setting must <strong>not</strong> end in a backslash "<code>/</code>".') );
	$domain = parse_url($bbd);
	if ( !$domain )
		$errors->add('domain', __('Your <code>$bb->domain</code> setting cannot be parsed.') ); // Not very helpful, but should essentially never happen.
	if ( !$domain['scheme'] )
		$errors->add('domain', __('Your <code>$bb->domain</code> setting <strong>must</strong> start with <code>http://</code>.') );
	if ( $domain['path'] && '/' != $domain['path'] )
		$errors->add('domain', __('Your <code>$bb->domain</code> setting must only include the <code>http://</code> and the domain name; it may not include any directories or path information.') );
	if ( '/' != $bbp{0} )
		$errors->add('path', __('Your <code>$bb->path</code> setting <strong>must</strong> start with a backslash "<code>/</code>".') );
	if ( '/' != substr($bbp, -1) )
		$errors->add('path', __('Your <code>$bb->path</code> setting <strong>must</strong> end with a backslash "<code>/</code>".') );

	// We don't really do anything with $bb->wp_site_url.

	if ( $wph = bb_get_option( 'wp_home' ) ) {
		if ( '/' == $wph{strlen($wph) - 1} )
			$errors->add('wp_home', __('Your <code>$bb->wp_home</code> setting must <strong>not</strong> end in a backslash "<code>/</code>".') );
		$home = parse_url($wph);
		if ( !$home )
			$errors->add('wp_home', __('Your <code>$bb->wp_home</code> setting cannot be parsed.') );
		if ( !$home['scheme'] )
			$errors->add('wp_home', __('Your <code>$bb->wp_home</code> setting <strong>must</strong> start with <code>http://</code>.') );
		if ( preg_match('|(.*\.)?([^.]+\.[^.]+)|', $domain['host'], $d2 ) && preg_match('|(.*\.)?([^.]+\.[^.]+)|', $home['host'], $h2 )) {
			if ( $d2[2] != $h2[2] )
				$errors->add('cookie', __('Your <code>$bb->domain</code> and <code>$bb->wp_home</code> settings do not have the same domain.<br />You cannot share login cookies between the two.<br />Remove the <code>$bb->wp_home</code> setting from your config.php file.') );
		} elseif ( !$d2 ) {
			$errors->add('domain', __('Your <code>$bb->domain</code> setting cannot be parsed.') ); // Not very helpful, but should essentially never happen.
		} else {
			$errors->add('cookie', __('Your <code>$bb->wp_home</code> setting cannot be parsed.') ); // Not very helpful, but should essentially never happen.
		}
		if ( !strstr($bbp, $home['path'] . '/') )
			$notices->add('cookie', __("Your bbPress URL ({$bbd}$bbp) is not a subdirectory of your WordPress URL ($bb->wp_home).<br />Sharing login cookies is possible but is more complicated.  See the documentation about integrating bbPress and WordPress.<br />In the meantime, remove the <code>$bb->wp_home</code> setting from your config.php file, or you may not be able to log in.") );
	}

	if ( $cd = bb_get_option( 'cookiedomain' ) ) {
		if ( '.' == $cd{0} )
			$cd = substr($cd, 1);
		if ( !strstr($bbd, $cd) )
			$errors->add('cookie', __('Your <code>$bb->cookiedomain</code> is not in the same domain as your <code>$bb->domain</code>.  You will not be able to log in.') );
	}

	$cp = bb_get_option( 'cookiepath' );
	if ( $cp != preg_replace('|https?://[^/]+|i', '', bb_get_option( 'wp_home' ) . '/') && !strstr($bbp, $cp) )
		$notices->add('cookie', __('Your bbPress URL <code>$bb->path</code> is outside of your <code>$bb->cookiepath</code>.  You may not be able to log in.') );

	if ( $ecodes = $errors->get_error_codes() ) {
		echo "<ul class='error'>\n";
		if ( in_array('domain', $ecodes) )
			foreach ( $errors->get_error_messages( 'domain' ) as $message )
				echo "\t<li>$message</li>\n";
		
		if ( in_array('path', $ecodes) )
			foreach ( $errors->get_error_messages( 'path' ) as $message )
				echo "\t<li>$message</li>\n";
		if ( in_array('wp_home', $ecodes) )
			foreach ( $errors->get_error_messages( 'wp_home' ) as $message )
				echo "\t<li>$message</li>\n";
		if ( array('cookie') == $ecodes ) { // Only show cookie errors if nothing else is wrong
			foreach ( $errors->get_error_messages( 'cookie' ) as $message )
				echo "\t<li>$message</li>\n";
			echo "</ul>\n";
			break;
		}
		echo "</ul>\n";
		echo "<h2>Current settings</h2>\n";
		echo "<table class='current'>\n";
		foreach ( $ecodes as $ecode ) {
			if ( 'cookie' == $ecode )
				continue;
			echo "\t<tr><td>\$bb->$ecode:</td><td><code>" . bb_get_option( $ecode ) . "</code></td></tr>\n";
		}
		echo "</table>\n";
		break;
	}
	if ( $ncodes = $notices->get_error_codes() ) {
		echo "<ul class='notice'>\n";
		foreach ( $notices->get_error_messages() as $message )
			echo "\t<li>$message</li>\n";
		echo "</ul>\n";
	}
?>
<p><?php _e("Before we begin we need a little bit of information about your site's first <strong>administrator account</strong>, and your site's first <strong>forum</strong>."); ?></p>

<form id="setup" method="post" action="install.php?step=2">
<h2><?php _e('Administrator'); ?></h2>
<?php	if ( $keymaster ) : ?>
<p><?php printf(__('We found <strong>%s</strong> who is already a "Key Master" on these forums.  You may make others later'), get_user_name( $keymaster->ID )) ?>.</p>
<input type="hidden" name="old_keymaster" value="<?php echo $keymaster->ID; ?>" />
<?php elseif ( $users ) : ?>
<p><?php _e("Enter your username below.  You will be made the first Key Master on these forums.  Leave this blank if you want to create a new account"); ?></p>
<input type="text" name="new_keymaster" />
<?php else : ?>
<table width="100%" cellpadding="4">
<tr<?php alt_class( 'user' ); ?>>
<td class="required" width="25%"><label for="admin_login"><?php _e('Username:'); ?>*</label></td>
<td><input name="admin_login" type="text" id="admin_login" size="25" /></td>
</tr>
<tr<?php alt_class( 'user' ); ?>>
<td width="25%"><?php _e('Email address:'); ?></td>
<td><?php bb_option( 'admin_email' ); ?><br /><small><?php _e('(You already set this in your <code>config.php</code> file.)'); ?></small></td>
</tr>
<tr<?php alt_class( 'user' ); ?>>
<td><label for="admin_url"><?php _e("Website:"); ?></label></td>
<td><input name="admin_url" type="text" id="admin_url" size="30" /></td>
</tr>
<tr<?php alt_class( 'user' ); ?>>
<td><label for="admin_loc"><?php _e("Location:"); ?></label></td>
<td><input name="admin_loc" type="text" id="admin_loc" size="30" /></td>
</tr>
<tr<?php alt_class( 'user' ); ?>>
<td><label for="admin_int"><?php _e('Interests:'); ?></label></td>
<td><input name="admin_int" type="text" id="admin_int" size="25" /></td>
</tr>
</table>
<?php endif; ?>

<h2><?php _e('First Forum') ?></h2>

<table width="100%" cellpadding="4">
<tr<?php alt_class( 'forum' ); ?>>
<td class="required" width="25%"><?php _e('Forum Name:'); ?>*</td>
<td><input name="forum_name" type="text" id="forum_name" value="<?php echo wp_specialchars( @$_POST['forum_name'], 1); ?>" size="25" /></td>
</tr>
<tr<?php alt_class( 'forum' ); ?>>
<td><?php _e('Description:'); ?></td>
<td><input name="forum_desc" type="text" id="forum_desc" value="<?php echo wp_specialchars( @$_POST['forum_desc'], 1); ?>" size="25" /></td>
</tr>
</table>
<p><em><?php _e('Double-check that username before continuing.'); ?></em></p>

<h2 class="step">
<input type="submit" name="Submit" value="<?php _e('Continue to Second Step &raquo;'); ?>" />
</h2>
</form>

<?php
	break;

	case 2:
flush();

// Set everything up
if ( !isset($_POST['old_keymaster']) && !isset($_POST['new_keymaster']) && !$admin_login = user_sanitize( $_POST['admin_login'] ) )
	die(__('Bad username.  Go back and try again.'));
if ( isset($_POST['new_keymaster']) && !bb_get_user_by_name( $_POST['new_keymaster'] ) )
	die(__('Username not found.  Go back and try again.'));
if ( !$forum_name = $_POST['forum_name'] )
	die(__('You must name your first forum.  Go back and try again.'));
?>
<h1><?php _e('Second Step'); ?></h1>
<p><?php _e('Now we&#8217;re going to create the database tables and fill them with some default data.'); ?></p>

<?php
require_once('upgrade-schema.php');
require_once( BBPATH . BBINC . 'registration-functions.php');

function get_keymaster_password($user_id, $pass) {
	global $password;
	$password = $pass;
}

// Fill in the data we gathered
// KeyMaster
if ( isset($_POST['old_keymaster']) ) :
	$bb_current_user = bb_set_current_user( (int) $_POST['old_keymaster'] );
	$admin_login = get_user_name( $bb_current_user->ID );
	$password = __('*Your WordPress password*');
	$already = true;
elseif ( isset($_POST['new_keymaster']) ) :
	$bb_current_user = bb_set_current_user( $_POST['new_keymaster'] );
	$bb_current_user->set_role('keymaster');
	$admin_login = get_user_name( $bb_current_user->ID );
	$password = __('*Your WordPress password*');
	$already = true;
else :
	if ( isset( $_POST['admin_url'] ) )
		$admin_url = bb_fix_link( $_POST['admin_url'] );
	add_action('bb_new_user','get_keymaster_password',10,2);
	global $password;
	$user_id = bb_new_user( $admin_login, bb_get_option( 'admin_email' ), $admin_url );
	$bb_current_user = bb_set_current_user( $user_id );
	if ( strlen( $_POST['admin_loc'] ) > 0 )
		bb_update_usermeta( 1, 'from', $_POST['admin_loc'] );
	if ( strlen( $_POST['admin_int'] ) > 0 )
		bb_update_usermeta( 1, 'interest', $_POST['admin_int'] );
	$already = false;
endif;

// First Forum
$forum_desc = ( isset( $_POST['forum_desc'] ) ) ? $_POST['forum_desc'] : '' ;
bb_new_forum( $forum_name, $forum_desc );

// First Topic
bb_new_topic(__('Your first topic'), 1, 'bbPress');
bb_new_post(1, __('First Post!  w00t.'));

$message_headers = 'From: ' . $forum_name . ' <' . bb_get_option( 'admin_email' ) . '>';
$message = sprintf(__("Your new bbPress site has been successfully set up at:

%1\$s

You can log in to the administrator account with the following information:

Username: %2\$s
Password: %3\$s

We hope you enjoy your new forums. Thanks!

--The bbPress Team
http://bbpress.org/
"), bb_get_option( 'uri' ), $admin_login, $password);

@mail(bb_get_option( 'admin_email' ), __('New bbPress installation'), $message, $message_headers);?>

<p><em><?php _e('Finished!'); ?></em></p>

<p><?php printf(__('Now you can <a href="%1$s">log in</a> with the <strong>username</strong> "<code>%2$s</code>" and <strong>password</strong> "<code>%3$s</code>".'), '..', $admin_login, $password); ?></p>
<?php if ( !$already ) : ?>
<p><?php _e('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you. If you lose it, you will have to delete the tables from the database yourself, and re-install bbPress. So to review:'); ?></p>
<?php endif; ?>
<dl>
<dt><?php _e('Username'); ?></dt>
<dd><code><?php echo $admin_login ?></code></dd>
<dt><?php _e('Password'); ?></dt>
<dd><code><?php echo $password; ?></code></dd>
	<dt><?php _e('Login address'); ?></dt>
<dd><a href=".."><?php bb_option( 'name' ); ?></a></dd>
</dl>
<p><?php _e('Were you expecting more steps? Sorry to disappoint. All done! :)'); ?></p>
<?php
	break;
endswitch;
?>
<p id="footer"><?php _e('<a href="http://bbpress.org/">bbPress</a>: Simple, Fast, Elegant.'); ?></p>
</body>
</html>
