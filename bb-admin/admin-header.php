<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<title><?php bb_admin_title() ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style type="text/css">
	 @import url(<?php option('uri'); ?>bb-admin/style.css);
	</style>
</head>

<body>

<div id="top"><h1>bbPress &#8212; <a href="<?php option('uri'); ?>"><?php option('name'); ?></a></h1>
<?php login_form(); ?>
</div>
<?php bb_admin_menu(); ?>
<div class="wrap">

