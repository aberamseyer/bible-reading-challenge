<?php

//
// Creates user streaks. run once after adjusting the constants in the DatePeriod constructor
// all this code does is loop over the same code as the 'update-streaks.php', manually setting what day it is
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_FILE);

$schedule = get_active_schedule();

foreach(select("
  SELECT SUM(
    LENGTH(rcv) - LENGTH(REPLACE(rcv, ' ', '')) + 1
  ) as word_count, GROUP_CONCAT(rcv, ' ') chapter, c.id, b.name || ' ' || c.number chp
  FROM verses v
  JOIN chapters c ON c.id = v.chapter_id
  JOIN books b ON b.id = c.book_id
  GROUP BY chapter_id
  ORDER BY word_count asc") as $chapter) {
    $text = preg_replace("/[^a-zA-Z ]/", "", $chapter['chapter']);
    while (strpos($text, '  ') !== false)
      $text = str_replace('  ', ' ', $text);
    $count = count(explode(' ', $text));
    print($chapter['chp'].": ".$count.PHP_EOL);
    update("chapters", [
      'word_count' => $count
    ], 'id = '.$chapter['id']);
}