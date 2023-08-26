<?php

// crontab entry: 4 * * * * php /home/bible-reading-challenge/cron/airtable-event-checkin.php
// free airtable plan currently limits to 1000 calls/month

define('AIRTABLE_BASE_ID', 'appTjcF4S0vp2ij2C'); // "Attendance"
define('AIRTABLE_TABLE_ID', 'tblV9GoT02I6G5FYg'); // "F23 Event Check-in"

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = new SQLite3(DB_PATH);

// get some check-in events from the queue
$unposted_rows = select("
  SELECT *
  FROM event_check_ins
  WHERE posted = 0
  LIMIT 10", $db);

// format them for the airtable base
$records = [];
foreach($unposted_rows as $row) {
  $datetime = date_create_from_format('U', $row['timestamp']);
  $records[] = [
    "fields" => [
      "Name" => $row['name'],
      "Time" => $datetime->format('Y-m-d H:i:s'),
      "Event Details" => $row['event_details']
    ]
  ];
}

// send them to airtable
curl_post_json("https://api.airtable.com/v0/".AIRTABLE_BASE_ID."/".AIRTABLE_TABLE_ID, [
  "Authorization: Bearer ".AIRTABLE_ACESS_TOKEN
], [ "records" => $records ]);

// set them as done
query("
  UPDATE event_check_ins
  SET posted = 1
  WHERE id IN(".
    implode(',', array_column($unposted_rows, 'id')).
  ")", $db);