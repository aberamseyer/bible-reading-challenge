<?php
  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  $allow_personal_schedules = $site->data('allow_personal_schedules');

  if ($_REQUEST['get_dates']) {
    print_json(
      $db->select("
      SELECT sd.id, sd.date, sd.passage, rd.id read
      FROM schedule_dates sd
      LEFT JOIN (
        SELECT * FROM read_dates WHERE user_id = $my_id
      ) rd ON rd.schedule_date_id = sd.id
      WHERE schedule_id = $schedule[id]"));
  }



  
  $hide_title = true;
  $page_title = "Schedule".($allow_personal_schedules ? 's' : '');
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";  

  if ($allow_personal_schedules) {
    echo "<div class='admin-navigation'>
        <a class='nav-item ".active_navigation_class($link = '/my-schedule/corporate')."' href='$link'>Corporate</a>
        <a class='nav-item ".active_navigation_class($link = '/my-schedule/personal')."' href='$link'>Personal</a>
      </div>";
  }