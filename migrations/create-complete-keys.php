<?php

//
// Adds random completion keys to all the rows in schedule_dates
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_FILE);

$days = cols("SELECT id FROM schedule_dates");

foreach($days as $day_id) {
  update('schedule_dates', [
    'complete_key' => bin2hex(random_bytes(16))
  ], 'id = '.$day_id);
}