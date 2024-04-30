<?php

//
// Creates user streaks. run once after adjusting the constants in the DatePeriod constructor
// all this code does is loop over the same code as the 'update-streaks.php', manually setting what day it is
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT * FROM sites") as $site_id) {
  $site = BibleReadingChallenge\Site::get_site($site_id);
  $db->query("UPDATE users SET streak=0, max_streak=0 WHERE site_id = ".$site->ID);
  
  $period = new DatePeriod(
    new Datetime(CHANGE_ME_TO_START_DATE), // e.g., '2023-08-21', the first day of the reading challenge schedule (or whatever you want)
    new DateInterval('P1D'),
    new Datetime(CHANGE_ME_TO_CURRENT_DATE), // probably today's date
    DatePeriod::INCLUDE_END_DATE
  );
  $schedule = $site->get_active_schedule();
  
  foreach($period as $day) {
    $yesterday = new Datetime('@'.strtotime('yesterday', $day->format('U')), $site->TZ);
    
    $scheduled_reading = get_reading($yesterday, $schedule['id']);
  
    if ($scheduled_reading) {
      foreach($db->select("SELECT * FROM users WHERE site_id = ".$site->ID) as $user) {
        $current_streak = $user['streak'];
        $read_yesterday = $db->col("
          SELECT id
          FROM read_dates
          WHERE user_id = $user[id] AND
            DATE(timestamp, 'unixepoch', '".$site->TZ_OFFSET." hours') = '".$yesterday->format('Y-m-d')."'");
            
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
}