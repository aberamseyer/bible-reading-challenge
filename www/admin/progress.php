<?php

require __DIR__."/../inc/init.php";

if (!$staff) {
  redirect('/');
}


$page_title = "Progress";
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require DOCUMENT_ROOT."inc/head.php";

echo admin_navigation();

echo "<h5 class='text-center'>Group Monthly Progress</h5>";
$end_of_month = date('Y-m-t'); // 't' is the how many days in the month
$start = new DateTime($schedule->data('start_date'), $site->TZ);
$end_date = new DateTime($schedule->data('end_date'), $site->TZ);
$next = clone($start); $next->modify('+1 month');
$prev = clone($start);
$timer = new \BibleReadingChallenge\PerfTimer();
$total_words_in_schedule = $schedule->total_words_in_schedule();
if ($total_words_in_schedule) { // graphs are meaningless if there is nothing in the schedule
  $graphs = [];
  $sum = 0;
  do {
    $data = $schedule->emoji_data(null, $start->format('U'), $next->format('U'));
    $timer->mark('emoji: '.$next->format('U'));
      $graphs[] = [ 
        'dates' => [ 'start' => clone($prev), 'end' => clone($next) ], 
        'data' => $data
      ];
    $next->modify('+1 month');
    $prev->modify('+1 month');
  } while ($next->format('Y-m-t') <= $end_date->format('Y-m-t') && //  one for each month in the schedule
    $next->format('Y-m-t') <= $end_of_month); // only graphs up through this month
  $timer->mark('end emoji');
  
  foreach($graphs as $i => $graph) {
    echo "<div class='historical-mountain ".($i !== count($graphs)-1 ? 'hidden' : '')."'>";
    $format = "M j";
    if ($graph['dates']['start']->format('Y') != $graph['dates']['end']->format('Y')) {
      $format = "M j, Y";
    }
    echo "<h6 class='text-center'>";
    if ($i > 0) {
      echo "<button type='button' onclick='toggleMountains(($i-1) % ".count($graphs).")' style='cursor: pointer;'>&lt;&lt;</button>";
    }
    else {
      echo "<button type='button' style='visibility: hidden;' disabled>&lt;&lt;</button>";
    }
    echo "<span style='display: inline-block; width: 350px;'>".$graph['dates']['start']->format($format)." - ".$graph['dates']['end']->format($format)."</span>";
    if ($i < count($graphs)-1) {
      echo "<button type='button' onclick='toggleMountains((".count($graphs)."+$i+1) % ".count($graphs).")' style='cursor: pointer;'>&gt;&gt;</button>";
    }
    else {
      echo "<button type='button' style='visibility: hidden;' disabled>&gt;&gt;</button>";
    }
    echo "</h6>";
    echo $site->mountain_for_emojis($graph['data'], 0); // one of the mountains must start visible in order for the js that measures its height to function
    echo "</div>";
    $timer->mark('graph '.$i);
  }
}

echo "<h5>Individual User Progress</h5>";
$all_users = $site->all_users($_GET['stale']);
foreach($all_users as &$user) {
  $stats = $site->user_stats($user['id']);
  $user['last_read'] = $stats['last_read_ts'];
}
unset($user);
$timer->mark('all_users');

$user_count = count(array_filter($all_users, fn($user) => $user['last_read']));
echo toggle_all_users($user_count);

echo "<div class='table-scroll'>
<table>";
echo "<thead>
  <tr>
    <th data-sort='name'>
      User
    </th>
    <th data-sort='behind'>
      Days behind schedule
    </th>
    <th data-sort='target'>
      On-target % ".help("% of dates where the reading was marked complete on the day it was assigned")."
    </th>
    <th data-sort='percent'>
      % Complete ".help('by # of words read')."
    </th>
    <th data-sort='progress'>
      Progress
    </th>
  </tr>
</thead>
<tbody>";

foreach($all_users as $user) {
  $stats = $site->user_stats($user['id']);
  echo "<tr class='".($stats['last_read_ts'] ? '' : 'hidden')."'>
  <td ".last_read_attr($stats['last_read_ts'])." data-name><a href='/admin/users?user_id=$user[id]'><small>".html($user['name'])."</small></a></td>
  <td data-behind='$stats[days_behind]'>-$stats[days_behind]</td>
  <td data-target='".($stats['on_target_percent'])."'>".round($stats['on_target_percent'], 2)." %</td>
  <td data-percent='".($stats['challenge_percent'])."'>".round($stats['challenge_percent'], 2)."%</td>
  <td data-progress='".($stats['challenge_percent'])."' style='max-height: 100px;'>";
    echo $site->progress_canvas($stats['progress_graph_data'], 170);
  echo "</td></tr>";
  $timer->mark($user['id']);
}
echo "</tbody>
  </table>
</div>";

if (!$_GET['stale']) {
  echo "<small>Only those who have been active in the past 9 months are shown. <a href='?stale=1'>Click here to see omitted users</a>.</small>";
}
else {
  echo "<small>Only those who have <b>not</b> been active in the past 9 months are shown. <a href='?'>Click here to see active users</a>.</small>";
}

$add_to_foot .= 
  chartjs_js().
  cached_file('js', '/js/lib/tableSort.js').
  cached_file('js', '/js/progress.js');
require DOCUMENT_ROOT."inc/foot.php";