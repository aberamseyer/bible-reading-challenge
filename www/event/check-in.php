<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

$page_title = "Event Check-in";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

$past = $time - 60 * 60;
$future = $time + 60 * 60;

$check_in = row("SELECT * FROM event_check_ins WHERE user_id = $me[id] AND timestamp BETWEEN $past AND $future");
if ($check_in) {
  echo "<p>You already checked in at <b>".date('g:ia', $check_in['timestamp'])."</b> for this event.</p>";
}
else {
  insert('event_check_ins', [
    'user_id' => $me['id'],
    'name' => $me['name'],
    'timestamp' => $time
  ]);
  echo "<p>You checked in at <b>".date('g:ia', $time)."</b>.</p>";
}

require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";