<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"<?php bb_language_attributes( '1.1' ); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php bb_title() ?></title>
	<?php bb_feed_head(); ?> 
	<link rel="stylesheet" href="<?php bb_stylesheet_uri(); ?>" type="text/css" />
<?php if ( 'rtl' == bb_get_option( 'text_direction' ) ) : ?>
	<link rel="stylesheet" href="<?php bb_stylesheet_uri( 'rtl' ); ?>" type="text/css" />
<?php endif;

if ( is_topic() && bb_is_user_logged_in() )
	wp_enqueue_script('topic');
bb_head();

 ?>
</head>

<body id="<?php bb_location(); ?>">
	
	<div id="wrapper">
	
		<div id="header">
			<h1><a href="<?php bb_option('uri'); ?>"><?php bb_option('name'); ?></a></h1>
			<?php login_form(); ?>
			<div class="search">
				<?php search_form(); ?>
			</div>
		</div>

	
		<div id="main">
<?php if ( is_bb_profile() ) profile_menu(); ?>



