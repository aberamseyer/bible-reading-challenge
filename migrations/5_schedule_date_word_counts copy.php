<?php

//
// Adds random completion keys to all the rows in schedule_dates
// 

require __DIR__."/../www/inc/env.php";

$db = \BibleReadingChallenge\Database::get_instance();

$days = $db->select("SELECT id, passage_chapter_readings FROM schedule_dates WHERE word_count IS NULL");

$count = 0;
foreach($days as $day) {
  $pcr = json_decode($day['passage_chapter_readings'], true);
  $db->update('schedule_dates', [
    'word_count' => passage_readings_word_count($pcr)
  ], 'id = '.$day['id']);
  $count += 1;
}
echo "Updated $count record".xs($count).PHP_EOL;
