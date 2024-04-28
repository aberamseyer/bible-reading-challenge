<?php

require_once $_SERVER['DOCUMENT_ROOT']."../vendor/autoload.php";

$insecure = true;
require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$_POST['g_csrf_token'] || $_POST['g_csrf_token'] != $_COOKIE['g_csrf_token']) {
  $_SESSION['error'] = "Invalid CSRF token";
}
else {
  $client = new Google_Client(['client_id' => GOOGLE_CLIENT_ID]);
  $payload = $client->verifyIdToken($_POST['credential']);
  if (!$payload) {
    $_SESSION['error'] = "Problem with Google Sign in";
  } else {
    $payload['email'] = strtolower($payload['email']);
    $user_row = $db->row("SELECT * FROM users WHERE email = '".$db->esc($payload['email'])."'");
    // account doesn't exist, create
    if (!$user_row) {
      $id = $db->insert("users", [ 
        'site_id' => $site->ID,
        'uuid' => uniqid(),
        'name' => $payload['name'],
        'email' => $payload['email'],
        'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), // assign random password until the user changes it himself
        'email_verified' => 1,
        'trans_pref' => 'rcv',
        'date_created' => time(),
        'email_verify_token' => uniqid("", true).uniqid("", true),
        'emoji' => $site->data('default_emoji')
      ]);
    }
    else {
      // account exists
      $id = $user_row['id'];
      if (!$user_row['email_verified']) {
        $db->update('users', [
          'email_verified' => 1
        ], 'id = '.$user_row['id']);
      }
    }

    log_user_in($id);
  }
}

session_write_close();

redirect('/');