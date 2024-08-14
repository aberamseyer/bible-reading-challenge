<?php

//
// Sends notifications to users with the daily reading portion
//
// crontab entry: 45 * * * * php /home/bible-reading-challenge/cron/notifications.php
//


// Useful push notification notes:
// chrome://flags/#unsafely-treat-insecure-origin-as-secure
// chrome://serviceworker-internals/
// chrome://settings/content/siteDetails?site=http%3A%2F%2Fuoficoc.local%2F&search=notifications
// https://github.com/Minishlink/web-push-php-example/blob/master/src/send_push_notification.php
// https://developer.mozilla.org/en-US/docs/Web/API/Notification/requestPermission_static

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites WHERE enabled = 1") as $site_id) {
  $site = BibleReadingChallenge\SiteRegistry::get_site($site_id);
  $today = new DateTime('now', $site->TZ);
  // this cron runs every hour, we only want to send emails for the sites who's local time is 7:45 AM
  if ($today->format('G') != 7) {
    continue;
  }

  // get scheedule details
  $corp_schedule = $site->get_active_schedule();
  $recently = new Datetime($corp_schedule->data('start_date'));
  $recently->modify('-3 months');
  
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

    $personal_schedule = new BibleReadingChallenge\Schedule($site->ID, true, $user['id']);
    $personal_scheduled_reading = $personal_schedule->get_schedule_date($today);
  
    if ($corp_scheduled_reading &&
        $user['email_verses'] && 
        !$corp_schedule->day_completed($user['id'], $corp_scheduled_reading['id']) // skip anyone who's already read today (ptl early risers!)
      ) {
      
      $notification_info = $site->notification_info($user['name'], $corp_scheduled_reading);
            
      // EMAIL    
      $html = $user['streak'] > 1 ? "<p>🔥 Keep up your $user[streak]-day streak</p>" : "";

      $html .= "<p>Good morning $notification_info[name], here is the scheduled reading for today:</p>";
      /* chapter contents */
      $html .= $site->html_for_scheduled_reading($corp_scheduled_reading, $user['trans_pref'], $corp_scheduled_reading['complete_key'], $schedule, $today, true);
      /* unsubscribe */
      $html .= "<p style='text-align: center;'><small>If you would no longer like to receive these emails, <a href='".SCHEME."://".$site->DOMAIN."/today?change_subscription_type=none'>click here to unsubscribe</a>.<small></p>";
      $site->send_daily_verse_email($user['email'], $notification_info['minutes']." Minute Read", $html);
      printf("[v] Email sent for %s on site: %s|%s\n", $user['email'], $site->ID, $site->data('site_name'));
      usleep(floor(1_000_000 / 3)); // cooldown, just because it's nice to take a moment to rest :^)
    }

    // if, not else if. a user can receive both an email and a push notification.
    // also, a user may receive a push notification for his personal schedule and not the corporate schedule
    if ($user['sub_id']) {
      // PUSH NOTIFICATION
      // loop over the corporate and personal schedule, building a notification for their combined information
      $subscription = Subscription::create(json_decode($user['subscription'], true));
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
          $body_lines[] = "Good morning $notification_info[name]. Keep up your $user[streak]-day streak.";
        }
        $time += $notification_info['minutes'];
        
        $body_lines[] = $i === 0
          ? "We're reading ".$arr['sr']['reference']." today"
          : "You scheduled ".$arr['sr']['reference']." for yourself to read today.";
      }

      if ($body_lines) { // $body_lines will be empty if there are no scheduled readings
        $body_lines[] = "It'll take about $time minutes. Read now?";

        // we're not using queueNotification() and flushPooled() because when I was testing it,
        // there was no way to retrieve the sub_id out of the MessageSentReport in the flushPooled() callback
        // the library code said the payload was supposed to be in the object (via getRequestPayload()), but it was an empty string
        $report = $webPush->sendOneNotification(
          $subscription,
          json_encode([
            'title' => $site->data('short_name')." Daily Bible Reading",
            'options' => [
              'body' => implode("\n", $body_lines),
              'icon' => "/img/static/logo_".$site->ID."_512x512.png",
              'data' => [
                'link' => "https://".$site->DOMAIN."/today?today=".$today->format('Y-m-d'),
              ]
            ]
          ], JSON_UNESCAPED_SLASHES)
        );

        $endpoint = $report->getRequest()->getUri()->__toString();
        if ($report->isSuccess()) {
          echo "[v] Message sent successfully for subscription {$endpoint}.\n";
          $db->update('push_subscriptions', [
            'last_sent' => $today->format("Y-m-d H:i:s") // in local timezone
          ], "id = ".$user['sub_id']);
        }
        else {
          echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}\n";
          
          $db->query("DELETE FROM push_subscriptions WHERE id = ".$user['sub_id']);
          
          error_log(json_encode([
            'sub' => $user['sub_id'],
            'endpoint' => $endpoint,
            'isTheEndpointWrongOrExpired' => $report->isSubscriptionExpired(),
            'responseOfPushService' => $report->getResponse(),
            'getReason' => $report->getReason(),
            'getRequest' => print_r($report->getRequest(), true),
            'getResponse' => print_r($report->getResponse(), true),
          ]), JSON_UNESCAPED_SLASHES);
        }
      }
    }
  }
}