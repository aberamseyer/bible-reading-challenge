<?php


require __DIR__."/../www/inc/env.php";

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites WHERE enabled = 1") as $site_id) {
  $subs = $db->select("
    SELECT ps.id, ps.subscription, u.name, u.streak
    FROM push_subscriptions ps
    JOIN users u ON u.id = ps.user_id
    WHERE site_id = $site_id
    LIMIT 1");
  $site = BibleReadingChallenge\Site::get_site($site_id);

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

  foreach($subs as $sub) {
    $subscription = Subscription::create(json_decode($sub['subscription'], true));
    $webPush->queueNotification(
        $subscription,
        '{"message":"Hello from Abe! 👋"}',
    );
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