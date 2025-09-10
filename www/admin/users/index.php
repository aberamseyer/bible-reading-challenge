<?php

require __DIR__."/../../inc/init.php";

if (!$staff) {
  redirect('/');
}

// get dates for single user's progress view
if ($_REQUEST['get_dates'] && $_REQUEST['user_id']) {
  print_json($schedule->get_dates((int)$_REQUEST['user_id']));
}

// edit/delete user
if ($_POST['user_id']) {
  $to_change = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND id = ".(int)$_POST['user_id']);
  if ($to_change) {
    if ($_POST['delete']) {
      if ($to_change['staff']) {
        $_SESSION['error'] = "Can't delete a staff member. Make them a student first.";
      }
      else {
        $db->query("DELETE FROM read_dates WHERE user_id = ".$to_change['id']);
        $db->query("DELETE FROM users WHERE site_id = ".$site->ID." AND id = ".$to_change['id']);
        $_SESSION['success'] = $to_change['name']." was deleted.";
      }
    }
    else {
      $name = trim($_POST['name']);
      $emoji = trim($_POST['emoji']);
      if (!$name) {
        $_SESSION['error'] = "Name cannot be blank";
      }
      else if (!$emoji) {
        $_SESSION['error'] = "Emoji cannot be blank";
      }
      else if (grapheme_strlen($emoji) !== 1) {
        $_SESSION['error'] = "Enter exactly 1 character for your emoji";
      }
      else {
        $db->update("users", [
          'name' => $_POST['name'],
          'email_verses' => array_key_exists('email_verses', $_POST) ? 1 : 0,
          'staff' => intval($_POST['staff']) ? 1 :0,
          'emoji' => $emoji
        ], "id = $to_change[id]");
        $_SESSION['success'] = "Updated user";
        redirect();
      }
    }
  }
}

