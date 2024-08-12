<?php

$insecure = true;

require __DIR__."/../inc/init.php";

if ($me) {
  redirect("/");
}

if ($_POST['email']) {
  $_POST['email'] = strtolower($_POST['email']);
  // send forgot password email
  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email.";
  }
  else {
    $user_row = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND email = '".$db->esc($_POST['email'])."' AND email_verified = 1");
    if ($redis->get_forgot_password_token($user_row['id'])) {
      $_SESSION['error'] = "Email already sent.";
    }
    else if ($user_row) {
      $reset_token = uniqid("", true).uniqid("", true);
      $redis->set_forgot_password_token($user_row['id'], $reset_token);
      $site->send_forgot_password_email($user_row['email'], SCHEME."://".$site->DOMAIN."/auth/forgot?reset=$user_row[uuid]&key=$reset_token");
      $_SESSION['success'] = "Email sent!";
    }
    else {
      // ambiguous message given to people trying to game the system
      $_SESSION['email'] = "If ".html($_POST['email'])." email has been registered with us, an email with instructions has been sent to it.";
    }
  }
  redirect("/auth/login");
}
else if ($_REQUEST['reset']) {
  // check password reset link
  $user_row = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND uuid = '".$db->esc($_REQUEST['reset'])."' AND email_verified = 1");
  if (!$user_row) {
    $_SESSION['error'] = "Invalid reset link.";
  }
  else {
    $token = $redis->get_forgot_password_token($user_row['id']);
    if ($token !== $_REQUEST['key']) {
      $_SESSION['error'] = "Invalid reset link.";
    }
    else {
      // show form for new password
      if ($_POST['password']) {
        if ($_POST['password'] != $_POST['password_confirm']) {
          $_SESSION['error'] = "Passwords did not match.";
        }
        else if (strlen($_POST['password']) < 8) {
          $_SESSION['error'] = "Password too short.";
        }
        else {
          $db->update("users", [
            'password' => password_hash($_POST['password'], PASSWORD_BCRYPT)
          ], "id = ".$user_row['id']);
          $redis->delete_forgot_password_token($user_row['id']);

          $_SESSION['success'] = "Welcome back";
          log_user_in($user_row['id']);
        }

        if ($_SESSION['error']) {
          // on error, redirect to same page to show form again:
          redirect('?'.http_build_query(['reset' => $_POST['reset'], 'key' => $_POST['key']]));
        }

      }
      else {
        $page_title = "Reset Password";
        require DOCUMENT_ROOT."inc/head.php";
        ?>
          <div id='auth-wrap'>
            <div>
              <img alt='login' src='<?= $site->resolve_img_src('login') ?>' style='width: 280px'>
            </div>
            <div>
              <h4>Reset Password</h4>
              <p></p>
              <p>Or <a href='login'>log in</a></p>
              <form action='' method='post'>
                <input type='hidden' name='reset' value='<?= $_REQUEST['reset'] ?>'>
                <input type='hidden' name='key' value='<?= $_REQUEST['key'] ?>'>
                <p>
                  <input name='password' type='password' placeholder="New Password" required>
                  <small>At least 8 characters.</small>
                </p>
                <p>
                  <input name='password_confirm' type='password' placeholder="Confirm Password" required>
                </p>
                <button type="submit">Submit</button>
              </form>
            </div>
          </div>
        <?php
        require DOCUMENT_ROOT."inc/foot.php";
        die;
      }
    }
  }
  redirect();
}

$page_title = "Forgot Password";
require DOCUMENT_ROOT."inc/head.php";
?>
  <div id='auth-wrap'>
    <div>
      <img alt='login' src='<?= $site->resolve_img_src('login') ?>' style='width: 280px'>
    </div>
    <div>
      <h4>Forgot Password</h4>
      <p></p>
      <p>Or <a href='login'>log in here</a>.</p>
      <form action='' method='post'>
        <p>
          <input name='email' type='text' placeholder="Email" required>
        </p>
        <button type="submit">Submit</button>
      </form>
    </div>
  </div>
<?php
require DOCUMENT_ROOT."inc/foot.php";