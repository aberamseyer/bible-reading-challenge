<?php

//
// Creates word counts on verse, chapter, and book rows
// 

require __DIR__."/../www/inc/env.php";

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
    print("VERSE ".$verse['ref'].": ".$count.PHP_EOL);
    $db->update("verses", [
      'word_count' => $count
    ], 'id = '.$verse['id']);
}

foreach($db->select("
    SELECT v.chapter_id chapter_id, SUM(v.word_count) word_count
    FROM verses v
    JOIN chapters c ON c.id = v.chapter_id
    GROUP BY v.chapter_id
  ") as $chapter) {
    
    print("CHAPTER ".$chapter['chapter_id'].": ".$chapter['word_count'].PHP_EOL);
    $db->update("chapters", [
      'word_count' => $chapter['word_count']
    ], 'id = '.$chapter['chapter_id']);
}

foreach($db->select("
    SELECT c.book_id, SUM(c.word_count) word_count
    FROM chapters c
    JOIN books b ON b.id = c.book_id
    GROUP BY c.book_id
  ") as $book) {
    
    print("BOOK ".$book['book_id'].": ".$book['word_count'].PHP_EOL);
    $db->update("books", [
      'word_count' => $book['word_count']
    ], 'id = '.$book['book_id']);
}