<?php

require __DIR__."/../inc/init.php";

$subscription = json_decode(file_get_contents('php://input'), true);

if (!isset($subscription['endpoint'])) {
  echo 'Error: not a subscription';
  return;
}

$method = $_SERVER['REQUEST_METHOD'];

$existing_sub = $db->row("SELECT * FROM push_subscriptions WHERE endpoint = '".$db->esc($subscription['endpoint'] ?: '')."'");

function insert_sub($user_id, $subscription) {
  $db = BibleReadingChallenge\Database::get_instance();
  $db->insert('push_subscriptions', [
    'user_id' => $user_id,
    'endpoint' => $subscription['endpoint'],
    'subscription' => json_encode($subscription, JSON_UNESCAPED_SLASHES)
  ]);
}

switch ($method) {
  case 'POST':
    // create a new subscription entry in your database
    if ($existing_sub) {
      insert_sub($me['id'], $subscription);
    }
    echo "Success: created subscription.";
    break;
  case 'PUT':
    // update the key and token of subscription corresponding to the endpoint
    if ($existing_sub) {
      $db->update('push_subscriptions', [
        'subscription' => json_encode($subscription, JSON_UNESCAPED_SLASHES)
      ], "id = ".$existing_sub['id']);
      echo "Success: updated subscription.";
    }
    else {
      insert_sub($me['id'], $subscription);
      echo "Success: no matching subscription, inserted subscription.";
    }
    break;
  case 'DELETE':
    // delete the subscription corresponding to the endpoint
    if ($existing_sub) {
      $db->query("DELETE FROM push_subscriptions WHERE id = ".$existing_sub['id']);
      echo "Success: deleted subscription.";
    }
    else {
      echo "Error: no existing subscription.";
    }
    break;
  default:
    echo "Error: method not handled";
    return;
}