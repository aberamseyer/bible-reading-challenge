<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

// uofichristiansoncampus@gmail.com account
define('GCAL_PRIVATE_URL', 'https://calendar.google.com/calendar/ical/e0dd0c868a6d98e5035e72123b34966ed8ae6c3181f9f2dac1d6c7c9892800d3%40group.calendar.google.com/private-eefcc905d5d5ba392c231e8bcb41316f/basic.ics');
define('ICAL_FILENAME', __DIR__."/../../cron/uofichristiansoncampus_event_calendar.ical");

require_once __DIR__."/../../vendor/autoload.php";
use Sabre\VObject;
use RRule\RRule;

$page_title = "Event Check-in";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

//
// parse the CoC Google Calendar of events
//
if (file_exists(ICAL_FILENAME)) {
  // refresh the file every minute
  $fp = fopen(ICAL_FILENAME, 'r');
  $stat = fstat($fp);
  fclose($fp);
  if (date('Y-m-d H:i') != date('Y-m-d H:i', $stat['ctime'])) {
    file_put_contents(ICAL_FILENAME, file_get_contents(GCAL_PRIVATE_URL));
  }
}
else {
  // file doesn't exist yet
  file_put_contents(ICAL_FILENAME, file_get_contents(GCAL_PRIVATE_URL));
}
// see if there's anything happening right now
$vcalendar = VObject\Reader::read( file_get_contents(ICAL_FILENAME) );
$ongoing_event = "";
foreach ($vcalendar->VEVENT as $event) {
  $start_time = strtotime($event->DTSTART);
  $end_time = strtotime($event->DTEND);

  if ($start_time <= $time && $time <= $end_time) {
    // Event is currently ongoing
    $ongoing_event = (string)$event->SUMMARY;
  } else {
    // Check recurrence rule for ongoing recurring events
    if ($event->RRULE) {
      $rrule = new RRule((string)$event->RRULE);
      
      // both checks are necessary. "occursAt" only checks the current date ($time) against the RRule (which doesn't include time information),
      // so we also check the start and end time
      if (
        $rrule->occursAt($time) &&
        $start_time <= $time && $time <= $end_time
      ) {
          $ongoing_event = (string)$event->SUMMARY;
      }
    }
  }
}

// If there's an ongoing event, register it
if (!$ongoing_event) {
  echo "<p>There's nothing going on right now.</p>";
}
else {
  $past = $time - 60 * 60;
  $future = $time + 60 * 60;
  
  $check_in = row("SELECT * FROM event_check_ins WHERE user_id = $me[id] AND timestamp BETWEEN $past AND $future");
  if ($check_in) {
    echo "<p>You already checked in at <b>".date('g:ia', $check_in['timestamp'])."</b> for <b>".$ongoing_event.".</p>";
  }
  else {
    insert('event_check_ins', [
      'user_id' => $me['id'],
      'name' => $me['name'],
      'timestamp' => $time,
      'event_details' => $ongoing_event
    ]);
    echo "<p>You checked in for <b>".$ongoing_event."</b> at <b>".date('g:ia', $time)."</b>.</p>";
  }
}

require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";