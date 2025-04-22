<?php

$insecure = true;
require __DIR__."/inc/init.php";

// Implementation adopted from example at https://docs.laminas.dev/laminas-feed/writer/
use Laminas\Feed\Writer\Feed;

global $site, $schedule;

$feed_type = $_GET['type'] == "rss" 
  ? "rss" 
  : "atom";

$base_url = "http".($_SERVER['HTTPS'] ? "s" : "")."://".$site->DOMAIN;
$author = [
  'name'  => $site->data('site_name'),
  'email' => $site->data('email_from_address')."@".$site->DOMAIN,
  'uri'   => $base_url,
];

$feed = new Feed;
$feed->setGenerator("Abe's Bible Reading Challenge", VERSION, "https://github.com/aberamseyer/Bible-Reading-Challenge/blob/master/www/feed.php");
$feed->setTitle($site->data('short_name')."'s Bible Reading Schedule");
$feed->setLink($base_url."/today");
$feed->setFeedLink($base_url."/feed?type=".$feed_type, $feed_type);
$feed->addAuthor($author);
$feed->setDescription("Current Bible reading schedule for ".$site->data('site_name'));

$tz = new DateTimeZone($site->data('time_zone_id'));
$start_of_day = "07:30:00";
$today = new DateTime("now", $tz);
if ($_GET['today'] && strtotime($_GET['today'])) {
  $override_date = new DateTime($_GET['today']." ".$start_of_day, $tz);
  $today = allowed_schedule_date($override_date)
  ? $override_date
  : $today;
}

$schedule_dates = array_filter($schedule->get_dates(0), fn ($schedule_date) => strtotime($schedule_date['date']) <= $today->format('U'));

foreach(array_reverse($schedule_dates) as $schedule_date) {
  $entry = $feed->createEntry();
  $schedule_date_datetime = new DateTime($schedule_date['date']." ".$start_of_day, $tz);

  $entry->setId(strval($schedule_date['id']));
  $entry->setTitle($schedule_date_datetime->format("l, F j"));
  $link = $base_url."/today?today=".$schedule_date_datetime->format('Y-m-d');
  $entry->setLink($link);
  $entry->addAuthor($author);
  
  $entry->setDateModified($schedule_date_datetime);
  $entry->setDateCreated($schedule_date_datetime);

  $entry->setDescription("Daily reading portion for ".$site->data('short_name'). "'s Bible reading challenge");
  $entry->setContent(
    "<h4>".$schedule_date['passage']."</h4>
    <a href='".$link."'>Read on ".$site->data('short_name')."</a>"
  );

  $feed->addEntry($entry);
}

$feed->setDateModified($feed->getEntry(0)->getDateModified());

header("Content-type: application/".$feed_type."+xml");
echo $feed->export($feed_type);
