<?php get_header(); ?>

<h3><a href="<?php option('uri'); ?>"><?php option('name'); ?></a> &raquo; Profile</h3>
<h2><?php echo $user->username; ?></h2>
<dl id="userinfo">
<dt>Member Since</dt>
<dd><?php echo date('F j, Y', $ts); ?> (<?php echo since($ts); ?>)</dd>
<?php
if ($user->user_website) :
        $USERINFO .= "<dt>Web address</dt>
<dd><a href='$user->user_website'>$user->user_website</a></dd>
";
endif;
if ($user->user_from) :
        $USERINFO .= "<dt>Where in the world?</dt>
<dd>$user->user_from</dd>
";
endif;
if ($user->user_occ) :
        $USERINFO .= "<dt>Occupation</dt>
<dd>$user->user_occ</dd>
";
endif;
if ($user->user_interest) :
        $USERINFO .= "<dt>Interests</dt>
<dd>$user->user_interest</dd>
";
endif;
echo $USERINFO;
?>
</dl>

<h2>User Activity</h2>

<div id="user-replies" class="user-recent"><h3>Recent Replies</h3>
<?php
$recent = $bbdb->get_results("SELECT DISTINCT $bbdb->posts.topic_id, $bbdb->posts.forum_id, topic_title, MAX(post_time) AS m, UNIX_TIMESTAMP(MAX(post_time))
AS posted FROM $bbdb->posts, $bbdb->topics WHERE $bbdb->posts.poster_id=$user->user_id AND
$bbdb->posts.topic_id=$bbdb->topics.topic_id GROUP BY $bbdb->posts.topic_id ORDER BY m desc LIMIT 25");

if (!$recent) :
        $USERINFO = '<p>No replies yet.</p>';
else :
        $USERINFO = '<ol>';
        foreach ($recent as $r) :
                $when = since($r->posted);
                $USERINFO .= "<li><a href='/support/$r->forum_id/$r->topic_id'>$r->topic_title</a> $when ago</li>";
        endforeach;
        $USERINFO .= '</ol>';
endif;
$USERINFO .= '</div>';

$USERINFO .= '<div id="user-threads" class="user-recent"><h3>Recent Threads</h3>';

$threads = $bbdb->get_results("SELECT topic_id, forum_id, topic_title, UNIX_TIMESTAMP(topic_time) AS posted FROM $bbdb->topics WHERE
topic_poster=$user->user_id ORDER BY topic_time DESC LIMIT 25");


if (!$threads) :
        $USERINFO .= '<p>No topics posted yet.</p>';
else :
        $USERINFO .= '<ol>';
        foreach ($threads as $r) :
                $when = since($r->posted);
                $USERINFO .= "<li><a href='/support/$r->forum_id/$r->topic_id'>$r->topic_title</a> $when ago</li>";
        endforeach;
        $USERINFO .= '</ol>';
endif;
$USERINFO .= '</div><br style="clear: both;" />';
echo $USERINFO;
?>

<?php get_footer(); ?>