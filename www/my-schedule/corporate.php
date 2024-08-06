<?php
  require __DIR__."/../inc/init.php";


  $calendar_sched = new BibleReadingChallenge\Schedule($site->ID);
  if ($_REQUEST['get_dates']) {
    print_json($calendar_sched->get_dates($my_id));
  }

  $allow_personal = $site->data('allow_personal_schedules');

  $page_title = "Schedule".($allow_personal ? 's' : '');
  require DOCUMENT_ROOT."inc/head.php";  

  if ($allow_personal) {
    echo do_nav([
      ['/my-schedule/corporate', 'Corporate'],
      ['/my-schedule/personal', 'Personal'],
    ], true, 'admin-navigation');
  }
  
  echo "<h1>".($allow_personal ? "Corporate " : "")." Schedule</h1>";
  echo "<p>Click a date to jump to any past reading to complete it</p>";
  
  echo $schedule->html_calendar();
  
  $add_to_foot .= BibleReadingChallenge\Schedule::fill_read_dates_js();
    
  require DOCUMENT_ROOT."inc/foot.php";