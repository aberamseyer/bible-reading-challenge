<?php

// crontab entry: 45 7 * * * php /home/bible-reading-challenge/cron/daily-verse-email.php

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_PATH);

$today = new Datetime();

$schedule = get_active_schedule();
$recently = new Datetime($schedule['start_date']);
$recently->modify('-1 month');

$scheduled_reading = get_reading($today);

if ($scheduled_reading) {
  foreach(select("SELECT id, name, email, trans_pref, last_seen FROM users WHERE email_verses = 1") as $user) {
    // if a user hasn't been active near the period of the schedule, we won't email them
    $last_seen_date = new Datetime('@'.$user['last_seen']);
    if ($recently <= $last_seen_date) {
      // make the name using everything but the last name
      $name_arr = explode(' ', $user['name']);
      $name = array_pop($name_arr);
      if ($name_arr) {
        $name = implode(' ', $name_arr);
      }

      $email_verses_key = bin2hex(random_bytes(16));
      update('users', [
        'email_verses_key' => $email_verses_key
      ], "id = ".$user['id']);
      $html = html_for_scheduled_reading($scheduled_reading, $user['trans_pref'], $email_verses_key);
      send_daily_verse_email($user['email'], $name, $html);
    }
  }
}