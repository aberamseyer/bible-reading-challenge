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
    
// change user from student/staff
if (isset($_POST['staff'])) {
  update("users", [
    'staff' => $_POST['staff'] ? 1 : 0
  ], "id = ".(int)$_POST['user_id']);
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
        query("DELETE FROM users WHERE id = ".$to_change['id']);
        query("DELETE FROM read_dates WHERE user_id = ".$to_change['id']);
        $_SESSION['success'] = $to_change['name']." was deleted.";
      }
    }
    else if ($_POST['change_name']) {
      update("users", [
        'name' => $_POST['change_name']
      ], "id = $to_change[id]");
      $_SESSION['success'] = "Updated name";
    }
  }
}
    
$page_title = "Manage Users";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
    
// specific user's stats
if ($_GET['user_id'] &&
  $user = row("
    SELECT * FROM users
    WHERE id = ".intval($_GET['user_id'])."
    ORDER BY name DESC")
) {
  echo "<h5>Edit ".html($user['name'])."</h5>";
  echo "<p>Email: <b>".html($user['email'])."</b><br>";
  echo "Created: <b>".date('F j, Y \a\t g:ia', $user['date_created'])."</b><br>";
  echo "Last seen: <b>".date('F j, Y \a\t g:ia', $user['last_seen'])."</b></p>";
  echo "<form method='post'>
    <input type='hidden' name='user_id' value='$user[id]'>
    <input type='text' name='change_name' minlength='1' value='".html($user['name'])."'><br>
    <button type='submit'>Save</button>
    <button type='submit' name='delete' value='1' onclick='return confirm(`Are you sure you want to delete $user[name]? This can NEVER be recovered.`)'>Delete user</button>
    </form>";
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
  
  $last_saturday = new Datetime('last saturday');
  $last_friday = new Datetime('last friday');
  $this_week = [
    [ date_modify(clone($last_saturday), '+1 day'),  'S' ],
    [ date_modify(clone($last_saturday), '+2 days'), 'M' ],
    [ date_modify(clone($last_saturday), '+3 days'), 'T' ],
    [ date_modify(clone($last_saturday), '+4 days'), 'W' ],
    [ date_modify(clone($last_saturday), '+5 days'), 'T' ],
    [ date_modify(clone($last_saturday), '+6 days'), 'F' ],
    [ date_modify(clone($last_saturday), '+7 days'), 'S' ]
  ];
      
  echo "<h5>Fully Equipped</h5>";
  $where = "WHERE sd.schedule_id = $schedule[id] 
    AND sd.date >= '".$last_friday->format('Y-m-d')."' AND sd.date <= '".$this_week[4][0]->format('Y-m-d')."'"; // last friday through this thursday
  $schedule_days_this_week = col("
    SELECT COUNT(*)
    FROM schedule_dates sd
    $where");
  $consistent_readers = cols("
    SELECT u.name
    FROM read_dates rd
    LEFT JOIN schedule_dates sd ON rd.schedule_date_id = sd.id
    LEFT JOIN users u ON u.id = rd.user_id
    $where
    GROUP BY rd.user_id
    HAVING COUNT(rd.id) >= $schedule_days_this_week");
      
  if ($consistent_readers) {
    echo "
    <ol>";
    foreach($consistent_readers as $name) {
      echo "<li>".html($name)."</li>";
    }
    echo "
    </ol>"; 
  }
  else {
    echo "<p>No one has read every day this week.</p>";
  }
      
      
  $all_users = select("
    SELECT id, name, email, staff, last_seen FROM users
    WHERE last_seen ".($_GET['stale'] ? "<" : ">=")." '".strtotime('-9 months')."'
    ORDER BY staff DESC, name ASC");
  $count = count(
    array_filter($all_users, fn($row) => $row['staff'] == 0)
  );
  
  echo "<h5>All users</h5>";
  echo "<p><b>$count</b> student".xs($count).". Click a user to see more details</p>";

  // table of users
  echo "<table>
    <colgroup>
      <col>
      <col style='width: 200px;'>
      <col>
      <col>
    </colgroup>
    <thead>
      <tr>
        <th>Email</th>
        <th title='This is irrespective of what reading schedule is selected'>4-week trend</th>
        <th>Read this week</th>
        <th>Actions</th>
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
    <tr>
    <td><small><a href='?user_id=$user[id]' title='Last seen: ".date('M j', (int)$user['last_seen'])."'>".html($user['name'])." (".html($user['email']).")</a></small></td>
    <td>
      <canvas data-graph='".json_encode($trend = cols("
      SELECT COALESCE(count, 0) count
      FROM (
        SELECT strftime('%Y-%W', sd.date) AS week
        FROM schedule_dates sd
        WHERE sd.schedule_id = $schedule[id]
        GROUP BY week
      ) sd
      LEFT JOIN (
        SELECT strftime('%Y-%W', sd.date) AS week, COUNT(rd.user_id) count
        FROM read_dates rd
        JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
        WHERE user_id = $user[id]
        GROUP BY week
      ) rd ON rd.week = sd.week
      WHERE sd.week >= strftime('%Y-%W', DATE('now', '-28 days', 'localtime') ) 
      LIMIT 4;"))."'></canvas>
    </td>
    <td class='week'>";
    foreach($this_week as $day) {
      echo "
      <span class='"
      .(in_array($day[0]->format("Y-m-d"), $days_read_this_week) ? 'done' : '') // mark done if this day is in the list of read days for this user
      .($day[0]->format("Y-m-d") == date('Y-m-d') ? ' underline' : '')."'>$day[1]</span>"; // underline the current day
    }
    echo " </td>
      <td>
        <form method='post'>
          <input type='hidden' name='user_id' value='$user[id]'>
          <small><button type='submit' name='staff' value='1' ".($user['staff'] ? 'disabled' : '').">Make staff</button>
          <button type='submit' name='staff' value='0' ".($user['id'] == $my_id || !$user['staff'] ? 'disabled' : '')." ".
          ($user['id'] == $my_id ? "title='Cant mark yourself as a student'" : "").">Make student</button></small>
        </form>
      </td>
    </tr>";
    
  }
  echo "
    </tbody>
  </table>";
  echo "<script>
    const canvas = document.querySelectorAll('canvas');
    canvas.forEach(c => {
      const data = JSON.parse(c.getAttribute('data-graph'));
      const ctx = c.getContext('2d');
      
      // Set the canvas dimensions
      c.width = 100;
      c.height = 40;
      
      // Calculate the scale factors
      const maxDataValue = Math.max(...data);
      const scaleFactor = c.height / maxDataValue;
      
      // Draw the sparkline
      ctx.beginPath();
      ctx.moveTo(0, c.height - data[0] * scaleFactor);
      for (let i = 1; i < data.length; i++) {
        const x = (c.width / (data.length - 1)) * i;
        const y = c.height - data[i] * scaleFactor;
        const prevX = (c.width / (data.length - 1)) * (i - 1);
        const prevY = c.height - data[i - 1] * scaleFactor;
        const cpx = (prevX + x) / 2;
        const cpy = (prevY + y) / 2;
        
        ctx.quadraticCurveTo(prevX, prevY, cpx, cpy);
      }
      
      let gradient = ctx.createLinearGradient(0, 0, 200, 0);
      gradient.addColorStop(0, 'rgb(63, 70, 143)');
      gradient.addColorStop(1, 'rgb(219, 184, 100)');
      ctx.strokeStyle = gradient;
      
      ctx.lineWidth = 1;
      ctx.stroke();
    })
    </script>";

  if (!$_GET['stale']) {
    echo "<small>Only those who have been active in the past 9 months are shown. <a href='?stale=1'>Click here to see omitted users</a>.</small>";
  }
  else {
    echo "<small>Only those who have <b>not</b> been active in the past 9 months are shown. <a href='?'>Click here to see active users</a>.</small>";
  }
}
    
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";