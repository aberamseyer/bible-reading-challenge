<?php

require __DIR__."/../www/inc/env.php";

$db = \BibleReadingChallenge\Database::get_instance();
$redis = \BibleReadingChallenge\Redis::get_instance();

// clear stats values
foreach($redis->stats_iterator() as $key) {
  echo "Deleting $key\n";
  $key = str_replace(BibleReadingChallenge\Redis::SITE_NAMESPACE, '', $key);
  $redis->client()->del($key);
}

foreach($db->cols("SELECT id FROM sites WHERE enabled = 1") as $site_id) {
  $site = \BibleReadingChallenge\Site::get_site($site_id);
  foreach($site->all_users() as $user) {
    $redis->enqueue_stats($site->ID, $user['id']);
  }
}