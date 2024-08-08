<?php

//
// Creates VAPID keys for sites without them
// 

require __DIR__."/../www/inc/env.php";

$db = \BibleReadingChallenge\Database::get_instance();

foreach($db->cols("SELECT id FROM sites WHERE vapid_pubkey IS NULL") as $site_id) {
  $keys = Minishlink\WebPush\VAPID::createVapidKeys();
  
  $db->update('sites', [
    'vapid_pubkey' => $keys['publicKey'],
    'vapid_privkey' => $keys['privateKey']
  ], "id = $site_id");
  echo "Updated site: $site_id".PHP_EOL;
}
