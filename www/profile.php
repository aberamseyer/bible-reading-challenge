<?php

require __DIR__."/inc/init.php";

// edit own profile
if (isset($_POST['name'])) {
  $to_change = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND id = ".$my_id);
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
      $db->update("users", [
        'name' => $name,
        'emoji' => $emoji
      ], "id = $to_change[id]");
      $_SESSION['success'] = "Updated profile";
      redirect();
    }
  }
}

$page_title = "Profile";
$show_title = true;
require DOCUMENT_ROOT."inc/head.php";
echo "<p><a href='/auth/logout'>↩ Logout</a></p>";

echo "
<div>
  <h3>Progress</h3>";
$badges = badges_for_user($my_id);
$badges_html = badges_html($badges);
if (!$badges_html) {
  echo "Badges for books you complete will be displayed here.";
}
else {
  echo $badges_html;
}
echo "</div>";

$stats = $site->user_stats($me['id'], $redis);
echo "
  <div class='two-columns'>
    <div>
      <h5>My Stats</h5>
      <ul>
        <li>Current Challenge progress: <b>".($stats['progress'] ? round($stats['progress'], 2) : '-')."%</b></li>
        <li>Current / Longest streak: <b>$me[streak] day".xs($stats['streak'])."</b> / <b>$stats[max_streak] day".xs($stats['max_streak'])."</b></li>
        <li>Chapters I've read: <b>".number_format($stats['chapters_ive_read'])."</b></li>
        <li>Words I've read: <b>".number_format($stats['words_ive_read'])."</b></li>
      </ul>
    </div>
    <div>
      <h5>Club Stats</h5>
      <ul>
        <li>All-club chapters read: <b>".number_format($stats['all_club_chapters_read'])."</b></li>
        <li>All-club words read: <b>".number_format($stats['all_club_words_read'])."</b></li>
      </ul>
    </div>
  </div>";

// mountain
$emojis = $schedule->emoji_data($me['id']);
echo "
  <h5 class='text-center'>Top 20 Readers (and you)</h5>";

echo $site->mountain_for_emojis($emojis, $me['id']);

echo "<p>
<div class='two-columns'>
  <div>
    <h6 class='text-center'>Progress</h6>
    ".$site->progress_canvas($stats['progress_graph_data'])."
  </div>
  <div>
    <h6 class='text-center'>Days read each week</h6>
    ".$site->weekly_progress_canvas($me['id'])."
  </div>
</div>
</p>";

echo "<form method='post'>
  <fieldset>
    <legend>Edit Account</legend>";
echo "<p>Email: <b>".html($me['email'])."</b><br>";
echo "Joined: <b>".date('F j, Y', $me['date_created'])."</b><br></p>";
echo "
  <label>My name: <input type='text' name='name' minlength='1' value='".html($me['name'])."'></label>
  <label>My emoji: 
    <input type='text' name='emoji'
      minlength='1' maxlength='6'
      value='".html($me['emoji'])."'
      style='width: 70px'
    >
  </label>
  <button type='submit'>Save</button>
  </fieldset>
</form>";

$add_to_foot .= 
  chartjs_js().
  cached_file('js', '/js/profile.js');

require DOCUMENT_ROOT."inc/foot.php";

