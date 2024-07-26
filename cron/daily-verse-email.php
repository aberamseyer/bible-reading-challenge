<?php

//
// Sends an email to users with the daily reading portion
//
// crontab entry: 45 * * * * php /home/bible-reading-challenge/cron/daily-verse-email.php

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites WHERE enabled = 1") as $site_id) {
  $site = BibleReadingChallenge\Site::get_site($site_id);
  $today = new DateTime('now', $site->TZ);
  // this cron runs every hour, we only want to send emails for the sites who's local time is 7:45 AM
  if ($today->format('G') != 7) {
    continue;
  }
  
  $schedule = $site->get_active_schedule();
  $recently = new Datetime($schedule->data('start_date'));
  $recently->modify('-3 months');
  
  $scheduled_reading = $schedule->get_reading($today);
  
  if (!$scheduled_reading) {
    die("nothing to do today!");
  }
  foreach($db->select("SELECT id, name, email, trans_pref, last_seen, streak FROM users WHERE site_id = ".$site->ID." AND email_verses = 1") as $user) {
    // if a user hasn't been active near the period of the schedule, we won't email them
    $last_seen_date = new Datetime('@'.$user['last_seen']);
    if ($last_seen_date < $recently) {
      continue;
    }
  
    // skip anyone who's already read today (ptl early risers!)
    if ($schedule->day_completed($user['id'], $scheduled_reading['id'])) {
      continue;
    }
    
    // format the user's name by using everything but the last name
    $name_arr = explode(' ', $user['name']);
    $name = array_pop($name_arr);
    if ($name_arr) {
      $name = implode(' ', $name_arr);
    }
  
    // total up the words in this day's reading
    $total_word_count = array_reduce(
      $scheduled_reading['passages'], 
      fn($acc, $cur) => $acc + $cur['word_count']);
    $minutes_to_read = ceil($total_word_count / ($site->data('reading_rate_wpm') ?: 240)); // words per minute, default to 240
  
    // BUILD EMAIL
    /* the banner image at the top of the email is part of the email template in Sendgrid */
  
    /* chapter contents */
    $html = $site->html_for_scheduled_reading($scheduled_reading, $user['trans_pref'], $scheduled_reading['complete_key'], $schedule, $today, true);
    /* unsubscribe */
    $html .= "<p style='text-align: center;'><small>If you would no longer like to receive these emails, <a href='".SCHEME."://".$site->DOMAIN."/?change_email_me=0'>click here to unsubscribe</a>.<small></p>";
    
    $streak = $user['streak'] > 1 ? "<p>🔥 Keep up your $user[streak]-day streak</p>" : "";
    
    usleep(1_000_000 / 5); // 5 per second at most
    $site->send_daily_verse_email($user['email'], $name, $minutes_to_read." Minute Read", $html, $streak);
  }
}