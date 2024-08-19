<?php

$insecure = true;
require __DIR__."/../../inc/init.php";

load_env();

// Firebase is just a library for parsing JWT tokens. We're not actually using firebase here.
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

if (!$_POST['state'] || !$_POST['code'] || !$_POST['id_token']) {
  $_SESSION['error'] = "Invalid authentication response.";
}
else {
  $keyset = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);
  
  $jwt = $_REQUEST['id_token'];
  
  $token_parts = explode('.', $jwt);
  $header = json_decode(base64_decode($token_parts[0]), true);
  
  $kid = $header["kid"];
  
  try {
    JWT::$leeway = 60;
    $payload = (array) JWT::decode($jwt, JWK::parseKeySet($keyset));
  } catch (\Exception $e) {
    error_log("Token validation failed: " . $e->getMessage());
    $_SESSION['error'] = "Authentication response could not be validated.";
    session_write_close();
    redirect('/');
  }
  
  // sucessful decoding with valid signature
  if ($payload['nonce'] !== session_id()) {
    $_SESSION['error'] = "Session mismatch.";
  }
  else if (!$payload['email']) {
    $_SESSION['error'] = "Sign in with Apple is not supported for your account.";
  }
  else {
    // https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_rest_api/authenticating_users_with_sign_in_with_apple#3383773
    // Users can change their email address or start/stop using sign in with apple. The documentation here says that the 'sub' claim will be a stable, app-specific uuid
    // tied to a user regardless of email address or name changes
    $user_id = $db->col("
      SELECT id
      FROM users
      WHERE site_id = ".$site->ID." AND (
        uuid = '".$db->esc($payload['sub'])."' OR
        email = '".$db->esc($payload['email'])."'
      )");
    if ($user_id) {
      // account exists
      $_SESSION['success'] = "Welcome back!";
      $db->update('users', [
        'uuid' => $payload['sub'],
        'email' => $payload['email'], // overwrite the email to whatever apple told us this user has
        'email_verified' => 1         // assume it's verified
      ], "id = $user_id");
      $redis->delete_verify_email_key($user_id); // if there's an existing one out there somewhere
      log_user_in($user_id);
    }
    else {
      // Sign in with apple only returns the "user" JSON object on the first successfulauth;enntication
      $user_arr = json_decode($_REQUEST['user'], true);
      if (!$user_arr) {
        $_SESSION['error'] = "Authentication response from Apple did not contain sign in details.";
      }
      else {
        $name = $user_arr["name"]["firstName"]." ".$user_arr["name"]["lastName"];
        $ret = $site->create_user(
          $payload["email"],
          $name,
          false,
          false,
          true,
          $payload["sub"]
        );
        $user_id = $ret["insert_id"];
        $_SESSION["success"] = "Welcome to the challenge!";
        log_user_in($user_id);
      }
    }
  }
}

session_write_close();

redirect('/');