<?php

//
// Creates user streaks. run once after adjusting the constants in the DatePeriod constructor
// all this code does is loop over the same code as the 'update-streaks.php', manually setting what day it is
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_FILE);
query("UPDATE users SET streak=0, max_streak=0");

$period = new DatePeriod(
  new Datetime(CHANGE_ME_TO_START_DATE), // e.g., '2023-08-21', the first day of the reading challenge schedule
  new DateInterval('P1D'),
  new Datetime(CHANGE_ME_TO_CURRENT_DATE),
  DatePeriod::INCLUDE_END_DATE
);

$schedule = get_active_schedule();;

foreach($period as $day) {
  $yesterday = new Datetime('@'.strtotime('yesterday', $day->format('U')));
  
  $scheduled_reading = get_reading($yesterday, $schedule['id']);

  if ($scheduled_reading) {
    foreach(select("SELECT * FROM users") as $user) {
      $current_streak = $user['streak'];
      $read_yesterday = col("
        SELECT id
        FROM read_dates
        WHERE user_id = $user[id] AND
          DATE(timestamp, 'unixepoch', 'localtime') = '".$yesterday->format('Y-m-d')."'");
          
      update('users', [
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