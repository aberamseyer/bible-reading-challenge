<?php
  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  if (!$site->data('allow_personal_schedules')) {
    redirect('/my-schedule/corporate');
  }


  $calendar_sched = new BibleReadingChallenge\Schedule(true);
  if ($_REQUEST['get_dates']) {
    print_json($calendar_sched->get_dates($my_id));
  }
  else if ($_REQUEST['fill_dates'] && $_REQUEST['d'] && $_REQUEST['start_book'] && $_REQUEST['start_chp'] && $_REQUEST['days']) {
    print_json(
      $calendar_sched->fill_dates($_REQUEST['fill_dates'], $_REQUEST['d'], $_REQUEST['start_book'], $_REQUEST['start_chp'], $_REQUEST['days'])
    );
  }
  else if ($_POST['start_date'] && $_POST['end_date']) {
    $start = new Datetime($_POST['start_date']);
    $end = new Datetime($_POST['end_date']);
    if (!$start || !$end) {
      $_SESSION['error'] = "Invalid dates";
    }
    else {
      $difference = $start->diff($end);
      if ($start >= $end) {
        $_SESSION['error'] = 'Start date must come before end date';
      }
      else if ($difference->y > 4) {
        $_SESSION['error'] = 'Schedule must be shorter than 4 years';
      }
      else {
        $calendar_sched->update($start, $end, $calendar_sched->data('name'));
        $_SESSION['success'] = "Updated your schedule's dates.";
        redirect();
      }
    }
  }
  else if ($_POST['edit']) {
    $calendar_sched->edit($_POST['days']);

    $_SESSION['success'] = 'Schedule saved';
    redirect();
  }


  $hide_title = true;
  $page_title = "Schedule".($allow_personal_schedules ? 's' : '');
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";  

  echo do_nav([
    ['/my-schedule/corporate', 'Corporate'],
    ['/my-schedule/personal', 'Personal'],
  ], true, 'admin-navigation');

  echo "<h1>Personal Schedule</h1>
  <details>
    <summary>Introduction</summary>
    <h5>Personal Calendar Instructions</h5>
    <p>
      From here, you can create and edit your very own Bible reading schedule. This is useful if you already read the Bible on your own schedule
      and want to track everything in one on the website. Everything read here will be recorded and count toward your personal statistics, but it will
      not count toward the all-club schedule, goals, or rewards (if any).
    </p>
    <ol>
      <li>Adjust the start and end dates</li>
      <li>Click 'Save schedule dates'</li>
      <li>Follow the editor instructions below, and use the tool on the side of the page to fill in the dates.</li>
      <li>Click 'Save readings'</li>
    </ol>
    <p>If you would like to stop using the personal schedule, you can simply ignore it, or use the 'Clear after selected' button to delete all the future
    readings and choose 'Save readings'</p>";


  $my_schedules = BibleReadingChallenge\Schedule::schedules_for_user($site->ID, $my_id);
  echo "<table>
    <thead>
      <tr>
        <th data-sort='name'>
          Name
        </th>
        <th data-sort='start'>
          Start
        </th>
        <th data-sort='end'>
          End
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>";

  foreach($my_schedules as $schedule) {
    echo "
      <tr class='".($schedule->data('active') ? 'active' : '')."'>
        <td data-name><a href='?edit=".$schedule->ID."'><small>".html($schedule->data('name'))."</small></a></td>
        <td data-start='".$schedule->data('start_date')."'><small>".date('F j, Y', strtotime($schedule->data('start_date')))."</small></td>
        <td data-end='".$schedule->data('end_date')."'><small>".date('F j, Y', strtotime($schedule->data('end_date')))."</small></td>
        <td>
          <form method='post'>
            <small>
              <input type='hidden' name='schedule_id' value='".$schedule->ID."'>
              <button type='submit' name='set_active' value='1' ".($schedule->data('active') ? 'disabled' : '').">Set active</button>
              <button type='submit' name='duplicate' value='1'>Duplicate</button>
              <button type='button' onclick='window.location = `/my-schedule/personal?calendar_id=".$schedule->ID."`'>Edit Calendar</button>
            </small>
          </form>
        </td>
      </tr>";
  }
  echo "
      </tbody>
    </table>
    <br>";

  echo "<form method='post'>
    <fieldset>
      <legend>Edit schedule start and end dates</legend>
        <label>Start date: <input type='date' name='start_date' value='".$calendar_sched->data('start_date')."'></label>
        <label>End date: <input type='date' name='end_date' value='".$calendar_sched->data('end_date')."'></label>
        <button type='submit'>Save schedule dates</button>
      </fieldset>
    </form>

    ".$calendar_sched->html_instructions()."
  </details>";
  echo $calendar_sched->html_calendar();

    
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";