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
  foreach(select("SELECT id, name, email, trans_pref, last_seen, complete_key FROM users WHERE email_verses = 1") as $user) {
    // if a user hasn't been active near the period of the schedule, we won't email them
    $last_seen_date = new Datetime('@'.$user['last_seen']);
    if ($recently <= $last_seen_date) {
      // make the name using everything but the last name
      $name_arr = explode(' ', $user['name']);
      $name = array_pop($name_arr);
      if ($name_arr) {
        $name = implode(' ', $name_arr);
      }

      // the banner image at the top of the email is part of the email template in Sendgrid

      // chapter contents
      $html = html_for_scheduled_reading($scheduled_reading, $user['trans_pref'], $user['complete_key'], true);
      // unsubscribe
      $html .= "<p style='text-align: center;'><small>If you would no longer like to receive these emails, <a href='".SCHEME."://".DOMAIN."/?change_email_me=0'>click here to unsubscribe</a>.<small></p>";
      
      send_daily_verse_email($user['email'], $name, $html);
    }
  }
}