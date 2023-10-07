<?php

  require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

  if (!$staff) {
    redirect('/');
  }

  // all actions from the 'manage schedules' table actions column
  if ($_POST['schedule_id']) {
    $change_sched = row("SELECT * FROM schedules WHERE id = ".(int)$_POST['schedule_id']);
    if ($change_sched) {
      if ($_POST['set_active']) {
        query("UPDATE schedules SET active = 0");
        query("UPDATE schedules SET active = 1 WHERE id = $change_sched[id]");
      }
      else if ($_POST['delete']) {
        if ($change_sched['active']) {
          $_SESSION['error'] = "Can't delete the active schedule.";
        }
        else {
          query("DELETE FROM read_dates WHERE schedule_date_id IN(
            SELECT id FROM schedule_dates WHERE schedule_id = $change_sched[id]
          )");
          query("DELETE FROM schedule_dates WHERE schedule_id = $change_sched[id]");
          query("DELETE FROM schedules WHERE id  = $change_sched[id]");
          $_SESSION['success'] = 'Schedule deleted';
          redirect('?');
        }
      }
      else if ($_POST['duplicate']) {
        $new_id = insert("schedules", [
          'name' => "Copy of ".$change_sched['name'],
          'start_date' => $change_sched['start_date'],
          'end_date' => $change_sched['end_date'],
          'active' => 0
        ]);
        query("
          INSERT INTO schedule_dates (schedule_id, date, passage)
            SELECT $new_id, date, passage
            FROM schedule_dates WHERE schedule_id = $change_sched[id]");
        $_SESSION['success'] = "Schedule duplicated.&nbsp;<a href='/admin/calendar?id=$new_id'>Edit new schedule's calendar &gt;&gt;</a>";
      }
      else {
        $start_date = strtotime($change_sched['start_date']);
        if ($new_date = strtotime($_POST['start_date']))
          $start_date = $new_date;
        $end_date = strtotime($change_sched['end_date']);
        if ($new_date = strtotime($_POST['end_date']))
          $end_date = $new_date;
        if ($start_date >= $end_date) {
          $_SESSION['error'] = "Start date must be before end date.";
        }
        else if (!$_POST['name']) {
          $_SESSION['error'] = "Schedule must have a name.";
        }
        else if (
          ($change_sched['start_date'] != date('Y-m-d', $start_date) || $change_sched['end_date'] != date('Y-m-d', $end_date))
           && col("
                SELECT COUNT(*)
                FROM read_dates rd
                JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
                WHERE sd.schedule_id = $change_sched[id]")) {
          $_SESSION['error'] = "This schedule has already been started by some readers. You can no longer change the start and end dates.";
        }
        else {
          update("schedules", [
            'name' => $_POST['name'],
            'start_date' => date('Y-m-d', $start_date),
            'end_date' => date('Y-m-d', $end_date)
          ], "id = $change_sched[id]");
          $_SESSION['success'] = "Saved schedule";
        }   
      }
    }
  }
  else if ($_POST['new_schedule']) {
    $start_date = strtotime($_POST['start_date']);
    $end_date = strtotime($_POST['end_date']);
    if (!$start_date || !$end_date || $start_date >= $end_date) {
      $_SESSION['error'] = "Start date must be before end date.";
    }
    else if (!$_POST['name']) {
      $_SESSION['error'] = "Schedule must have a name.";
    }
    else {
      $new_id = insert('schedules', [
        'name' => $_POST['name'],
        'start_date' => date('Y-m-d', $start_date),
        'end_date' => date('Y-m-d', $end_date),
        'active' => 0
      ]);
      $_SESSION['success'] = "Created schedule";
      redirect("?edit=$new_id");
    }
  }
  
  $page_title = "Manage Schedules";
  $hide_title = true;
  $add_to_head .= "
  <link rel='stylesheet' href='/css/admin.css' media='screen'>";
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

  echo admin_navigation();

  if ($_GET['new_schedule']) {
    // create new schedule
    echo "<p><a href='/admin/schedules'>&lt;&lt; Back to schedules</a></p>";
    
    echo "<h5>Create New Schedule</h5>";
    echo "<form method='post'>
      <input type='hidden' name='new_schedule' value='1'>

      <label>Start date: <input type='date' name='start_date'></label>
      <label>End date: <input type='date' name='end_date'></label>
      <label>Name: <input type='text' name='name' minlength='1'></label>
      <button type='submit'>Save</button>
    </form>";
  }
  else if ($edit_sched = row("SELECT * FROM schedules WHERE id = ".(int)$_GET['edit'])) {
    // viewing single schedule
    echo "<p><a href='/admin/schedules'>&lt;&lt; Back to schedules</a></p>";

    $start_date = new Datetime($edit_sched['start_date']);
    $end_date = new Datetime($edit_sched['end_date']);
    echo "<h5>Editing '".html($edit_sched['name'])."' schedule</h5>";
    echo "<form method='post'>
      <input type='hidden' name='schedule_id' value='$edit_sched[id]'>

      <label>Start date: <input type='date' name='start_date' value='".$start_date->format('Y-m-d')."'></label>
      <label>End date: <input type='date' name='end_date' value='".$end_date->format('Y-m-d')."'></label>
      <label>Name: <input type='text' name='name' minlength='1' value='".html($edit_sched['name'])."'></label>
      <button type='submit'>Save</button>
      <button type='submit' ".($edit_sched['active'] ? 'disabled' : '')." name='delete' value='1' onclick='return confirm(`Are you sure you want to delete $edit_sched[name]? This can NEVER be recovered. All existing reading progress, INCLUDING BADGES, will be permanently lost.`)'>Delete Schedule</button>
    </form>";
  }
  else {
    // all schedules summary
    $schedules = select("SELECT * FROM schedules ORDER BY active DESC, start_date DESC");
    echo "<p><button type='button' onclick='window.location = `?new_schedule=1`'>+ Create Schedule</button></p>
    <p>Click a Schedule's name to edit its start and end dates</p>";
    echo "<table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Start</th>
          <th>End</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>";
    foreach($schedules as $schedule) {
      echo "
        <tr class='".($schedule['active'] ? 'active' : '')."'>
          <td><a href='?edit=$schedule[id]'><small>".html($schedule['name'])."</small></a></td>
          <td><small>".date('F j, Y', strtotime($schedule['start_date']))."</small></td>
          <td><small>".date('F j, Y', strtotime($schedule['end_date']))."</small></td>
          <td>
            <form method='post'>
              <small>
                <input type='hidden' name='schedule_id' value='$schedule[id]'>
                <button type='submit' name='set_active' value='1' ".($schedule['active'] ? 'disabled' : '').">Set active</button>
                <button type='submit' name='duplicate' value='1'>Duplicate</button>
                <button type='button' onclick='window.location = `/admin/calendar?id=$schedule[id]`'>Edit Calendar</button>
              </small>
            </form>
          </td>
        </tr>";
    }
  
    echo "
      </tbody>
    </table>";
  }
    
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";