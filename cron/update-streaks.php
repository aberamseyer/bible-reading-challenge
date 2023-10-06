<?php

//
// Updates user streaks–meant to be run daily
//
// crontab entry: 50 3 * * * php /home/bible-reading-challenge/cron/update-streaks.php

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_FILE);

$schedule = get_active_schedule();;

$yesterday = new Datetime('@'.strtotime('yesterday'));
$scheduled_reading = get_reading($yesterday, $schedule['id']);

if ($scheduled_reading) {
  foreach(select("SELECT * FROM users") as $user) {
    $current_streak = $user['streak'];
    
    $read_yesterday = col("
      SELECT id
      FROM read_dates
      WHERE user_id = $user[id] AND
        DATE(timestamp, 'unixepoch', 'localtime') = '".$yesterday->format('Y-m-d')."'"); // irrespective of schedule

    update('users', [
      'streak' => $read_yesterday
        ? max($user['streak'] + 1, 2) // streaks start at 2 days
        : 0, 
      'max_streak' => $read_yesterday
        ? max(intval($user['max_streak']), max(2, intval($user['streak'] + 1)))
        : $user['max_streak']
    ], "id = ".$user['id']);
  }
}