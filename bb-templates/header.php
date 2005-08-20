<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
	<title><?php bb_title() ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<?php bb_feed_head(); ?>
	<style type="text/css">
	 @import url(<?php bb_stylesheet_uri(); ?>);
	</style>

<?php if ( is_topic() ) : ?>
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

		var topicId = <?php topic_id(); ?>;
		var tagLinkBase = '<?php tag_link_base(); ?>';
	</script>
	<script type="text/javascript" src="<?php option('uri'); ?>bb-scripts/fat.js" />
	<script type="text/javascript" src="<?php option('uri'); ?>bb-scripts/tw-sack.js" />
	<script type="text/javascript" src="<?php option('uri'); ?>bb-scripts/topic.js" />
<?php endif; ?>
</head>

<body>

<div id="rap">
