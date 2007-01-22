<?php header('Content-type: text/xml'); ?>
<?php echo '<?xml version="1.0"?>'; ?>
<!-- generator="bbPress" -->

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
>

<channel>
<title><?php echo $title; ?></title>
<link><?php bb_option('uri'); ?></link>
<description><?php echo $title; ?></description>
<language>en</language>
<pubDate><?php echo gmdate('D, d M Y H:i:s +0000'); ?></pubDate>

<?php foreach ($posts as $bb_post) : ?>
<item>
<title><?php post_author(); ?> <?php _e('on')?> "<?php topic_title( $bb_post->topic_id ); ?>"</title>
<link><?php post_link(); ?></link>
<pubDate><?php post_date('D, d M Y H:i:s +0000'); ?></pubDate>
<dc:creator><?php post_author(); ?></dc:creator>
<guid isPermaLink="false"><?php post_id(); ?>@<?php bb_option('uri'); ?></guid>
<description><?php post_text(); ?></description>
</item>
<?php endforeach; ?>

</channel>
</rss>
