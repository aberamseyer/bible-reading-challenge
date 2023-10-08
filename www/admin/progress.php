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

echo "<h5>Badges</h5>";

$all_users = all_users($_GET['stale']);
$user_count = count(array_filter($all_users, fn($user) => $user['last_read']));
echo toggle_all_users($user_count);

echo "<div class='table-scroll'>
<table>";
echo "<thead>
  <tr>
    <th data-sort='name'>
      <span class='sort-icon asc'></span>User
    </th>
    <th data-sort='behind'>
      <span class='sort-icon'></span>
      Days behind schedule
    </th>
    <th data-sort='streak'>
      <span class='sort-icon'></span>
      Current/Longest Streak
    </th>
    <th data-sort='badges'>
      <span class='sort-icon'></span>
      Badges
    </th>
  </tr>
</thead>
<tbody>";
foreach($all_users as $user) {
  $days_behind = 
    col("SELECT COUNT(*) FROM schedule_dates WHERE schedule_id = $schedule[id] AND date <= '".date('Y-m-d')."'") - 
    col("SELECT COUNT(*) FROM read_dates rd JOIN schedule_dates sd ON sd.id = rd.schedule_date_id WHERE sd.schedule_id = $schedule[id] AND rd.user_id = $user[id]");
  echo "<tr class='".($user['last_read'] ? '' : 'hidden')."'>
  <td ".last_read_attr($user['last_read'])." data-name><a href='/admin/users?user_id=$user[id]'><small>$user[name]</small></a></td>
  <td data-behind='$days_behind'>-$days_behind</td>
  <td data-streak='".($user['streak'] + $user['max_streak'])."'>$user[streak] / $user[max_streak]</td>
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