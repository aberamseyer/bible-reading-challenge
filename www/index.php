<?php

  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  // set translation, update it if the select box changed
  $tranlsations = ['kjv', 'esv', 'asv', 'niv', 'nlt'];
  if ($_REQUEST['change_trans']) {
    $new_trans = $_REQUEST['change_trans'];
    if (!in_array($new_trans, $tranlsations)) {
      $new_trans = 'esv';
    }
    $me['trans_pref'] = $new_trans;
    update("users", [
      'trans_pref' => $new_trans
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
  if ($_POST['done'] && !$today_completed && $scheduled_reading) {
  // make sure they didn't read too fast 🤔
    $scheduled_reading_chapter_ids = array_map(fn($passage) => $passage['chapter']['id'], $scheduled_reading['passages']);
    // count the characters in today's reading
    $char_length = col("SELECT SUM(LENGTH($trans)) chapter_length FROM verses WHERE  chapter_id IN(".implode(',', $scheduled_reading_chapter_ids).")");
    if (
      ($time - $_SESSION['last_page_load_time']) / 60
      < $char_length / 1000
    ) { // https://en.wikipedia.org/wiki/Words_per_minute
      // "the number of characters per minute tends to be around 1000 for all the tested languages"
      $_SESSION['error'] = "You read faster than a reasonable speed of what humans can comprehend.";
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

  $_SESSION['last_page_load_time'] = $time;
  $page_title = "Read";
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

  if ($today_completed) {
    echo "<blockquote><img class='icon' src='/img/circle-check.svg'> You've completed the reading for today!</blockquote>";
  }
  // header with translation selector
  echo "<div id='date-header'>
    <h5>".$today->format("l, F j")."</h5>
    <form style='display: flex;'>
      <input type='hidden' name='today' value='".$today->format('Y-m-d')."'>
      <select name='change_trans' onchange='this.form.submit();'>";
      foreach($tranlsations as $trans_opt)
        echo "
          <option value='$trans_opt' ".($trans_opt == $trans ? "selected" : "").">".strtoupper($trans_opt)."</option>";
    echo "</select>
    </form>
  </div>";

  // generates html for verses
  $html = "";
  if ($scheduled_reading) {
    echo "<h4 class='text-center'>$scheduled_reading[reference]</h4>";
    foreach($scheduled_reading['passages'] as $passage) {
      $book = $passage['book'];
      $verses = select("SELECT number, $trans FROM verses WHERE chapter_id = ".$passage['chapter']['id']);

      $book_abbrevs = json_decode($passage['book']['abbreviations'], true);
      $ref = ucwords($book_abbrevs[0]).". ".$passage['chapter']['number'].":";

      foreach($verses as $verse_row) {
        $html .= "
          <div class='verse'><span class='ref'>".$ref.$verse_row['number']."</span><span class='verse-text'>".$verse_row[$trans]."</span></div>";
      }
    }
    echo $html."
    <form method='post' id='done' class='center'><button type='submit' name='done' value='1'>Done!</button></form>";
  }
  else {
    echo "<p>Nothing to read today!</p>";

    // look for the next time to read in the schedule.
    $days = get_schedule_days($schedule['id']);
    $today = new Datetime();
    foreach($days as $day) {
      $dt = new Datetime($day['date']);
      if ($today < $dt) {
        echo "<p>The next reading will be on <b>".$dt->format('F j')."</b>.</p>";
        break;
      }
    }
  }


  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";