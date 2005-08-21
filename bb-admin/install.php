<?php
define('BB_INSTALLING', true);
if (!file_exists('../bb-config.php')) 
    die('There doesn\'t seem to be a <code>bb-config.php</code> file. I need this before we can get started.');

require_once('../bb-config.php');

$step = isset($_GET['step']) ? (int) $_GET['step'] : 0 ;

header( 'Content-Type: text/html; charset=utf-8' );
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title><?php _e('bbPress &rsaquo; Installation'); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style media="screen" type="text/css">
	<!--
	html {
		background: #eee;
	}
	body {
		background: #fff;
		color: #000;
		font-family: Georgia, "Times New Roman", Times, serif;
		margin-left: 20%;
		margin-right: 20%;
		padding: .2em 2em;
	}
	
	h1 {
		color: #060;
		font-size: 18px;
		font-weight: lighter;
	}
	
	h2 {
		font-size: 16px;
	}
	
	p, li, dt {
		line-height: 140%;
		padding-bottom: 2px;
	}

	ul, ol {
		padding: 5px 5px 5px 20px;
	}
	#logo {
		margin-bottom: 2em;
	}
	.step a, .step input {
		font-size: 2em;
	}
	td input {
		font-size: 1.5em;
	}
	.step {
		text-align: right;
	}
	th {
		text-align: left;
	}
	.required {
		color: #060;
	}
	#footer {
		text-align: center; 
		border-top: 1px solid #ccc; 
		padding-top: 1em; 
		font-style: italic;
	}
	.warning {
		background: #ff6;
	}
	-->
	</style>
</head>

<body>
<h1 id="logo"><img alt="bbPress" src="http://bbpress.org/bbpress.png" /></h1>
<?php
// Let's check to make sure bb isn't already installed.
$bbdb->hide_errors();
$installed = $bbdb->get_results("SELECT * FROM $bbdb->users");
if ($installed) die(__('<h1>Already Installed</h1><p>You appear to have already bbPress WordPress. Perhaps you meant to run the upgrade scripts instead? To reinstall please clear your old database tables first.</p>') . '</body></html>');
$bbdb->show_errors();

switch ($step):
	case 0:
