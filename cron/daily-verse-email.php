<?php

// crontab entry: 0 8 * * * php /home/bible-reading-challenge/cron/daily-verse-email.php

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_PATH);

$today = new Datetime();
$today = new Datetime('2023-08-25');
$schedule = get_active_schedule();
$scheduled_reading = get_reading($today);

if ($scheduled_reading) {
  foreach(select("SELECT name, email, trans_pref FROM users WHERE email_verses = 1") as $user) {
    $name = ucwords(explode(' ', $user['name'])[0]);
    $html = html_for_scheduled_reading($scheduled_reading, $user['trans_pref'], true);
    send_daily_verse_email($user['email'], $name, $html);
  }
}