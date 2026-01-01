<?php
  require __DIR__."/../inc/init.php";
  global $add_to_head, $add_to_foot, $staff, $site;

  if (!$staff) {
    redirect('/');
  }

  $page_title = "Statistics";
  $add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
  require DOCUMENT_ROOT."inc/head.php";

  echo admin_navigation();

  $all_stats_schedule_options = [
    [ 'id' => 0, 'name' => 'All schedules'],
    ...$db->select("
      SELECT id, name 
      FROM schedules 
      WHERE site_id = $site->ID AND user_id IS NULL
      ORDER BY start_date DESC")
  ];
  echo "<h4>Site Statistics</h4>
    <form method='get'>
      For Schedule:
      <select name='schedule_id'>
        ";
  foreach($all_stats_schedule_options as $sched) {
    echo "<option value='$sched[id]' ".($sched['id'] == intval($_GET['schedule_id']) ? 'selected' : '').">".html($sched['name'])."</option>
    ";
  }
  echo "
      </select>
      <span>
        Between dates:
        <input type='date' name='start_date' value='".$_REQUEST['start_date']."'>
        <input type='date' name='end_date' value='".$_REQUEST['end_date']."'>
      </span>
      <button type='submit'>Refresh</button>
    </form>";
  echo "
    <div class='two-columns'>
      ".$site->hourly_reading_canvas((int)$_GET['schedule_id'], $_GET['start_date'], $_GET['end_date'])."
      ".$site->verse_email_stats_canvas((int)$_GET['schedule_id'], $_GET['start_date'], $_GET['end_date'])."
    </div>
  ";

  $add_to_foot .=
    chartjs_js().
    cached_file('js', '/js/statistics.js');

  require DOCUMENT_ROOT."inc/foot.php";
