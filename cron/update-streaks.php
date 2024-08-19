<?php

//
// Updates user streaks–should be run daily
//
// crontab entry: 50 * * * * php /home/bible-reading-challenge/cron/update-streaks.php

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites") as $site_id) {
  $site = BibleReadingChallenge\SiteRegistry::get_site($site_id);
  $dt = new DateTime('now', $site->TZ);
  // this cron runs every hour, we only want to update the sites who's local time is 3:50 AM
  if ($dt->format('G') != 3) {
    continue;
  }

  $schedule = $site->get_active_schedule();

  $yesterday = new DateTime('@'.strtotime('yesterday'), $site->TZ);
  $scheduled_reading = $schedule->get_schedule_date($yesterday);

  if ($scheduled_reading) {
    foreach($db->select("SELECT * FROM users WHERE site_id = ".$site->ID) as $user) {
      $current_streak = $user['streak'];
      
      $read_yesterday = $db->col("
        SELECT id
        FROM read_dates
        WHERE user_id = $user[id] AND
          DATE(timestamp, 'unixepoch', '".$site->TZ_OFFSET." hours') = '".$yesterday->format('Y-m-d')."'"); // irrespective of schedule within a site

      $db->update('users', [
        'streak' => $read_yesterday
          ? $user['streak'] + 1
          : 0, 
        'max_streak' => $read_yesterday
          ? max(intval($user['max_streak']), intval($user['streak']) + 1)
          : $user['max_streak']
      ], "id = ".$user['id']);
    }
  }
}