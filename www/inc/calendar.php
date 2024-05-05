<?php

  $calendar_sched = new BibleReadingChallenge\Schedule(false, $_REQUEST['calendar_id']);

  if (!$calendar_sched->ID) {
    redirect('/admin/schedules');
  }

  if ($_REQUEST['get_dates']) {
    print_json($calendar_sched->get_dates());
  }

  if ($_POST['edit']) {
    $calendar_sched->edit($_POST['days']);

    $_SESSION['success'] = 'Schedule saved';
    redirect("/admin/schedules?calendar_id=".$calendar_sched->ID);
  }

  if ($_REQUEST['fill_dates'] && $_REQUEST['d'] && $_REQUEST['start_book'] && $_REQUEST['start_chp'] && $_REQUEST['days']) {
    print_json(
      $calendar_sched->fill_dates($_REQUEST['fill_dates'], $_REQUEST['d'], $_REQUEST['start_book'], $_REQUEST['start_chp'], $_REQUEST['days'])
    );
  }


  $page_title = "Edit Schedule Calendar";
  $hide_title = true;
  $add_to_head .= "
  <link rel='stylesheet' href='/css/admin.css' media='screen'>";
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  echo admin_navigation();

  echo "<p><a href='/admin/schedules'>&lt;&lt; Back</a></p>";
  echo $calendar_sched->html_instructions();
  echo $calendar_sched->html_calendar();

