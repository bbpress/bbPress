<?php
header( 'Content-Type: text/xml; charset=UTF-8' );
echo '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' . "\n";
bb_generator( 'comment' );
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom">
	<channel>
		<title><?php echo $title; ?></title>
		<link><?php echo $link; ?></link>
		<description><?php echo $description; ?></description>
		<language><?php wp_specialchars( bb_option('language') ); ?></language>
		<pubDate><?php echo gmdate('D, d M Y H:i:s +0000'); ?></pubDate>
		<?php bb_generator( 'rss2' ); ?>
		<textInput>
			<title><![CDATA[<?php _e('Search'); ?>]]></title>
			<description><![CDATA[<?php _e('Search all topics from these forums.'); ?>]]></description>
			<name>q</name>
			<link><?php bb_uri('search.php'); ?></link>
		</textInput>
		<atom:link href="<?php echo $link_self; ?>" rel="self" type="application/rss+xml" />

<?php foreach ($posts as $bb_post) : ?>
		<item>
			<title><?php post_author(); ?> <?php _e('on')?> "<?php topic_title( $bb_post->topic_id ); ?>"</title>
			<link><?php post_link(); ?></link>
			<pubDate><?php bb_post_time('D, d M Y H:i:s +0000', array( 'localize' => false ) ); ?></pubDate>
			<dc:creator><?php post_author(); ?></dc:creator>
			<guid isPermaLink="false"><?php post_id(); ?>@<?php bb_uri(); ?></guid>
			<description><?php post_text(); ?></description>
		</item>
<?php endforeach; ?>

	</channel>
</rss>
