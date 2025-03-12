<?php

$insecure = true;
require __DIR__."/../inc/init.php";

if ($me) {
  redirect("/");
}

$csrf = $_SESSION['csrf_login'];
if (!$csrf) {
  $csrf = $_SESSION['csrf_login'] = bin2hex(random_bytes(16));
}

if ($_POST['email'] && $_POST['password'] && $_POST['csrf'] == $csrf) {
  $_POST['email'] = strtolower($_POST['email']);
  $user_row = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND email = '".$db->esc($_POST['email'])."' AND email_verified = 1");
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
        $_SESSION['success'] = "Welcome back!";
        log_user_in($user_row['id']);        
      }
    }
  }
}

$page_title = "Log in";
require DOCUMENT_ROOT."inc/head.php";
?>
  <div id='auth-wrap'>
    <div>
      <img alt='login' src='<?= $site->resolve_img_src('login') ?>' style='width: 280px'>
    </div>
    <div>
      <img alt='start reading' src='/img/static/start-reading.svg' style='width: 240px'>
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
        data-client_id="<?= GOOGLE_CLIENT_ID ?>"
        data-context="signin"
        data-ux_mode="popup"
        data-login_uri="https://<?= $site->DOMAIN ?>/auth/oauth/google"
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
      <?php
        if ($site->env('APPLE_SIGNIN_KEY_ID')) {
          echo "
          <div 
            class='center'
            style='margin-top: 7px;'
            id='appleid-signin'
            data-mode='center-align'
            data-type='sign in'
            data-color='black'
            data-border='true'
            data-width='90%'
            data-height='30px'
          ></div>";
          $add_to_foot .= "
            <script type='text/javascript' src='https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js'></script>
            <script type='text/javascript'>
              if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
              // dark mode
                document.getElementById('appleid-signin').setAttribute('data-color', 'white')
              }
              AppleID.auth.init({
                clientId: '".$site->env('APPLE_SIGNIN_CLIENT_ID')."',
                scope: 'email name',
                redirectURI: 'https://".$site->DOMAIN."/auth/oauth/apple',
                state: '".time()."',
                nonce: '".session_id()."',
                usePopup: false
              });
            </script>
            ";
        }
      ?>
      <div class='text-center'><small><a href='/privacy'>Privacy Policy</a></small></div>
      <?php $add_to_foot .= "<script src='https://accounts.google.com/gsi/client' async></script>"; ?>
    </div>
  </div>
<?php
require DOCUMENT_ROOT."inc/foot.php";