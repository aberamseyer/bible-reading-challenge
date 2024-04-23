<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}


$page_title = "Progress";
$hide_title = true;
$add_to_head .= "
<link rel='stylesheet' href='/css/admin.css' media='screen'>";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

echo admin_navigation();

echo "<h5>Monthly Reading</h5>";
$start = new Datetime($schedule['start_date']);
$end_date = new Datetime($schedule['end_date']);
$next = clone($start); $next->modify('+1 month');
$prev = clone($start);

$total_words_in_schedule = total_words_in_schedule($schedule['id']);
$graphs = [];
$sum = 0;
do {
  $data = select("
    SELECT ROUND(SUM(word_count) * 1.0 / $total_words_in_schedule * 100, 2) percent_complete, u.emoji, u.id
    FROM schedule_dates sd
    JOIN JSON_EACH(passage_chapter_ids)
    JOIN chapters c on c.id = value
    JOIN read_dates rd ON sd.id = rd.schedule_date_id
    JOIN users u ON u.id = rd.user_id
    WHERE sd.schedule_id = $schedule[id] AND ".$start->format('U')." <= rd.timestamp AND rd.timestamp < ".$next->format('U')."
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

$today = new Datetime();
$opt_group_year = '';
echo "<select id='mountain-select' onchange='toggleMountains()'>";
foreach($graphs as $i => $graph) {
  if ($graph['dates']['start']->format('Y') !== $opt_group_year) {
    $opt_group_year = $graph['dates']['start']->format('Y');
    echo "<optgroup label='".$opt_group_year."'>";
  }

  $selected = '';
  if ($graph['dates']['start'] < $today && $today <= $graph['dates']['end']) {
    $selected = 'selected';
  }
  echo "<option value='$i' $selected>".$graph['dates']['start']->format('M j')."–".$graph['dates']['end']->format('M j')."</option>";
  if ($graph['dates']['end']->format('Y') !== $opt_group_year && $i !== count($graphs)) {
    echo "</optgroup>";
  }
}
echo "</optgroup>";
echo "</select>";
foreach($graphs as $i => $graph) {
  mountain_for_emojis($graph['data']);
}


echo "<h5>User Progress</h5>";
$all_users = all_users($_GET['stale']);
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
    <th data-sort='badges'>
      Badges
    </th>
  </tr>
</thead>
<tbody>";

foreach($all_users as $user) {
  $days_behind = 
    col("SELECT COUNT(*) FROM schedule_dates WHERE schedule_id = $schedule[id] AND date <= '".date('Y-m-d')."'") - 
    col("SELECT COUNT(*) FROM read_dates rd JOIN schedule_dates sd ON sd.id = rd.schedule_date_id WHERE sd.schedule_id = $schedule[id] AND rd.user_id = $user[id]");
  $percent_complete = words_read($user, $schedule['id']) / total_words_in_schedule($schedule['id']) * 100;
  echo "<tr class='".($user['last_read'] ? '' : 'hidden')."'>
  <td ".last_read_attr($user['last_read'])." data-name><a href='/admin/users?user_id=$user[id]'><small>$user[name]</small></a></td>
  <td data-behind='$days_behind'>-$days_behind</td>
  <td data-streak='".($user['streak'] + $user['max_streak'])."'>$user[streak] / $user[max_streak]</td>
  <td data-percent='".($percent_complete)."'>".round($percent_complete, 2)."%</td>
  <td data-badges='".count(badges_for_user($user['id']))."'>";
  echo badges_html_for_user($user['id']);
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

echo "
<script src='/js/tableSort.js'></script>
<script src='/js/progress.js'></script>";
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";