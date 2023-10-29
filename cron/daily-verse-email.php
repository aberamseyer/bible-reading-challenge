<?php

//
// Sends an email to users with the daily reading portion
//
// crontab entry: 45 7 * * * php /home/bible-reading-challenge/cron/daily-verse-email.php

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_FILE);

$today = new Datetime();

$schedule = get_active_schedule();
$recently = new Datetime($schedule['start_date']);
$recently->modify('-1 month');

$scheduled_reading = get_reading($today, $schedule['id']);

if (!$scheduled_reading) {
  die("nothing to do today!");
}

foreach(select("SELECT id, name, email, trans_pref, last_seen, streak FROM users WHERE email_verses = 1") as $user) {
  // if a user hasn't been active near the period of the schedule, we won't email them
  $last_seen_date = new Datetime('@'.$user['last_seen']);
  if ($last_seen_date < $recently) {
    continue;
  }

  // skip anyone who's already read today (ptl early risers!)
  if (day_completed($user['id'], $scheduled_reading['id'])) {
    continue;
  }
  
  // make the user's name by using everything but the last name
  $name_arr = explode(' ', $user['name']);
  $name = array_pop($name_arr);
  if ($name_arr) {
    $name = implode(' ', $name_arr);
  }

  // total up the words in this day's reading
  $word_length = array_reduce(
    $scheduled_reading['passages'], 
    fn($acc, $cur) => intval(col("
      SELECT SUM(
        LENGTH($user[trans_pref]) - LENGTH(REPLACE($user[trans_pref], ' ', '')) + 1
      )
      FROM verses
      WHERE chapter_id = ".$cur['chapter']['id'])) + $acc);
  $minutes_to_read = ceil($word_length / 246); // words per minute

  // BUILD EMAIL
  /* the banner image at the top of the email is part of the email template in Sendgrid */

  /* chapter contents */
  $html = html_for_scheduled_reading($scheduled_reading, $user['trans_pref'], $scheduled_reading['complete_key'], $schedule, true);
  /* unsubscribe */
  $html .= "<p style='text-align: center;'><small>If you would no longer like to receive these emails, <a href='".SCHEME."://".DOMAIN."/?change_email_me=0'>click here to unsubscribe</a>.<small></p>";
  
  $streak = $user['streak'] > 1 ? "<p>🔥 Keep up your $user[streak]-day streak</p>" : "";
  send_daily_verse_email($user['email'], $name, $minutes_to_read." Minute Read", $html, $streak);
}