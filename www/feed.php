<?php

$insecure = true;
require __DIR__."/inc/init.php";

// Implementation adopted from example at https://docs.laminas.dev/laminas-feed/writer/
use Laminas\Feed\Writer\Feed;

global $site, $schedule, $db;

$feed_type = $_GET['type'] == "rss" 
  ? "rss" 
  : "atom";

$base_url = "http".($_SERVER['HTTPS'] ? "s" : "")."://".$site->DOMAIN;
$author = [
  'name'  => $site->data('site_name'),
  'email' => $site->data('email_from_address')."@".$site->DOMAIN,
  'uri'   => $base_url,
];

$user = $_GET['user'] ?: 0;
$trans_pref = 'esv';
if ($user) {
  $trans_pref = $db->col("SELECT trans_pref FROM users WHERE uuid = '".$db->esc($user)."'")
    ?: 'esv';
}

$feed = new Feed;
$feed->setGenerator("Abe's Bible Reading Challenge", VERSION, "https://github.com/aberamseyer/Bible-Reading-Challenge/blob/master/www/feed.php");
$feed->setTitle($site->data('short_name')."'s Bible Reading Schedule");
$feed->setLink($base_url."/today");
$feed->setFeedLink($base_url."/feed?type=".$feed_type, $feed_type);
$feed->addAuthor($author);
$feed->setDescription("Current Bible reading schedule for ".$site->data('site_name'));

$tz = new DateTimeZone($site->data('time_zone_id'));
$start_of_day = "07:30:00";
$now = new DateTime("now", $tz);

$schedule_dates = array_filter(
  $schedule->get_dates(0),
  fn ($schedule_date) => strtotime($schedule_date['date']." ".$start_of_day) <= $now->format('U'));

foreach(array_reverse($schedule_dates) as $schedule_date) {
  $entry = $feed->createEntry();
  $schedule_date_datetime = new DateTime($schedule_date['date']." ".$start_of_day, $tz);

  $entry->setId(strval($schedule_date['id']));
  $entry->setTitle($schedule_date_datetime->format("l, F j"));
  $link = $base_url."/today?today=".$schedule_date['date'];
  $entry->setLink($link);
  $entry->addAuthor($author);
  
  $entry->setDateModified($schedule_date_datetime);
  $entry->setDateCreated($schedule_date_datetime);

  $entry->setDescription("Daily reading portion for ".$site->data('short_name'). "'s Bible reading challenge");
  $content = "";
  if ($trans_pref == 'rcv') {
    $entry->setContent(
      "<h4>".$schedule_date['passage']."</h4>
      <a href='".$link."'>Read on ".$site->data('short_name')."</a>"
    );
  }
  else {
    $scheduled_reading = $schedule->get_schedule_date($schedule_date_datetime);
    ob_start();
    foreach($scheduled_reading['passages'] as $passage) {
      echo "<h4>".$passage['book']['name']." ".$passage['chapter']['number'].$verse_range."</h4>";
      foreach($passage['verses'] as $verse_row) {
        if ($verse_row[$trans_pref]) {
          echo "<div><b>".$verse_row['number']."</b> <span>".$verse_row[$trans_pref]."</span></div>";
        }
      }
    }
    $entry->setContent(ob_get_clean());
  }

  $feed->addEntry($entry);
}

$feed->setDateModified($feed->getEntry(0)->getDateModified());

header("Content-type: application/".$feed_type."+xml");
header("Cache-Control: max-age=".(60*60*24)); // should be about one new entry every day
echo $feed->export($feed_type);
