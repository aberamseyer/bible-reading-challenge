<?php

  require __DIR__."/../inc/init.php";

  if (!$staff) {
    redirect('/');
  }

  $editing_schedule = new BibleReadingChallenge\Schedule(
    $site->ID,
    $_POST['schedule_id'] ?: $_REQUEST['calendar_id'] ?: $_GET['edit']);

  if ($editing_schedule && $_POST['schedule_id']) {
    // editing single schedule start/end dates
    $editing_schedule->handle_edit_sched_post();
  }
  else if ($editing_schedule && $_REQUEST['calendar_id']) {
    // calendar date editing page

    $editing_schedule->handle_edit_sched_days_post();

    $page_title = "Edit Calendar";
    $add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
    require DOCUMENT_ROOT."inc/head.php";
    echo admin_navigation();

    echo "<p>".back_button("Back")."</p>";

    echo $editing_schedule->html_instructions();
    echo $editing_schedule->html_calendar_with_editor();

  }
  else if ($_POST['new_schedule']) {
    // submission from "create new schedule" form
    BibleReadingChallenge\Schedule::handle_create_sched_post($site->ID);
  }
  else {
    $page_title = "Manage Schedules";
    $add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
    require DOCUMENT_ROOT."inc/head.php";

    echo admin_navigation();

    if ($_GET['new_schedule']) {
      // create new schedule form
      echo BibleReadingChallenge\Schedule::create_schedule_form();
    }
    else if ($_GET['edit']) {
      // viewing single schedule
      echo "<p>".back_button("Back to schedules")."</p>";

      echo BibleReadingChallenge\Schedule::edit_schedule_form(
          $editing_schedule->ID, 
          $editing_schedule->data('name'), 
          $editing_schedule->data('start_date'), 
          $editing_schedule->data('end_date'), 
          (int)$editing_schedule->data('active'));
    }
    else {
      // all schedules summary
      echo "<h4 class='text-center'>All Schedules</h4>
      <p>
        Click a Schedule's name to edit its start and end dates
        <button style='float: right;' type='button' onclick='window.location = `?new_schedule=1`'>+ Create Schedule</button>
      </p>";

      echo BibleReadingChallenge\Schedule::schedules_table($site->ID, 0);

      $add_to_foot .= 
        cached_file('js', '/js/lib/tableSort.js').
        cached_file('js', '/js/schedules.js');
    }
  }

  require DOCUMENT_ROOT."inc/foot.php";