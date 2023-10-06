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

echo "<table class='table-scroll'>";
echo "<thead>
  <tr>
    <th>User</th>
    <th>Badges</th>
  </tr>
</thead>
<tbody>";
foreach($all_users as $user) {
  echo "<tr class='".($user['last_read'] ? '' : 'hidden')."'>
  <td data-last-read='".date('Y-m-d', $user['last_read'] ?: "4124746800")."'>$user[name]</td>
  <td>";
  echo badges_for_user($user['id']);
  echo "</td></tr>";
}
echo "</tbody>
  </table>";

if (!$_GET['stale']) {
  echo "<small>Only those who have been active in the past 9 months are shown. <a href='?stale=1'>Click here to see omitted users</a>.</small>";
}
else {
  echo "<small>Only those who have <b>not</b> been active in the past 9 months are shown. <a href='?'>Click here to see active users</a>.</small>";
}

require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";