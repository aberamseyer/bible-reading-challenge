<?php

// crontab entry: */2 * * * * /home/bible-reading-challenge/cron/airtable-event-checkin.php

define('AIRTABLE_BASE_ID', 'applo2kFjtcUpWa6v'); // "Christians on Campus Contact Form"
define('AIRTABLE_TABLE_ID', 'tblblB2PnTP9ytbB7'); // "Event Check-in Table"

require __DIR__."/../inc/env.php";
require __DIR__."/../inc/functions.php";

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
      "Time" => $datetime->format('Y-m-d H:i:s')
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