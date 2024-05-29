<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}


$page_title = "Progress";
$hide_title = true;
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

echo admin_navigation();

echo "<h5 class='text-center'>Group Monthly Progress</h5>";
$start = new Datetime($schedule->data('start_date'));
$end_date = new Datetime($schedule->data('end_date'));
$next = clone($start); $next->modify('+1 month');
$prev = clone($start);

$total_words_in_schedule = (int)total_words_in_schedule($schedule->ID);
if ($total_words_in_schedule) { // graphs are meaningless if there is nothing in the schedule
  $graphs = [];
  $sum = 0;
  do {
    $data = $db->select("
      SELECT ROUND(SUM(word_count) * 1.0 / $total_words_in_schedule * 100, 2) percent_complete, u.emoji, u.id
      FROM schedule_dates sd
      JOIN JSON_EACH(passage_chapter_ids)
      JOIN chapters c on c.id = value
      JOIN read_dates rd ON sd.id = rd.schedule_date_id
      JOIN users u ON u.id = rd.user_id
      WHERE sd.schedule_id = ".$schedule->ID." AND ".$start->format('U')." <= rd.timestamp AND rd.timestamp < ".$next->format('U')."
      GROUP BY u.id
      ORDER BY COUNT(*) DESC, RANDOM()
      LIMIT 20");
      $graphs[] = [ 
        'dates' => [ 'start' => clone($prev), 'end' => clone($next) ], 
        'data' => $data
      ];
    $next->modify('+1 month');
    $prev->modify('+1 month');
  } while ($next->format('U') <= strtotime('+1 month', $end_date->format('U')));

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
  }
}

echo "<h5>Individual User Progress</h5>";
$all_users = $site->all_users($_GET['stale']);
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
    <th data-sort='streak'>
      Current/Longest Streak
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
  $days_behind = 
    $db->col("SELECT COUNT(*) FROM schedule_dates WHERE schedule_id = ".$schedule->ID." AND date <= '".date('Y-m-d')."'") - 
    $db->col("SELECT COUNT(*) FROM read_dates rd JOIN schedule_dates sd ON sd.id = rd.schedule_date_id WHERE sd.schedule_id = ".$schedule->ID." AND rd.user_id = $user[id]");
    $percent_complete = $total_words_in_schedule
      ? words_read($user, $schedule->ID) / $total_words_in_schedule * 100
      : 0;
  echo "<tr class='".($user['last_read'] ? '' : 'hidden')."'>
  <td ".last_read_attr($user['last_read'])." data-name><a href='/admin/users?user_id=$user[id]'><small>$user[name]</small></a></td>
  <td data-behind='$days_behind'>-$days_behind</td>
  <td data-streak='".($user['streak'] + $user['max_streak'])."'>$user[streak] / $user[max_streak]</td>
  <td data-percent='".($percent_complete)."'>".round($percent_complete, 2)."%</td>
  <td data-progress='$percent_complete' style='max-height: 100px;'>";
    echo $site->progress_canvas($user['id'], $schedule->ID, 170);
  echo "</td></tr>";
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
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";