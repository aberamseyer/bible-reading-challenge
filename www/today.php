<?php

require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

// set translation, update it if the select box changed
$tranlsations = ['rcv', 'kjv', 'esv', 'asv', 'niv', 'nlt'];
if ($_REQUEST['change_trans'] || array_key_exists('change_email_me', $_REQUEST)) {
  $new_trans = $_REQUEST['change_trans'];
  if (!in_array($new_trans, $tranlsations)) {
    $new_trans = 'rcv';
  }
  $me['trans_pref'] = $new_trans;
  
  $me['email_verses'] = array_key_exists('change_email_me', $_REQUEST)
    ? $_REQUEST['change_email_me']
    : 0;
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
$scheduled_reading = get_reading($today, $schedule['id']);

// determine if today's reading has been completed
$today_completed = day_completed($my_id, $scheduled_reading['id'] ?: 0);

// "Done!" clicked
if ($_REQUEST['done'] && !$today_completed && $scheduled_reading &&
  $_REQUEST['complete_key'] == $scheduled_reading['complete_key']) {
  insert("read_dates", [
    'user_id' => $my_id,
    'schedule_date_id' => $scheduled_reading['id'],
    'timestamp' => $time
  ]);
  $today_completed = true;
}

$page_title = "Read";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

if ($today_completed) {
  echo "<blockquote><img class='icon' src='/img/circle-check.svg'> You've completed the reading for today!</blockquote>";
}
// header with translation selector and email pref
echo "<div id='date-header'>
  <h5>".$today->format("l, F j")."</h5>
  <form style='display: flex; width: 20rem; justify-content: space-between; align-items: flex-end;'>
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
  if ($me['streak']) {
    $streak = (int)$me['streak'];
    echo "<div>";
    echo $streak > 46
      ? '🔥 x'.number_format($streak)
      : str_repeat('🔥', (int)$me['streak']);
    echo "
    </div>";
  }

// how many have read today
$total_readers = col("SELECT COUNT(*)
  FROM read_dates rd
  JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
  WHERE sd.id = ".$scheduled_reading['id']);
echo "<p><small>";

if ($today_completed) {
  if ($total_readers == 1) {
    echo "You're the first to complete this reading";
  }
  else {
    echo "You and ".($total_readers-1)." other".xs($total_readers-1)." have completed this reading";
  }
}
else {
  $nf = new NumberFormatter("", NumberFormatter::SPELLOUT);
  $words = $nf->format($total_readers);
  echo ucwords($words)." other".xs($total_readers)." ".($total_readers == 1 ? "has" : "have")." completed this reading.";
}
echo "</small></p>";

echo html_for_scheduled_reading($scheduled_reading, $trans, $scheduled_reading['complete_key'], $schedule);

if ($scheduled_reading) {
  echo "<style>
    article {
      position: relative;
    }
    .mug {
      display: inline-block;
      position: absolute;
      width: 2rem;
      height: 2rem;
      left: 2px;
      top: 0;
      line-height: 1rem;
      font-size: 1.5rem;
      transition: .7s all;
      box-shadow: 1px 1px  2.4px var(--color-text);
      border-radius: 50%;
      padding: 3px;
      text-align: center;
      background: var(--color-fade);
    }
    .mug small {
      font-size: 0.7rem;
      display: block;
      text-align: center;
      margin-top: 2px;
      color: var(--color-bg);
    }
  </style>";
  echo "
  <script>
    const WS_URL = 'ws".(PROD ? 's' : '')."://".SOCKET_DOMAIN."'
  </script>
  <script src='/js/client.js'></script>";
}
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";