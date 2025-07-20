<?php

$insecure = true;
require __DIR__."/../../inc/init.php";

if (!$_POST['g_csrf_token'] || $_POST['g_csrf_token'] != $_COOKIE['g_csrf_token']) {
  $_SESSION['error'] = "Invalid CSRF token";
}
else {
  $client = new Google_Client(['client_id' => getenv("GOOGLE_CLIENT_ID") ]);
  $payload = $client->verifyIdToken($_POST['credential']);
  if (!$payload) {
    $_SESSION['error'] = "Problem with Google Sign in";
  }
  else {
    $payload['email'] = strtolower($payload['email']);
    $user_row = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND email = '".$db->esc($payload['email'])."'");
    // account doesn't exist, create
    if (!$user_row) {
      // oauth makes the email already verified, so no email sent or verify key cached
      $ret = $site->create_user($payload['email'], $payload['name'], false, false, true);
      $id = $ret['insert_id'];
      $_SESSION['success'] = "Welcome to the challenge!";
    }
    else {
      // account exists
      $id = $user_row['id'];
      if (!$user_row['email_verified']) {
        $db->update('users', [
          'email_verified' => 1
        ], 'id = '.$user_row['id']);
      }
      $_SESSION['success'] = "Welcome back!";
    }

    log_user_in($id);
  }
}

session_write_close();

redirect('/');
