<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"<?php bb_language_attributes( '1.1' ); ?>>
<?php if (bb_is_profile()) : global $self; ?>
<?php if (!$self) : ?>
<head profile="http://www.w3.org/2006/03/hcard">
<?php else : ?>
<head>
<?php endif; ?>
<?php else : ?>
<head>
<?php endif; ?>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php bb_title() ?></title>
	<?php bb_feed_head(); ?> 
	<link rel="stylesheet" href="<?php bb_stylesheet_uri(); ?>" type="text/css" />
<?php if ( 'rtl' == bb_get_option( 'text_direction' ) ) : ?>
	<link rel="stylesheet" href="<?php bb_stylesheet_uri( 'rtl' ); ?>" type="text/css" />
<?php endif; ?>

<?php bb_head(); ?>

</head>

<body id="<?php bb_location(); ?>">
	<div id="wrapper">
	
		<div id="header" role="banner">
			<h1><a href="<?php bb_uri(); ?>"><?php bb_option('name'); ?></a></h1>
			<?php if ( bb_get_option('description') ) : ?><p class="description"><?php bb_option('description'); ?></p><?php endif; ?>

			<?php if ( !in_array( bb_get_location(), array( 'login-page', 'register-page' ) ) ) login_form(); ?>
			<div class="search">
				<?php search_form(); ?>
			</div>
		</div>

	
		<div id="main">
<?php if ( bb_is_profile() ) profile_menu(); ?>
