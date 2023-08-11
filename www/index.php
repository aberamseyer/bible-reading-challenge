<?php

  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  $today = new Datetime();
  if (strtotime($_GET['today'])) {
    $override_date = new Datetime($_GET['today']);
    $today = allowed_schedule_date($override_date)
      ? $override_date
      : $today;
  }
  $scheduled_reading = get_reading($today);
  $today_completed = num_rows("
    SELECT id
    FROM read_dates
    WHERE schedule_date_id = ".($scheduled_reading['id'] ?: 0)."
      AND user_id = ".$my_id);
  if ($_POST['done'] && !$today_completed && $scheduled_reading) {
    // finished reading
    insert("read_dates", [
      'user_id' => $my_id,
      'schedule_date_id' => $scheduled_reading['id']
    ]);
    $today_completed = true;
  }
  
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

  $page_title = "Read";
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

  if ($today_completed) {
    echo "<blockquote><img class='icon' src='/img/circle-check.svg'> You've completed the reading for today!</blockquote>";
  }
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

  $html = "";
  if ($scheduled_reading) {
    echo "<h4 class='text-center'>$scheduled_reading[reference]</h4>";
    foreach($scheduled_reading['passages'] as $passage) {
      $book = $passage['book'];
      $verses = select("SELECT number, $trans FROM verses WHERE chapter_id = ".$passage['book']['id']);

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
  }


  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";