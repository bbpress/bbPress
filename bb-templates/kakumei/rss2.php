<?php header('Content-Type: text/xml; charset=UTF-8'); ?>
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<!-- generator="bbPress" -->

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
>

<channel>
<title><?php echo $title; ?></title>
<link><?php echo $link; ?></link>
<description><?php echo $description; ?></description>
<language><?php wp_specialchars( bb_option('language') ); ?></language>
<pubDate><?php echo gmdate('D, d M Y H:i:s +0000'); ?></pubDate>

<?php foreach ($posts as $bb_post) : ?>
<item>
<title><?php post_author(); ?> <?php _e('on')?> "<?php topic_title( $bb_post->topic_id ); ?>"</title>
<link><?php post_link(); ?></link>
<pubDate><?php bb_post_time('D, d M Y H:i:s +0000'); ?></pubDate>
<dc:creator><?php post_author(); ?></dc:creator>
<guid isPermaLink="false"><?php post_id(); ?>@<?php bb_uri(); ?></guid>
<description><?php post_text(); ?></description>
</item>
<?php endforeach; ?>

</channel>
</rss>
