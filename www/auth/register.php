<?php

$insecure = true;

require $_SERVER['DOCUMENT_ROOT']."/inc/init.php";

if ($me) {
  redirect("/");
}

// confirm user from email link
if ($_REQUEST['confirm']) {
  $user_row = $db->row("SELECT * FROM users WHERE uuid = '".$db->esc($_REQUEST['confirm'])."'");
  if (!$user_row || $user_row['email_verify_token'] != $_REQUEST['key']) {
    $_SESSION['error'] = "Invalid confirmation link.";
  }
  else {
    $db->update("users", [
      "email_verified" => 1
    ], "id = ".$user_row['id']);
    redirect("/auth/login");
  }
}
else if ($_POST['email']) {
  $_POST['email'] = strtolower($_POST['email']);
  // register user
  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email.";
  }
  else if ($_POST['password'] != $_POST['password_confirm']) {
    $_SESSION['error'] = "Passwords did not match.";
  }
  else if (strlen($_POST['password']) < 8) {
    $_SESSION['error'] = "Password too short.";
  }
  else if ($db->row("SELECT * FROM users WHERE email = '".$db->esc($_POST['email'])."'")) {
    $_SESSION['error'] = "Email already registered.";
  }
  else {
    $uuid = uniqid();
    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $verify_token = uniqid("", true).uniqid("", true);
    $db->insert("users", [ 
      'site_id' => $site->ID,
      'uuid' => $uuid,
      'name' => $_POST['name'],
      'email' => $_POST['email'],
      'password' => $hash,
      'trans_pref' => 'rcv',
      'date_created' => time(),
      'email_verify_token' => $verify_token,
      'emoji' => $site->data('default_emoji')
    ]);
    $site->send_register_email($_POST['email'], SCHEME."://".$site->DOMAIN."/auth/register?confirm=$uuid&key=$verify_token");
    $_SESSION['info'] = "<img class='icon' src='/img/email.svg'>Registration email sent. Check your inbox!"; // TODO email image
  }
}

$page_title = "Register";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
?>
  <div id='auth-wrap'>
    <div>
      <p>Or <a href='login'>log in</a></p>
      <form action='' method='post'>
        <p>
          <input name='name' type='text' placeholder="Name" value="<?= $_POST['name'] ?>" minlength='1' required>
        </p>
        <p>
          <input name='email' type='text' placeholder="Email" value="<?= $_POST['email'] ?>">
        </p>
        <p>
          <input name='password' type='password' placeholder="Password" required><br>
          <small>At least 8 characters</small>
        </p>
        <p>
          <input name='password_confirm' type='password' placeholder="Confirm password" required><br>
        </p>
        <button type="submit">Submit</button>
      </form>
      <hr>
      <div id="g_id_onload"
        data-client_id="<?= GOOGLE_CLIENT_ID ?>"
        data-context="signup"
        data-ux_mode="popup"
        data-login_uri="https://<?= $site->DOMAIN ?>/auth/oauth"
        data-auto_prompt="false">
      </div>
      <div class="g_id_signin center"
        data-type="standard"
        data-shape="pill"
        data-theme="outline"
        data-text="signup_with"
        data-size="large"
        data-logo_alignment="left">
      </div>
      <script src="https://accounts.google.com/gsi/client" async></script>
    </div>
    <div></div>
  </div>
<?php
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";