<?php

//
// Adds random completion keys to all the rows in schedule_dates
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = \BibleReadingChallenge\Database::get_instance();

$days = $db->cols("SELECT id FROM schedule_dates");

foreach($days as $day_id) {
  $db->update('schedule_dates', [
    'complete_key' => bin2hex(random_bytes(16))
  ], 'id = '.$day_id);
}