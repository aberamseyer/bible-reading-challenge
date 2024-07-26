<?php

//
// Updates users last-seen timestamps–should be run daily
//
// crontab entry: 47 3 * * * php /home/bible-reading-challenge/cron/update-last-seen.php

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();
$redis = BibleReadingChallenge\Redis::get_instance();

foreach($redis->user_iterator() as $key) {
  $key = str_replace(BibleReadingChallenge\Redis::SITE_NAMESPACE, '', $key);
  $id = (int)str_replace(BibleReadingChallenge\Redis::LAST_SEEN_KEYSPACE, '', $key);
  $user = $db->col("SELECT name FROM users WHERE id = ".(int)$id);
  $time = $redis->client()->get($key);
  if ($id && $user && $time) {
    echo "$user ($id) was last seen ".date('Y-m-d H:i:s', $time)."\n";
    $db->update('users', [
      'last_seen' => $time
    ], 'id = '.$id);
  }
}
