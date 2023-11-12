<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";


// edit own profile
if (isset($_POST['name'])) {
  $to_change = row("SELECT * FROM users WHERE id = ".(int)$my_id);
  if ($to_change) {
    $name = trim($_POST['name']);
    $emoji = trim($_POST['emoji']);

    if (!$name) {
      $_SESSION['error'] = "Name cannot be blank";
    }
    else if (!$emoji) {
      $_SESSION['error'] = "Emoji cannot be blank";
    }
    else if (grapheme_strlen($emoji) !== 1) {
      $_SESSION['error'] = "Enter exactly 1 character for the emoji";
    }
    else {
      update("users", [
        'name' => $name,
        'emoji' => $emoji
      ], "id = $to_change[id]");
      $_SESSION['success'] = "Updated profile";
      redirect();
    }
  }
}

$page_title = "Profile";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
echo "<p><a href='/auth/logout'>Logout</a></p>";
echo "<p>Email: <b>".html($me['email'])."</b><br>";
echo "Created: <b>".date('F j, Y', $me['date_created'])."</b><br>";
echo "<form method='post'>
  <label>Name <input type='text' name='name' minlength='1' value='".html($me['name'])."'></label>
  <label>My emoji
    <input type='text' name='emoji'
      minlength='1' maxlength='6'
      value='".html($me['emoji'])."'
      style='width: 70px'
    >
  </label>
  <button type='submit'>Save</button>
</form> ";

require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";