?>
<div class="warning">
<p>bbPress is alpha software.  That means it will</p>
<ol>
<li>eat all your data,</li>
<li>crush your dreams,</li>
<li>and stab you in the eye</li>
</ol>
all after
<ol>
<li>claiming ownership of your firstborn,</li>
<li>serving you your own computer... with a delicious sauce flamb&#233;,</li>
<li>and urinating on your car door handle (just 'cause it can).</li>
</ol>
<p>You've been warned.</p>
</div>
<p><?php _e('Welcome to bbPress installation. We&#8217;re now going to go through a few steps to get you up and running with the latest in forums software.'); ?></p>
	<h2 class="step"><a href="install.php?step=1"><?php _e('First Step &raquo;'); ?></a></h2>
<?php
	break;

	case 1:
?>
<h1><?php _e('First Step'); ?></h1>
<p><?php _e('Make sure you have <strong>everything</strong> (database information, email address, etc.) entered correctly in <code>bb-config.php</code> before running this script.'); ?></p>
<p><?php _e("Before we begin we need a little bit of information about your site's first <strong>administrator account</strong>, and your site's first <strong>forum</strong>."); ?></p>

<form id="setup" method="post" action="install.php?step=2">
<table width="100%"> 
<tr><th>Administrator</th></tr>
<tr>
<td class="required"><?php _e('Login name*:'); ?></td>
<td><input name="admin_login" type="text" id="admin_login" size="25" /></td>
</tr>
<tr>
<td><?php _e("Website:"); ?></td>
<td><input name="admin_url" type="text" id="admin_url" size="100" /></td>
</tr>
<tr>
<td><?php _e("Location:"); ?></td>
<td><input name="admin_loc" type="text" id="admin_loc" size="100" /></td>
</tr>
<tr>
<td><?php _e('Interests:'); ?></td>
<td><input name="admin_int" type="text" id="admin_int" size="25" /></td>
</tr>
<tr><th>Forum</th></tr>
<tr>
<td class="required"><?php _e('Forum Name*:'); ?></td>
<td><input name="forum_name" type="text" id="forum_name" size="25" /></td>
</tr>
<tr>
<td"><?php _e('Description:'); ?></td>
<td><input name="forum_desc" type="text" id="forum_desc" size="25" /></td>
</tr>
</table>

<p><?php _e('* Items marked in <span class="required">green</span> are required.'); ?></p>
<p><em><?php _e('Double-check that login name before continuing.'); ?></em></p>

<h2 class="step">
<input type="submit" name="Submit" value="<?php _e('Continue to Second Step &raquo;'); ?>" />
</h2>
</form>

<?php
	break;

	case 2:
?>
<h1><?php _e('Second Step'); ?></h1>
<p><?php _e('Now we&#8217;re going to create the database tables and fill them with some default data.'); ?></p>


<?php
flush();

// Set everything up
require_once('upgrade-schema.php');
require_once('../bb-includes/registration-functions.php');

// Fill in the data we gathered
if ( !$admin_login = user_sanitize( $_POST['admin_login'] ) )
	die('Bad login name.  Go back and try again.');
if ( isset( $_POST['admin_url'] ) )
	$admin_url = bb_fix_link( $_POST['admin_url'] );

if ( !$forum_name = $_POST['forum_name'] )
	die('You must name your first forum.  Go back and try again.');
$forum_desc = ( isset( $_POST['forum_desc'] ) ) ? $_POST['forum_desc'] : '' ;

$password = bb_new_user( $admin_login, $bb->admin_email, $admin_url );
$bb_current_user = new BB_User( 1 );
if ( strlen( $_POST['admin_loc'] ) > 0 )
	bb_update_usermeta( 1, 'from', $_POST['admin_loc'] );
if ( strlen( $_POST['admin_int'] ) > 0 )
	bb_update_usermeta( 1, 'interest', $_POST['admin_int'] );

// First Forum
bb_new_forum( $forum_name, $forum_desc );

// First Topic
bb_new_topic('Your first topic', 1, 'bbPress');
bb_new_post(1, 'First Post!  w00t.');

$message_headers = 'From: ' . $forum_name . ' <' . $bb->admin_email . '>';
$message = sprintf(__("Your new bbPress site has been successfully set up at:

%1\$s

You can log in to the administrator account with the following information:

Username: %2\$s
Password: %3\$s

We hope you enjoy your new forums. Thanks!

--The bbPress Team
http://bbpress.org/
"), $bb->domain . $bb->path, $admin_login, $password);

@mail($bb->admin_email, __('New WordPress Blog'), $message, $message_headers);?>

<p><em><?php _e('Finished!'); ?></em></p>

<p><?php printf(__('Now you can <a href="%1$s">log in</a> with the <strong>username</strong> "<code>%2$s</code>" and <strong>password</strong> "<code>%3$s</code>".'), '..', $admin_login, $password); ?></p>
<p><?php _e('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you. If you lose it, you will have to delete the tables from the database yourself, and re-install bbPress. So to review:'); ?>
</p>
<dl>
<dt><?php _e('Login'); ?></dt>
<dd><code><?php echo $admin_login ?></code></dd>
<dt><?php _e('Password'); ?></dt>
<dd><code><?php echo $password; ?></code></dd>
	<dt><?php _e('Login address'); ?></dt>
<dd><a href=".."><?php echo $bb->name; ?></a></dd>
</dl>
<p><?php _e('Were you expecting more steps? Sorry to disappoint. All done! :)'); ?></p>
<?php
	break;
endswitch;
?>
<p id="footer"><?php _e('<a href="http://bbpress.org/">bbPress</a>: Simple, Fast, Elegant.'); ?></p>
</body>
</html>
