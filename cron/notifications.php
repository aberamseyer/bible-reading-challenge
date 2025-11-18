<?php

//
// Sends notifications to users with the daily reading portion
//


// Useful push notification notes:
// chrome://flags/#unsafely-treat-insecure-origin-as-secure
// chrome://serviceworker-internals/
// chrome://settings/content/siteDetails?site=http%3A%2F%2Fuoficoc.local%2F&search=notifications
// https://github.com/Minishlink/web-push-php-example/blob/master/src/send_push_notification.php
// https://developer.mozilla.org/en-US/docs/Web/API/Notification/requestPermission_static
// https://web-push-book.gauntface.com

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites WHERE enabled = 1 ORDER BY id ASC") as $site_id) {
  $site = BibleReadingChallenge\SiteRegistry::get_site($site_id);
  $today = new DateTime("now", $site->TZ);
  // this cron runs every hour, we only want to send emails for the sites who's local time is 7:45 AM
  if ($today->format('G') != 7) {
    continue;
  }
  echo date('Y-m-d H:i:s')." Starting notifications for site ID: $site_id".PHP_EOL;

  // get schedule details
  $corp_schedule = $site->get_active_schedule();
  $recently = new DateTime($corp_schedule->data('start_date'), $site->TZ);
  $recently->modify('-6 months');

  $corp_scheduled_reading = $corp_schedule->get_schedule_date($today);

  // web push init for site
  $auth = [
    'VAPID' => [
      'subject' => "https://".$site->DOMAIN,
      'publicKey' => $site->data('vapid_pubkey'),
      'privateKey' => $site->data('vapid_privkey'),
    ],
  ];
  $webPush = new WebPush($auth, [
    'TTL' => 60*60*24 - 1, // 1 day less 1 second
    'urgency' => 'normal',
    'topic' => 'daily-email',
    'timeout' => 5,
  ]);
  $webPush->setReuseVAPIDHeaders(true);

  foreach($db->select("
    SELECT id, name, email, trans_pref, last_seen, streak, email_verses, uuid
    FROM users
    WHERE site_id = ".$site->ID."
    ORDER BY id ASC") as $user) {
    // if a user hasn't been active near the period of the schedule, we won't notify them
    $last_seen_date = $user['last_seen']
      ? new DateTime('@'.$user['last_seen'], $site->TZ)
      : 0;
    if ($last_seen_date < $recently) {
      continue;
    }

    $personal_schedule = null;
    $personal_scheduled_reading = null;
    if ($site->data('allow_personal_schedules')) {
      $personal_schedule = new BibleReadingChallenge\Schedule($site->ID, true, $user['id']);
      $personal_scheduled_reading = $personal_schedule->get_schedule_date($today);
    }

    if ($corp_scheduled_reading &&
        $user['email_verses'] == 1 &&
        !$corp_schedule->day_completed($user['id'], $corp_scheduled_reading['id']) // skip anyone who's already read today (ptl early risers!)
      ) {

      $notification_info = $site->notification_info($user['name'], $corp_scheduled_reading);

      // EMAIL
      $html = $user['streak'] > 1 ? "<p>🔥 Keep up your $user[streak]-day streak</p>" : "";

      $html .= "<p>Good morning $notification_info[name], here is the scheduled reading for today:</p>";
      /* chapter contents */
      $email_id = uniqid("", true);
      $html .= $site->html_for_scheduled_reading($corp_scheduled_reading, $user['trans_pref'], $corp_scheduled_reading['complete_key'], $corp_schedule, $today, true, $email_id);
      /* unsubscribe */
      $html .= "<p style='text-align: center;'><small>If you would no longer like to receive these emails, <a href='".SCHEME."://".$site->DOMAIN."/today?change_email_me=0'>click here to unsubscribe</a>.<small></p>";
      $site->send_daily_verse_email($user, $notification_info['minutes']." Minute Read", $html, $corp_scheduled_reading['id'], $email_id);
      printf(date('Y-m-d H:i:s')." [v] Email sent for %s on site: %s|%s\n", $user['email'], $site->ID, $site->data('site_name'));
      usleep(floor(1_000_000 / 3)); // cooldown, just because it's nice to take a moment to rest :^)
    }

    // if, not else if. a user can receive both an email and a push notification.
    // also, a user may receive a push notification for his personal schedule and not the corporate schedule
    $subs = $db->select("
      SELECT *
      FROM push_subscriptions
      WHERE user_id = ".$user['id']);
    if ($subs) {
      // PUSH NOTIFICATION
      // loop over the corporate and personal schedule, building a notification for their combined information
      $body_lines = [];
      $time = 0;
      foreach([
        [
          's' => &$corp_schedule,
          'sr' => &$corp_scheduled_reading,
        ],
        [
          's' => &$personal_schedule,
          'sr' => &$personal_scheduled_reading,
        ]
      ] as $i => $arr) {
        if (!$arr['s'] || !$arr['sr'] || $arr['s']->day_completed($user['id'], $arr['sr']['id'])) {
          // if nothing is scheduled for today or we already completed the reading, skip
          continue;
        }

        $notification_info = $site->notification_info($user['name'], $arr['sr']);
        if (count($body_lines) === 0) {
          $body_lines[] = "Good morning $notification_info[name].".($user['streak'] > 1 ? "Keep up your $user[streak]-day streak." : "");
        }
        $time += $notification_info['minutes'];

        $body_lines[] = $i === 0
          ? "We're reading ".$arr['sr']['reference']." today"
          : "You scheduled ".$arr['sr']['reference']." for yourself to read today.";
      }

      if ($body_lines) { // $body_lines will be empty if there are no scheduled readings
        $body_lines[] = "It'll take about $time minutes. Read now?";

        foreach($subs as $sub_row) {
          // send a push to each device this user has subscribed
          $subscription = Subscription::create(json_decode($sub_row['subscription'], true), true);
          $report = $webPush->queueNotification(
            $subscription,
            json_encode([
              'title' => $site->data('short_name')." Daily Bible Reading",
              'options' => [
                'body' => implode("\n", $body_lines),
                'icon' => "https://".$site->DOMAIN."/img/static/logo_".$site->ID."_512x512.png",
                'data' => [
                  'link' => "https://".$site->DOMAIN."/today?today=".$today->format('Y-m-d'),
                ],
                'tag' => 'daily-reading-reminder', // causes new notifications to replace old ones
                'renotify' => true // causes replacement notifications to not silently replace old ones
              ]
            ], JSON_UNESCAPED_SLASHES)
          );
          $endpoint_host = parse_url($sub_row['endpoint'], PHP_URL_HOST);
          printf(date('Y-m-d H:i:s')." [v] Queued notification for user:%s, sub_id:%s on site: %s for endpoint:%s\n", $user['name'], $sub_row['id'], $site->ID, $endpoint_host);
        }
      }
    }
  }

  // send all the notifications for this site
  $webPush->flushPooled(function($report) {
    $db = \BibleReadingChallenge\Database::get_instance();

    $endpoint = $report->getRequest()->getUri()->__toString();
    $sub_id = $db->col("SELECT id FROM push_subscriptions WHERE endpoint = '".$db->esc($endpoint)."'");
    $encoded_report = json_encode([
      'isTheEndpointWrongOrExpired' => $report->isSubscriptionExpired(),
      'responseOfPushService' => $report->getResponse(),
      'getReason' => $report->getReason()
    ], JSON_UNESCAPED_SLASHES);

    if ($report->isSuccess()) {
      echo date('Y-m-d H:i:s')." [v] Message sent successfully for subscription {$endpoint}.\n";
      if ($sub_id) {
        $db->update('push_subscriptions', [
          'last_sent' => time()
        ], "id = ".$sub_id);
      }
    }
    else {
      echo date('Y-m-d H:i:s')." [x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}\n";

      if ($sub_id) {
        error_log("Failed to deliver sub_id:".$sub_id.":".$encoded_report);
        $db->query("DELETE FROM push_subscriptions WHERE id = ".$sub_id);
      }
    }
    $db->update("push_subscriptions", [
      'last_log' => $encoded_report,
      'last_updated' => time()
    ], 'id = '.$sub_id);
  }, 100, 10);
  usleep(100_000);
}
