<?php
//
// pass a user id as an argument to refresh only that user's site and personal stats
//
//


require __DIR__."/../www/inc/env.php";

$db = \BibleReadingChallenge\Database::get_instance();
$redis = \BibleReadingChallenge\Redis::get_instance();

if (count($argv) > 1) {
  // one user and their site
  $user_id = (int)$argv[1];
  $site_id = $db->col("SELECT site_id FROM users WHERE id = $user_id");
  if ($site_id && $user_id) {
    $redis->delete_site_stats($site_id);
    $redis->delete_user_stats($site_id, $user_id);
    $redis->enqueue_stats($site_id);
    $redis->enqueue_stats("$site_id|$user_id");
  }
}
else {
  // all sites and users
  foreach($db->cols("SELECT id FROM sites WHERE enabled = 1") as $site_id) {
    $site = \BibleReadingChallenge\SiteRegistry::get_site($site_id);
    echo  "Deleting site stats:".$site->ID."\n";
    $redis->delete_site_stats($site_id);
    foreach([ ...$site->all_users(), ...$site->all_users(true) ] as $user) {
      echo  "Deleting user stats:".$user['id']."\n";
      $site->invalidate_user_stats($users['id']);
    }
  }
}