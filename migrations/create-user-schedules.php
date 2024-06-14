<?php

// creates schedules for existing users

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = BibleReadingChallenge\Database::get_instance();

$schedule_user_ids = $db->cols("SELECT user_id FROM schedules");

foreach($db->select("SELECT id, name, site_id FROM users") as $user) {
  if (!in_array($user['id'], $schedule_user_ids, true)) {
    echo $user['name']."[$user[id]] is getting a schedule.\n";
    $db->insert('schedules', [
      'site_id' => $user['site_id'],
      'user_id' => $user['id'],
      'name' => $user['name']."'s Default Schedule",
      'start_date' => date('Y-m-d', strtotime('January 1')),
      'end_date' => date('Y-m-d', strtotime('December 1')),
      'active' => 1
    ]);
  }
}