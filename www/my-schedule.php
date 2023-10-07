<?php
  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  if ($_REQUEST['get_dates']) {
    print_json(
      select("
      SELECT sd.id, sd.date, sd.passage, rd.id read
      FROM schedule_dates sd
      LEFT JOIN (
        SELECT * FROM read_dates WHERE user_id = $my_id
      ) rd ON rd.schedule_date_id = sd.id
      WHERE schedule_id = $schedule[id]"));
  }

  $page_title = "Schedule";
  $hide_title = true;
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

  echo "
  <div>
    <h5>Badges</h5>";
  $badges = badges_for_user($my_id);
  if (!$badges) {
    echo "Badges for books you complete will be displayed here.";
  }
  else {
    echo $badges;
  }
  $canvas_width = 300;
  echo "</div>
  <div style='display: flex; justify-content: space-between; align-items: flex-end;'>
    <div>
      <h5>Stats</h5>
      <ul>
        <li>Current / Longest streak: $me[streak] day".xs($me['streak'])." / $me[max_streak] day".xs($me['max_streak'])."</li>
        <li>My chapters read: ".number_format(col(($chp_qry = "
          SELECT SUM(JSON_ARRAY_LENGTH(passage_chapter_ids))
          FROM schedule_dates sd
          JOIN read_dates rd ON rd.schedule_date_id = sd.id")."
          WHERE rd. user_id = $my_id"))."</li>
        <li>My words read: ".number_format(col(sprintf($word_qry = "
          SELECT SUM(
            LENGTH(%s) - LENGTH(REPLACE(%s, ' ', '')) + 1
          ) as word_count
          FROM (
            SELECT value
            FROM schedule_dates sd
            JOIN JSON_EACH(passage_chapter_ids)
            JOIN read_dates rd ON sd.id = rd.schedule_date_id
            %s
          ) chp_ids
          LEFT JOIN verses v ON v.chapter_id = chp_ids.value
        ", $me['trans_pref'], $me['trans_pref'], "WHERE rd.user_id = $my_id")))."</li>
        <li>All-club chapters read: ".number_format(col($chp_qry))."</li>
        <li>All-club words read: ".number_format(col(sprintf($word_qry, 'rcv', 'rcv', "")))."</li>
      </ul>
    </div>
    <div>
      ".four_week_trend_canvas($my_id)."
      <div style='width: ".$canvas_width."px; text-align: center;'>
        <small>4-week reading trend</small>
      </div>
    </div>
  </div>
  <h5>Schedule</h5>
  <p>Click a date to jump to any past reading to complete it.</p>";

  // Generate the calendar for the current month and year
  echo generate_schedule_calendar($schedule);
            
  echo "<script>
  ".four_week_trend_js($canvas_width, 120)."

  const readingDays = document.querySelectorAll('.reading-day:not(.disabled)')
  fetch(`?get_dates=1`).then(rsp => rsp.json())
  .then(data => {
    readingDays.forEach(tableCell => {
      const date = tableCell.getAttribute('data-date')
      const matchingDay = data.find(sd => sd.date === date)
      if (matchingDay) {
        tableCell.querySelector('.label').textContent = matchingDay.passage
        if (matchingDay.read) {
          tableCell.classList.add('active')
        }
        if (!tableCell.classList.contains('future')) {
          tableCell.setAttribute('href', '/?today=' + date)
          tableCell.onclick = () => window.location = tableCell.getAttribute('href')
          tableCell.classList.add('cursor')

        }
      }
    })
  })
  </script>";
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";