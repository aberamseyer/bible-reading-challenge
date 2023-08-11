<?php

$insecure = true;

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if ($me) {
  redirect("/");
}

if ($_POST['email']) {
  // send forgot password email
  if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email.";
  }
  else {
    $user_row = row("SELECT * FROM users WHERE email = '".db_esc($_POST['email'])."'");
    if ($user_row['forgot_password_expires'] && time() <= date('U', $user_row['forgot_password_expires'])) {
      $_SESSION['error'] = "Email already sent.";
    }
    else if ($user_row) {
      $reset_token = uniqid("", true).uniqid("", true);
      update("users", [
        'forgot_password_token' => $reset_token,
        'forgot_password_expires' => time() + 60 * 60 * 2 // expires in two hours
      ], "id = ".$user_row['id']);
      send_forgot_password_email($user_row['email'], "https://brc.ramseyer.dev/auth/forgot?reset=$user_row[uuid]&key=$reset_token");
      $_SESSION['error'] = "Email sent!";
    }
    else {
      $_SESSION['error'] = "If ".html($_POST['email'])." email has been registered with us, an email with instructions has been sent to it.";
    }
  }
}
else if ($_REQUEST['reset']) {
  // check password reset link
  $user_row = row("SELECT * FROM users WHERE uuid = '".db_esc($_REQUEST['reset'])."'");
  if (!$user_row || $user_row['forgot_password_token'] != $_REQUEST['key']) {
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
      else if (time() > date('U', $user_row['forgot_password_expires'])) {
        $_SESSION['error'] = "Password reset link expired.";
      }
      else {
        update("users", [
          'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
          'forgot_password_token' => '',
          'forgot_password_expires' => ''
        ], "id = ".$user_row['id']);
        redirect('login');
      }
    }
    else {
      if (time() > date('U', $user_row['forgot_password_expires'])) {
        $_SESSION['error'] = "Password reset link expired.";
      }
      else {
        $page_title = "Reset Password";
        require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
        ?>
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
        <?php
        require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";
        die;
      }
    }
  }
}

$page_title = "Forgot Password";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
?>
    <p>Or <a href='login'>log in</a> here.</p>
    <form action='' method='post'>
      <p>
        <input name='email' type='text' placeholder="Email" required>
      </p>
      <button type="submit">Submit</button>
    </form>
<?php
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";