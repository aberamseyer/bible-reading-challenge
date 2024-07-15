<?php

//
// Creates word counts on verse rows
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = BibleReadingChallenge\Database::get_instance();

foreach($db->select("
  SELECT SUM(
    LENGTH(rcv) - LENGTH(REPLACE(rcv, ' ', '')) + 1
  ) as word_count, GROUP_CONCAT(rcv, ' ') verse, v.id, b.name || ' ' || c.number  || ':' || v.number ref
  FROM verses v
  JOIN chapters c ON c.id = v.chapter_id
  JOIN books b ON b.id = c.book_id
  GROUP BY v.id
  ORDER BY word_count ASC") as $verse) {
    $text = preg_replace("/[^a-zA-Z ]/", "", $verse['verse']);
    while (strpos($text, '  ') !== false)
      $text = str_replace('  ', ' ', $text);
    $count = count(explode(' ', $text));
    print($verse['ref'].": ".$count.PHP_EOL);
    $db->update("verses", [
      'word_count' => $count
    ], 'id = '.$verse['id']);
}