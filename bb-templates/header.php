<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<title><?php bb_title() ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?php bb_feed_head(); ?>
	<style type="text/css">
	 @import url(<?php bb_stylesheet_uri(); ?>);
	</style>

<?php if ( is_topic() && $bb_current_user ) : ?>
	<script type="text/javascript">
		function addLoadEvent(func) {
			var oldonload = window.onload;
			if (typeof window.onload != 'function') {
				window.onload = func;
			} else {
				window.onload = function() {
					oldonload();
					func();
				}
			}
		}

		var lastMod = <?php echo strtotime($topic->topic_time . ' +0000'); ?>;
		var page = <?php global $page; echo $page; ?>;
		var currentUserId = <?php echo $bb_current_user->ID; ?>;
		var topicId = <?php topic_id(); ?>;
		var uriBase = '<?php option('uri'); ?>';
		var tagLinkBase = '<?php tag_link_base(); ?>';
		var favoritesLink = '<?php favorites_link(); ?>'; 
		var isFav = <?php if ( false === $is_fav = is_user_favorite( $bb_current_user->ID ) ) echo "'no'"; else echo $is_fav; ?>;
	</script>
	<script type="text/javascript" src="<?php option('uri'); ?>bb-scripts/fat.js"></script>
	<script type="text/javascript" src="<?php option('uri'); ?>bb-scripts/tw-sack.js"></script>
	<script type="text/javascript" src="<?php option('uri'); ?>bb-scripts/topic.js"></script>
<?php endif; ?>
</head>

<body id="top">

<div id="main">
