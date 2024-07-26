<?php

//
// Create verse reference ranges for existing scheduled readings
// 

require __DIR__."/../www/inc/env.php";

$db = BibleReadingChallenge\Database::get_instance();

$schedule_dates = $db->select("SELECT id, passage, passage_chapter_ids FROM schedule_dates WHERE passage_chapter_readings IS NULL");

foreach($schedule_dates as $schedule_date) {
  $passage_chapter_ids = json_decode($schedule_date['passage_chapter_ids'], true);

  $objs = [];
  foreach($passage_chapter_ids as $passage_chapter_id) {
    $objs[] = [ 'id' => $passage_chapter_id,
     's' => 1,
     'e' => (int)$db->col("SELECT verses FROM chapters WHERE id = ".$passage_chapter_id)
    ];
  }

  print("Adding ranges for chapter: ".$schedule_date['passage']."\n");
  $db->update("schedule_dates", [
    'passage_chapter_readings' => json_encode($objs)
  ], 'id = '.$schedule_date['id']);
}