<?php

$insecure = true;
require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if ($me) {
  redirect("/");
}

$csrf = $_SESSION['csrf_login'];
if (!$csrf) {
  $csrf = $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
}

if ($_POST['email'] && $_POST['password'] && $_POST['csrf'] == $csrf) {
  $_POST['email'] = strtolower($_POST['email']);
  $user_row = $db->row("SELECT * FROM users WHERE email = '".$db->esc($_POST['email'])."' AND email_verified = 1");
  if (!$user_row) {
    $_SESSION['error'] = "Invalid credentials.";
  }
  else {
    if (PROD && !password_verify($_POST['password'], $user_row['password'])) {
      $_SESSION['error'] = "Bad username or password.";
    }
    else {
      if ($user_row['email_verified'] != 1) {
        $_SESSION['error'] = "Please confirm your email.";
      }
      else {
        $_SESSION['succes'] = "Welcome back!";
        log_user_in($user_row['id']);        
      }
    }
  }
}

$page_title = "Log in";
$hide_title = true;
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
?>
  <div id='auth-wrap'>
    <div>
      <img src='<?= $site->resolve_img_src('login') ?>' style='width: 280px'>
    </div>
    <div>
      <img src='/img/static/start-reading.svg' style='width: 240px'>
      <p></p>
      <form action='' method='post'>
        <input type='hidden' name='csrf' value='<?= $csrf ?>'>
        <p>
          <input name='email' type='text' placeholder="Email" required><br>
          <input name='password' type='password' placeholder="Password" required>
        </p>
        <p><button type="submit">Log in</button> <a href='forgot'>Forgot password</a></p>
        <p>Or <a href='register'>register here</a></p>
      </form>
      <hr>
      <div id="g_id_onload"
        data-client_id="<?= $site->env('GOOGLE_CLIENT_ID') ?>"
        data-context="signin"
        data-ux_mode="popup"
        data-login_uri="https://<?= $site->DOMAIN ?>/auth/oauth"
        data-auto_prompt="false">
      </div>
      <div class="g_id_signin center"
        data-type="standard"
        data-shape="pill"
        data-theme="outline"
        data-text="signin_with"
        data-size="large"
        data-logo_alignment="left">
      </div>
      <?php $add_to_foot .= "<script src='https://accounts.google.com/gsi/client' async></script>"; ?>
    </div>
  </div>
<?php
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";