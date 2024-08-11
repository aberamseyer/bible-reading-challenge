<?php

require __DIR__."/../www/inc/env.php";

$redis = BibleReadingChallenge\Redis::get_instance();

while (true) {
  try {
    // Blocks until a job is ready
    $job = $redis->dequeue_stats();
    
    if ($job) {            
      process_job($job);
    }
    else {
      // blocking
    }
  } catch (Exception $e) {
    error_log('Error processing job: ' . $e->getMessage());
  }
}


function process_job($job) {
  $start = hrtime(true);
  echo "Starting job: ".print_r($job, true)."...\n";
  $db = BibleReadingChallenge\Database::get_instance();
  $redis = BibleReadingChallenge\Redis::get_instance();

  list($site_id, $user_id) = explode("|", $job);
  $site = BibleReadingChallenge\SiteRegistry::get_site((int)$site_id);
  $user = $db->row("
    SELECT id, name
    FROM users
    WHERE id = ".intval($user_id)." AND site_id = ".$site->ID);
  if (!$site || !$user) {
    $redis->stats_job_finished($job);
    throw new \Exception("Bad site_id/user_id combination: ".$job);
  }
  else {
    $site->user_stats($user['id'], true);
    $redis->stats_job_finished($job);
    echo "Finished $job in ".number_format((hrtime(true) - $start) / 1e6, 2)."ms\n";
  }
}