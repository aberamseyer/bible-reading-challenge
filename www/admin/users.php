<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}

// get dates for single user's progress view
if ($_REQUEST['get_dates'] && $_REQUEST['user_id']) {
  print_json(
    select("
    SELECT sd.id, sd.date, sd.passage, rd.id read
    FROM schedule_dates sd
    LEFT JOIN (
      SELECT * FROM read_dates WHERE user_id = ".intval($_REQUEST['user_id'])."
      ) rd ON rd.schedule_date_id = sd.id
      WHERE schedule_id = $schedule[id]"));
}
    
// edit/delete user
if ($_POST['user_id']) {
  $to_change = row("SELECT * FROM users WHERE id = ".(int)$_POST['user_id']);
  if ($to_change) {
    if ($_POST['delete']) {
      if ($to_change['staff']) {
        $_SESSION['error'] = "Can't delete a staff member. Make them a student first.";
      }
      else {
        query("DELETE FROM read_dates WHERE user_id = ".$to_change['id']);
        query("DELETE FROM users WHERE id = ".$to_change['id']);
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
        update("users", [
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

$page_title = "Manage Users";
$hide_title = true;
$add_to_head .= "
<link rel='stylesheet' href='/css/admin.css' media='screen'>";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  
if ($_GET['user_id'] &&
  $user = row("
    SELECT * FROM users
    WHERE id = ".intval($_GET['user_id'])."
    ORDER BY name DESC")
) {
  // specific user's stats
  echo admin_navigation();
  $deviation = deviation_for_user($user['id'], $schedule);

  echo "<p><a href='' onclick='history.back()'>&lt;&lt; Back</a></p>";
  echo "<h5>Edit ".html($user['name'])."</h5>";
  echo "<p>Email: <b>".html($user['email'])."</b><br>";
  echo "Created: <b>".date('F j, Y \a\t g:ia', $user['date_created'])."</b><br>";
  echo "Last seen: <b>".($user['last_seen'] ? date('F j, Y \a\t g:ia', $user['last_seen']) : "N/A")."</b><br>";
  $last_read_ts = col("SELECT MAX(timestamp) FROM read_dates WHERE user_id = $user[id]");
  echo "Last read: <b>".($last_read_ts ? date('F j, Y \a\t g:ia', $last_read_ts) : "N/A")."</b><br>";
  echo "Current Streak / Longest Streak: <b>".$user['streak']."</b> day".xs($user['streak'])." / <b>".$user['max_streak']."</b> day".xs($user['max_streak'])."<br>";
  echo "Consistency (lower is better) ".help('Standard deviation of average days read per week').": <b>".$deviation."</b>";
  echo badges_html_for_user($user['id'])."</p>";
  echo "<p>
  <h6 class='text-center'>Days read each week</h6>
  <div class='center'>";
  echo weekly_progress_canvas($user['id'], $schedule);
  echo "<script>".weekly_progress_js(300, 150)."</script>";
  echo "</div>
  </p>";
  echo "<form method='post'>
    <input type='hidden' name='user_id' value='$user[id]'>
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
  </form> ";
  echo "<h5>Progress</h5>";
  echo generate_schedule_calendar($schedule);
  echo "<script>
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
}
else {
  // regular landing
  echo admin_navigation();
  
  $user_start_date = $user_end_date = null;
  if ($_GET['week_range']) {
    list($start, $end) = explode('|', $_GET['week_range']);
    $user_start_date = new Datetime($start);
    $user_end_date = new Datetime($end);
  }

  $last_friday = $user_start_date ?: new Datetime('last friday');
  
  $schedule_start_date = new Datetime($schedule['start_date']);
  $schedule_end_date = new Datetime($schedule['end_date']);

  // week picker
  $period = new DatePeriod(
    $schedule_start_date,
    new DateInterval('P1W'), // 1 week
    $schedule_end_date,
    DatePeriod::INCLUDE_END_DATE
  );

  $today = new Datetime(date('Y-m-d'));
  $is_friday = $today->format('N') == 5;
  $today_for_disabled_check = clone($today);
  if ($is_friday) {
    $today->modify('-1 day'); // for the purpose of figuring out which week we're on, it can never be friday because that's confusing
  }

  echo "<form>Viewing week of&nbsp;&nbsp;<select name='week_range' onchange='this.form.submit();'>";
  $opt_group_year = null; $i = 0; $total_periods = iterator_count($period);
  foreach($period as $date) {
    if ($date->format('N') == 5) {
      $week_start = clone($date);
    }
    else {
      $week_start = date_create_from_format('U', strtotime('last friday', $date->format('U')));
    }
    $week_start->setTime(0, 0, 0, 0);

    $week_end = date_create_from_format('U', strtotime('next thursday', $week_start->format('U')));
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
  echo "</select>";

  $this_week = [
    [ $last_friday,                                'F' ],
    [ date_modify(clone($last_friday), '+1 day'),  'S' ],
    [ date_modify(clone($last_friday), '+2 day'),  'S' ],
    [ date_modify(clone($last_friday), '+3 days'), 'M' ],
    [ date_modify(clone($last_friday), '+4 days'), 'T' ],
    [ date_modify(clone($last_friday), '+5 days'), 'W' ],
    [ date_modify(clone($last_friday), '+6 days'), 'T' ]
  ];
      
  echo "<h5>Fully Equipped ".($user_start_date ? '' : help("This list does not refer to the current period until Saturday"))."</h5>";
  $where = "
    WHERE sd.schedule_id = $schedule[id] ".                                                                                                                          // Current Day:     Sun      Mon      Tue      Wed      Thu     *Fri*     Sat
    " AND '".$last_friday->format('Y-m-d')."' <= sd.date AND sd.date <= '".($user_start_date || $is_friday ? $this_week[6][0]->format('Y-m-d') : date('Y-m-d'))."'"; // Range:         Fri-Sun, Fri-Mon, Fri-Tue, Fri-Wed, Fri-Thu, Fri-Thu, Fri-Sat
  $schedule_days_this_week = col("
    SELECT COUNT(*)
    FROM schedule_dates sd
    $where");
  $fully_equipped = cols("
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
    <ol>";
    foreach($fully_equipped as $name) {
      echo "<li>".html($name)."</li>";
    }
    echo "
    </ol>"; 
  }
  else {
    echo "<p><small>No one has read every day this period.</small></p>";
  }
      
  $nine_mo = strtotime('-9 months');
  $all_users = all_users($_GET['stale']);
  $user_count = count(array_filter($all_users, fn($user) => $user['last_read']));
  
  echo "<h5>All users</h5>";
  echo "<p>Click a user's name to see more details</p>";
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
          <th data-sort='email'>
            Emails
          </th>
          <th data-sort='trend'>
            4-week trend ".help("This is based on Mon-Sun reading, not counting this week, irrespective of what reading schedule or week is selected")."
          </th>
          <th data-sort='period'>
            Read this period ".help("This chart always begins with \"last friday\"")."
          </th>
        </tr>
        </thead>
      <tbody>";
    foreach($all_users as $user) {
      $days_read_this_week = cols("
        SELECT DATE(sd.date) d
        FROM read_dates rd
        JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
        WHERE schedule_id = $schedule[id]
          AND rd.user_id = $user[id]
          AND d >= DATE('".$this_week[0][0]->format('Y-m-d')."')
          AND d <= DATE('".$this_week[6][0]->format('Y-m-d')."')");
      echo "
      <tr class='".($user['last_read'] ? '' : 'hidden')."'>
        <td data-name class='left'><small><a href='?user_id=$user[id]' title='Last seen: ".($user['last_seen'] ? date('M j', $user['last_seen']) : "N/A")."'>".$user['emoji'].'&nbsp;&nbsp;&nbsp;&nbsp;'.html($user['name'])."</a></small></td>
        <td ".last_read_attr($user['last_read'])."><small>".($user['last_read'] ? date('M j', $user['last_read']) : 'N/A')."</small></td>
        <td data-email='".($user['email_verses'] ? 1 : 0)."'>".($user['email_verses'] ? '<img src="/img/circle-check.svg" class="icon">' : '<img src="/img/circle-x.svg" class="icon">')."</td>
        <td data-trend>
          ".four_week_trend_canvas($user['id'])."
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
  echo "<script>".four_week_trend_js(100, 40)."</script>";

  if (!$_GET['stale']) {
    echo "<small>Only those who have been active in the past 9 months are shown. <a href='?stale=1".($user_start_date ? "&date='".$last_friday->format('Y-m-d')."'" : "")."'>Click here to see omitted users</a>.</small>";
  }
  else {
    echo "<small>Only those who have <b>not</b> been active in the past 9 months are shown. <a href='?".($user_start_date ? "&date='".$last_friday->format('Y-m-d')."'" : "")."'>Click here to see active users</a>.</small>";
  }
}

echo "
<script src='/js/tableSort.js'></script>
<script src='/js/users.js'></script>";
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";