// merge accounts
if ($_POST['merge_from_account']) {
  $merge_from_account = $db->row("SELECT id, name, email FROM users WHERE site_id = ".$site->ID." AND id = ".(int)$_POST['merge_from_account']);
  $merge_to_account = $db->row("SELECT id, name, email FROM users WHERE site_id = ".$site->ID." AND id = ".(int)$_POST['merge_to_account']);

  if (!$merge_from_account || !$merge_to_account) {
    $_SESSION['error'] = "Invalid accounts for merge.";
  }
  else {
    $sql = "
      SELECT sd.id
      FROM read_dates rd
      JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      WHERE rd.user_id = ";
    $read_days_from = $db->cols($sql.$merge_from_account['id']);
    $read_days_to = $db->cols($sql.$merge_to_account['id']);

    $difference = array_values(array_diff($read_days_from, $read_days_to));

    if ($difference) {
      $db->query("
        UPDATE read_dates
        SET user_id = $merge_to_account[id]
        WHERE id IN(
          SELECT rd.id
          FROM read_dates rd
          JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
          WHERE sd.id IN(".implode(',', $difference).")
            AND rd.user_id = $merge_from_account[id]
        )");
    }

    $db->query(
      "UPDATE schedules SET user_id = $merge_to_account[id]
      WHERE user_id = $merge_from_account[id]");
    $db->query("DELETE FROM read_dates WHERE user_id = $merge_from_account[id]");
    $db->query("DELETE FROM users WHERE id = $merge_from_account[id]");
    $db->query("UPDATE verse_email_stats SET user_id = $merge_to_account[id]
      WHERE user_id = $merge_from_account[id]");

    $_SESSION['success'] = "Deleted account: ".html($merge_from_account['email'])." and updated ".count($difference)." unread day".xs(count($difference))." to ".html($merge_to_account['email']);
    redirect('/admin/users?user_id='.$merge_to_account['id']);
  }
  redirect();
}

$page_title = "Manage Users";
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require DOCUMENT_ROOT."inc/head.php";
  echo admin_navigation();

$user_id = (int)$_GET['user_id'];
if ($user_id &&
  ($stats = $site->user_stats($user_id)) &&
  ($user = $db->row("SELECT * FROM users WHERE id = $user_id AND site_id = ".$site->ID))
) {
  // specific user's stats

  echo "<p>".back_button("Back")."</p>";
  echo "<h5>Edit ".html($user['name'])."</h5>";
  echo "<p>Email: <b>".html($user['email'])."</b><br>";
  echo "Joined: <b>".date('F j, Y \a\t g:ia', $user['date_created'])."</b><br>";
  echo "Last seen: <b>".($stats['last_seen'] ? date('F j, Y \a\t g:ia', $stats['last_seen']) : "N/A")."</b><br>";
  echo "Last read: <b>".($stats['last_read_ts'] ? date('F j, Y \a\t g:ia', $stats['last_read_ts']) : "N/A")."</b><br>";
  echo "Current Streak / Longest Streak: <b>".$user['streak']."</b> day".xs($user['streak'])." / <b>".$user['max_streak']."</b> day".xs($user['max_streak'])."<br>";
  echo "Consistency (lower is better) ".help('Standard deviation of average days read per week').": <b>".$stats['deviation']."</b><br>";
  echo "Percentage On-target days ".help('The percentage of days that were recorded on the day that they were scheduled').": <b>".round($stats['on_target'], 2)." %</b>";
  echo badges_html($stats['badges'])."</p>";
  echo "<p>
  <div class='two-columns'>
    <div>
      <h6 class='text-center'>Progress</h6>
     ".$site->progress_canvas($stats['progress_graph_data'])."
    </div>
    <div>
      <h6 class='text-center'>Days read each week ".help("This number includes days read in personal schedules")."</h6>
      ".$site->weekly_progress_canvas($user_id)."
    </div>
  </div>
  <br>
  <form method='post'>
    <fieldset>
      <legend>Edit Account</legend>
      <input type='hidden' name='user_id' value='$user_id'>
      <label>Name <input type='text' name='name' minlength='1' value='".html($user['name'])."'></label>
      <div>
        <label><input type='checkbox' name='email_verses' value='1' ".($user['email_verses'] ? 'checked' : '').">&nbsp;&nbsp;Email Verses</label>
      </div>
      <div>
        <label>My emoji
          <input type='text' name='emoji'
            minlength='1' maxlength='6'
            value='".html($user['emoji'])."'
            style='width: 70px'
          >
        </label>
      </div>
      <div>
        <legend>Account Type</legend>
        <label><input type='radio' name='staff' ".($user['staff'] ? 'checked' : '')." value='1'".($user['id'] == $my_id ? "title='Cant mark yourself as a student'" : "")."> Staff</label>
        <label><input type='radio' name='staff' ".($user['staff'] ? '' : 'checked')." value='0'".($user['id'] == $my_id ? "title='Cant mark yourself as a student' disabled" : "")."> Student</label>
      </div>
      <button type='submit'>Save</button>
      <button type='submit' name='delete' value='1' onclick='return confirm(`Are you sure you want to delete $user[name]? This can NEVER be recovered.`)'>Delete user</button>
    </fieldset>
  </form>";
  echo "<h5>Progress</h5>";
  echo $schedule->html_calendar();
  $add_to_foot .=
    chartjs_js().
    cached_file('js', '/js/user.js')."
  <script>
    const readingDays = document.querySelectorAll('.reading-day:not(.disabled)')
    fetch(`?get_dates=1&user_id=".$user['id']."`).then(rsp => rsp.json())
    .then(data => {
      readingDays.forEach(tableCell => {
        const date = tableCell.getAttribute('data-date')
        const matchingDay = data.find(sd => sd.date === date)
        if (matchingDay) {
          tableCell.querySelector('small').textContent = matchingDay.passage
          if (matchingDay.read) {
            tableCell.classList.add('active')
          }
        }
      })
    })
    </script>";

    // merge accounts
    $read_days_sql = "
      SELECT COUNT(rd.id) read_days, u.*
      FROM users u
      LEFT JOIN read_dates rd ON rd.user_id = u.id
      LEFT JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      %s
      GROUP BY u.id
      ORDER BY name";
    $user_read_days = $db->col(sprintf($read_days_sql, "WHERE u.site_id = ".$site->ID." AND u.id = $user[id]"));
    echo "
    <form method='post'>
      <fieldset>
        <legend>Merge Accounts</legend>
        <p>You can use this form to merge this account into another account. This is destructive, deleting THIS account and retaining the other account.</p>
        <details>
          <summary>Danger Zone</summary>
          I want to delete <input style='width: 450px;' type='text' readonly value='$user[emoji] ".html($user['name']).": ".html($user['email']).", $user_read_days read days'> and put all of that account's progress into: ";
        echo "
          <input type='hidden' name='merge_from_account' value='$user[id]'>
          <select name='merge_to_account'>";
        foreach($db->select(sprintf($read_days_sql, "WHERE u.site_id = ".$site->ID." AND u.id != $user[id]")) as $other_user) {
          echo "<option value='$other_user[id]'>$other_user[emoji] ".html($other_user['name']).": ".html($other_user['email']).", $other_user[read_days] read days</option>";
        }
        echo  "   </select>
        <button type='submit' onclick='return confirm(`Are you sure you want to merge $user[email] into another account? $user[email] will be deleted and NEVER able to be recovered.`)'>Go</button>
        </details>
      </fieldset>
    </form>";
}
else {
  // regular landing
  $WEEK_ARR = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
    8 => 'Monday'
  ];
  $starting_day_of_week = $WEEK_ARR[ (int)$site->data('start_of_week') ];

  $user_start_date = $user_end_date = null;
  if ($_GET['week_range']) {
    list($start, $end) = explode('|', $_GET['week_range']);
    $user_start_date = new DateTime($start, $site->TZ);
    $user_end_date = new DateTime($end, $site->TZ);
  }

  $last_beginning = $user_start_date ?: new DateTime("last $starting_day_of_week", $site->TZ);

  $schedule_start_date = new DateTime($schedule->data('start_date'), $site->TZ);
  $schedule_end_date = new DateTime($schedule->data('end_date'), $site->TZ);

  // week picker
  $period = new DatePeriod(
    $schedule_start_date,
    new DateInterval('P1W'), // 1 week
    $schedule_end_date,
    DatePeriod::INCLUDE_END_DATE
  );

  $today = new DateTime(date('Y-m-d'), $site->TZ);
  $is_special_day = $today->format('N') == (int)$site->data('start_of_week');
  $today_for_disabled_check = clone($today);
  if ($is_special_day) {
    $today->modify('-1 day'); // for the purpose of figuring out which week we're on, it can never be the special day because that's confusing
  }

  echo "<form>Viewing week of&nbsp;&nbsp;<select name='week_range' onchange='this.form.submit();'>";
  $opt_group_year = null; $i = 0; $total_periods = iterator_count($period);
  foreach($period as $date) {
    if ($date->format('N') == $site->data('start_of_week')) {
      $week_start = clone($date);
    }
    else {
      $week_start = date_create_from_format('U', strtotime("last $starting_day_of_week", $date->format('U')));
    }
    $week_start->setTime(0, 0, 0, 0);

    $week_end = date_create_from_format('U', strtotime("next ".$WEEK_ARR[ intval($site->data('start_of_week')) - 1 ], $week_start->format('U')));
    $week_end->setTime(23, 59, 59, 999999);
    if ($week_start->format('Y') !== $opt_group_year) {
      $opt_group_year = $week_start->format('Y');
      echo "<optgroup label='".$week_start->format('Y')."'>";
    }
    echo "<option ".
      ($week_start > $today_for_disabled_check ? ' disabled ' : '')
      .(
        (!$user_start_date && $week_start <= $today && $today <= $week_end) || // today falls within the week
        ($user_start_date && $user_start_date->format('Y-m-d') == $week_start->format('Y-m-d') && $user_end_date->format('Y-m-d') == $week_end->format('Y-m-d')) // the selected week's start and end dates match up
        ? ' selected ' : '')
      ." value='".$week_start->format('Y-m-d')."|".($week_end->format('Y-m-d'))
      ."'>".$week_start->format('M j')."–".$week_end->format('M j')
      ."</option>";
      if ($week_end->format('Y') !== $opt_group_year && $i !== $total_periods) { // we skip if on the last iteration because there's an extra </optgroup> outside the loop
        echo "</optgroup>";
      }
      $i++;
  }
  echo "</optgroup>"; // this is a bug if the schedule ends at the start of the year
  echo "</select>
    </form>";

  $this_week = [
    [ $last_beginning,                                        substr($WEEK_ARR[ (int)$last_beginning->format('N') ], 0, 1) ],
    [ $next = date_modify(clone($last_beginning), '+1 day'),  substr($WEEK_ARR[ (int)$next->format('N') ], 0, 1) ],
    [ $next = date_modify(clone($last_beginning), '+2 day'),  substr($WEEK_ARR[ (int)$next->format('N') ], 0, 1) ],
    [ $next = date_modify(clone($last_beginning), '+3 days'), substr($WEEK_ARR[ (int)$next->format('N') ], 0, 1) ],
    [ $next = date_modify(clone($last_beginning), '+4 days'), substr($WEEK_ARR[ (int)$next->format('N') ], 0, 1) ],
    [ $next = date_modify(clone($last_beginning), '+5 days'), substr($WEEK_ARR[ (int)$next->format('N') ], 0, 1) ],
    [ $next = date_modify(clone($last_beginning), '+6 days'), substr($WEEK_ARR[ (int)$next->format('N') ], 0, 1) ]
  ];

  echo "<h5>All-week readers ".($user_start_date ? '' : help("This list does not refer to the current period until ".$WEEK_ARR[ (int)$site->data('start_of_week') + 1 ], 'right'))."</h5>";
  $where = "
    WHERE sd.schedule_id = ".$schedule->ID.                                                                                                                                  // Current Day:     Sun      Mon      Tue      Wed      Thu     *Fri*     Sat
    " AND '".$last_beginning->format('Y-m-d')."' <= sd.date AND sd.date <= '".($user_start_date || $is_special_day ? $this_week[6][0]->format('Y-m-d') : date('Y-m-d'))."'"; // Range:         Fri-Sun, Fri-Mon, Fri-Tue, Fri-Wed, Fri-Thu, Fri-Thu, Fri-Sat
  $schedule_days_this_week = $db->col("
    SELECT COUNT(*)
    FROM schedule_dates sd
    $where");
  $fully_equipped = $db->cols("
    SELECT u.name
    FROM read_dates rd
    LEFT JOIN schedule_dates sd ON rd.schedule_date_id = sd.id
    LEFT JOIN users u ON u.id = rd.user_id
    $where AND u.staff != 1
    GROUP BY rd.user_id
    HAVING COUNT(rd.id) = $schedule_days_this_week
    ORDER BY SUM(rd.timestamp) ASC");

  if ($fully_equipped) {
    echo "
    <ol style='columns: 2'>";
    foreach($fully_equipped as $name) {
      echo "<li>".html($name)."</li>";
    }
    echo "
    </ol>";
  }
  else {
    echo "<p><small>No one has read every day this period.</small></p>";
  }

  $all_users = $site->all_users($_GET['stale']);
  foreach($all_users as &$user) {
    $stats = $site->user_stats($user['id']);
    $user['last_read'] = $stats['last_read_ts'];
  }
  unset($user);

  $user_count = count(array_filter($all_users, fn($user) => $user['last_read']));

  echo "<h5>".($_GET['stale'] ? 'Stale' : 'All')." users</h5>";
  echo "<p>Click a user's name to see more details.<br>You can see recent signups <a href='/admin/users/recent'>here &#8608;</a>.</p>";
  echo toggle_all_users($user_count);

  // table of users
  echo "
  <div class='table-scroll'>
    <table>
      <thead>
        <tr>
          <th data-sort='name'>
            User
          </th>
          <th data-sort='last-read'>
            Last read
          </th>
          <th data-sort='notifications'>
            Notifications
          </th>
          <th data-sort='trend'>
            4-week trend ".help("This is based on Sun-Sat reading, irrespective of what reading schedule (personal or corporate) or week is selected")."
          </th>
          <th data-sort='period'>
            Read this period ".help("This relates to the corporate schedule, beginning with \"last $starting_day_of_week\"")."
          </th>
        </tr>
        </thead>
      <tbody>";
    foreach($all_users as $user) {
      $days_read_this_week = $db->cols("
        SELECT DATE(sd.date) d
        FROM read_dates rd
        JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
        WHERE schedule_id = ".$schedule->ID."
          AND rd.user_id = $user[id]
          AND d >= DATE('".$this_week[0][0]->format('Y-m-d')."')
          AND d <= DATE('".$this_week[6][0]->format('Y-m-d')."')");
      echo "
      <tr class='".($user['last_read'] ? '' : 'hidden')."'>
        <td data-name class='left'><small><a href='?user_id=$user[id]' title='Last seen: ".($user['last_seen'] ? date('M j', $user['last_seen']) : "N/A")."'>".$user['emoji'].'&nbsp;&nbsp;&nbsp;&nbsp;'.html($user['name'])."</a></small></td>
        <td ".last_read_attr($user['last_read'])."><small>".($user['last_read'] ? date('M j', $user['last_read']) : 'N/A')."</small></td>
        <td data-notifications='".($user['email_verses'] || $user['push_notifications'] ? 1 : 0)."'>".($user['email_verses'] || $user['push_notifications'] ? '<img alt="check" src="/img/static/circle-check.svg" class="icon">' : '<img alt="x" src="/img/static/circle-x.svg" class="icon">')."</td>
        <td data-trend>
          ".$site->four_week_trend_canvas($user['id'])."
        </td>
        <td data-period class='week'>";
      foreach($this_week as $day) {
        echo "
        <div class='day "
        .(in_array($day[0]->format("Y-m-d"), $days_read_this_week) ? 'active' : '') // mark done if this day is in the list of read days for this user
        ."'>$day[1]</div>"; // underline the current day
      }
      echo "
        </td>
      </tr>";

    }
    echo "
      </tbody>
    </table>
  </div>";

  if (!$_GET['stale']) {
    echo "<small>Only those who have been active in the past 9 months are shown. <a href='?stale=1".($user_start_date ? "&date='".$last_beginning->format('Y-m-d')."'" : "")."'>Click here to see omitted users</a>.</small>";
  }
  else {
    echo "<small>Only those who have <b>not</b> been active in the past 9 months are shown. <a href='?".($user_start_date ? "&date='".$last_beginning->format('Y-m-d')."'" : "")."'>Click here to see active users</a>.</small>";
  }

  $add_to_foot .=
    chartjs_js().
    cached_file('js', '/js/lib/tableSort.js').
    cached_file('js', '/js/users.js');
}


require DOCUMENT_ROOT."inc/foot.php";
