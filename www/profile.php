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

echo "
<div>
  <h3>Progress</h3>";
$badges = badges_html_for_user($my_id);
if (!$badges) {
  echo "Badges for books you complete will be displayed here.";
}
else {
  echo $badges;
}

$words_read = words_read($me, $schedule['id']);

$total_words_in_challenge = total_words_in_schedule($schedule['id']);

$canvas_width = 225;
echo "</div>
<div class='two-columns'>
  <div>
    <h5>Current Challenge Stats</h5>
    <ul>
      <li>".round($words_read / $total_words_in_challenge * 100, 2)."% Complete</li>
      <li>Current / Longest streak: $me[streak] day".xs($me['streak'])." / $me[max_streak] day".xs($me['max_streak'])."</li>
      <li>Chapters I've read: ".number_format(col(
        ($chp_qry = 
          "SELECT SUM(JSON_ARRAY_LENGTH(passage_chapter_ids))
          FROM schedule_dates sd
          JOIN read_dates rd ON rd.schedule_date_id = sd.id"
        )."
        WHERE rd. user_id = $my_id"))."</li>
      <li>Words I've read: ".number_format($words_read)."</li>
    </ul>
  </div>
  <div>
    <h5>Cross Challenge Stats</h5>
    <ul>
      <li>All-club chapters read: ".number_format(col($chp_qry))."</li>
      <li>All-club words read: ".number_format(words_read())."</li>
    </ul>
  </div>
</div>";

// mountain
$total_words_in_schedule = total_words_in_schedule($schedule['id']);
$emojis = select("
  SELECT ROUND(SUM(word_count) * 1.0 / $total_words_in_schedule * 100, 2) percent_complete, u.emoji, u.id, u.name
  FROM schedule_dates sd
  JOIN JSON_EACH(passage_chapter_ids)
  JOIN chapters c on c.id = value
  JOIN read_dates rd ON sd.id = rd.schedule_date_id
  JOIN users u ON u.id = rd.user_id
  WHERE sd.schedule_id = $schedule[id]
  GROUP BY u.id
  ORDER BY 
    CASE WHEN u.id = $me[id] THEN 9999999999 -- sort me first, then the top readers
    ELSE COUNT(*)
    END DESC
  LIMIT 20");
echo "
  <h5 class='text-center'>Top 20 Readers (and you)</h5>";

  mountain_for_emojis($emojis, $me['id']);

  echo "<p>
  <h6 class='text-center'>Days read each week</h6>
  <div class='center'>";
  echo weekly_progress_canvas($me['id'], $schedule);
  echo "<script>".weekly_progress_js(300, 150)."</script>";
  echo "</div>
  </p>";

echo "<h2>Edit Profile</h2>";
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
</form>";


echo "<script src='/js/profile.js'></script>";

require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";