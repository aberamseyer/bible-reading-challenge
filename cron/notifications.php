<?php

//
// Sends an email to users with the daily reading portion
//
// crontab entry: 45 * * * * php /home/bible-reading-challenge/cron/notifications.php


//
// Useful push notification notes:
// chrome://flags/#unsafely-treat-insecure-origin-as-secure
// chrome://serviceworker-internals/
// chrome://settings/content/siteDetails?site=http%3A%2F%2Fuoficoc.local%2F&search=notifications
// https://github.com/Minishlink/web-push-php-example/blob/master/src/send_push_notification.php
// https://developer.mozilla.org/en-US/docs/Web/API/Notification/requestPermission_static
//
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites WHERE enabled = 1") as $site_id) {
  $site = BibleReadingChallenge\Site::get_site($site_id);
  $today = new DateTime('now', $site->TZ);
  // this cron runs every hour, we only want to send emails for the sites who's local time is 7:45 AM
  if ($today->format('G') != 7) {
    continue;
  }

  // get scheedule details
  $schedule = $site->get_active_schedule();
  $recently = new Datetime($schedule->data('start_date'));
  $recently->modify('-3 months');
  
  $scheduled_reading = $schedule->get_schedule_date($today);
  
  if (!$scheduled_reading) {
    die("nothing to do today!");
  }
  
  // web push init for site
  $auth = [
    'VAPID' => [
      'subject' => $site->DOMAIN,
      'publicKey' => $site->data('vapid_pubkey'), 
      'privateKey' => $site->data('vapid_privkey'),
    ],
  ];
  $webPush = new WebPush($auth, [
    'TTL' => 60*60*24 - 1, // 1 day less 1 second
    'urgency' => 'low',
    'topic' => 'daily-email',
    'timeout' => 5,
  ]);
  $webPush->setReuseVAPIDHeaders(true);


  foreach($db->select("
    SELECT u.id, u.name, u.email, u.trans_pref, u.last_seen, u.streak, ps.subscription, u.email_verses, ps.id sub_id
    FROM users u
    LEFT JOIN push_subscriptions ps ON ps.user_id = u.id
    WHERE u.site_id = ".$site->ID." AND (
      u.email_verses = 1 OR ps.id
    )") as $user) {
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
  
    if ($user['email_verses']) {
      // EMAIL
      /* the banner image at the top of the email is part of the email template in Sendgrid */
    
      /* chapter contents */
      $html = $site->html_for_scheduled_reading($scheduled_reading, $user['trans_pref'], $scheduled_reading['complete_key'], $schedule, $today, true);
      /* unsubscribe */
      $html .= "<p style='text-align: center;'><small>If you would no longer like to receive these emails, <a href='".SCHEME."://".$site->DOMAIN."/today?change_subscription_type=0'>click here to unsubscribe</a>.<small></p>";
      
      $streak = $user['streak'] > 1 ? "<p>🔥 Keep up your $user[streak]-day streak</p>" : "";
      
      usleep(1_000_000 / 5); // 5 per second at most
      $site->send_daily_verse_email($user['email'], $name, $minutes_to_read." Minute Read", $html, $streak);
    }

    if ($user['sub_id']) {
      // PUSH NOTIFICATION
      $subscription = Subscription::create(json_decode($user['subscription'], true));
      $webPush->queueNotification(
          $subscription,
          json_encode([
            'title' => "something to read today",
            'options' => [
              'body' => 'body content',
              'data' => [
                'link' => SCHEME."://".$site->DOMAIN."/today?today=".$scheduled_reading['date']
              ]
            ]
          ])
      );
    }
  }

  $webPush->flushPooled(function($report) use ($db, $sub) {
    $endpoint = $report->getRequest()->getUri()->__toString();

    if ($report->isSuccess()) {
      echo "[v] Message sent successfully for subscription {$endpoint}.";
      $db->update('push_subscriptions', [
        'last_sent' => date("Y-m-d H:i:s")
      ], "id = ".$sub['id']);
    }
    else {
      echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}";
      
      $db->query("DELETE FROM push_subscriptions WHERE id = ".$sub['id']);
      
      error_log(json_encode([
        'sub' => $sub,
        'endpoint' => $endpoint,
        'isTheEndpointWrongOrExpired' => $report->isSubscriptionExpired(),
        'responseOfPushService' => $report->getResponse(),
        'getReason' => $report->getReason(),
        'getRequest' => print_r($report->getRequest(), true),
        'getResponse' => print_r($report->getResponse(), true),
      ]));
    }
  }, 20, 10);

}