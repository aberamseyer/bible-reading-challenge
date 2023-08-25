<?php

require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

// set translation, update it if the select box changed
$tranlsations = ['kjv', 'esv', 'asv', 'niv', 'nlt'];
if ($_REQUEST['change_trans'] || $_REQUEST['change_email_me']) {
  $new_trans = $_REQUEST['change_trans'];
  if (!in_array($new_trans, $tranlsations)) {
    $new_trans = 'esv';
  }
  $me['trans_pref'] = $new_trans;
  $me['email_verses'] = array_key_exists('change_email_me', $_REQUEST) ? 1 : 0;
  update("users", [
    'trans_pref' => $new_trans,
    'email_verses' => $me['email_verses']
  ], "id = ".$my_id);
}
$trans = $me['trans_pref'];

// figure out what today is (if overridden)
$today = new Datetime();
if (strtotime($_GET['today'])) {
  $override_date = new Datetime($_GET['today']);
  $today = allowed_schedule_date($override_date)
    ? $override_date
    : $today;
}
$scheduled_reading = get_reading($today);

// determine if today's reading has been completed
$today_completed = num_rows("
  SELECT id
  FROM read_dates
  WHERE schedule_date_id = ".($scheduled_reading['id'] ?: 0)."
    AND user_id = ".$my_id);

// make sure they didn't read too fast 🤔
if ($_REQUEST['done'] && !$today_completed && $scheduled_reading) {
  // count the words in today's reading
  $scheduled_reading_chapter_ids = array_map(fn($passage) => $passage['chapter']['id'], $scheduled_reading['passages']);
  $word_length = count(
    explode(' ', 
      implode(' ', 
        cols("SELECT $trans FROM verses WHERE chapter_id IN(".implode(',', $scheduled_reading_chapter_ids).")")
      )
    )
  );
  if (
    $_REQUEST['key'] != $me['email_verses_key'] || // if it's from an email, ignore time restriction
    (
      ($time - $_SESSION['started_reading']) / 60 // how many minutes we've been on the page
      < $word_length / 275           // how many words we can read each minute (avg is 250)
    )
  ) {
    $_SESSION['error'] = "You read a little too fast there. Take the time to enjoy the passage!";
  }
  else {
    // finished reading
    insert("read_dates", [
      'user_id' => $my_id,
      'schedule_date_id' => $scheduled_reading['id'],
      'timestamp' => $time
    ]);
    $today_completed = true;
  }
}
else {
  // reset the timer for when we started reading only if we did not try to submit too soon
  $_SESSION['started_reading'] = $time;
}

$page_title = "Read";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

if ($today_completed) {
  echo "<blockquote><img class='icon' src='/img/circle-check.svg'> You've completed the reading for today!</blockquote>";
}
// header with translation selector and email pref
echo "<div id='date-header'>
  <h5>".$today->format("l, F j")."</h5>
  <form style='display: flex; width: 20rem; justify-content: space-between; align-items: center;'>
    <label>
      Email me &nbsp;&nbsp;
      <input type='checkbox' name='change_email_me' value='1' ".($me['email_verses'] ? 'checked' : '')." onchange='this.form.submit()'>
    </label>
    <input type='hidden' name='today' value='".$today->format('Y-m-d')."'>
    <select name='change_trans' onchange='this.form.submit();'>";
    foreach($tranlsations as $trans_opt)
      echo "
        <option value='$trans_opt' ".($trans_opt == $trans ? "selected" : "").">".strtoupper($trans_opt)."</option>";
  echo "</select>
  </form>
</div>";

echo html_for_scheduled_reading($scheduled_reading, $trans);


require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";