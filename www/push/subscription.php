<?php

require __DIR__."/../inc/init.php";

$subscription = json_decode(file_get_contents('php://input'), true);

if (!isset($subscription['endpoint'])) {
  echo 'Error: not a subscription';
  return;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
  case 'POST':
    // create a new subscription entry in your database
    $db->query("DELETE FROM push_subscriptions WHERE user_id = ".$me['id']);
    $db->insert('push_subscriptions', [
      'user_id' => $me['id'],
      'subscription' => json_encode($subscription),
    ]);
    echo "Success: created subscription.";
    break;
  case 'PUT':
    // update the key and token of subscription corresponding to the endpoint
    $db->update('push_subscriptions', [
      'subscription' => json_encode($subscription),
    ], "user_id = ".$me['id']);
    echo "Success: created subscription.";
    break;
  case 'DELETE':
    // delete the subscription corresponding to the endpoint
    $db->query("DELETE FROM push_subscriptions WHERE user_id = ".$me['id']);
    echo "Success: deleted subscription.";
    break;
  default:
    echo "Error: method not handled";
    return;
}