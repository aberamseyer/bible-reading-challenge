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
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

  $canvas_width = 300;
  echo "<p>Click a date to jump to any past reading to complete it.</p>
  <div style='display: flex; justify-content: space-between; align-items: flex-end;'>
    <div>
      <h5>My Stats</h5>
      <ul>
        <li>Chapters read: ".col("
          SELECT SUM(JSON_ARRAY_LENGTH(passage_chapter_ids))
          FROM schedule_dates sd
          JOIN read_dates rd ON rd.schedule_date_id = sd.id
          WHERE rd. user_id = $my_id")."</li>
        <li>Words read: ".number_format(col("
          SELECT SUM(
            LENGTH($me[trans_pref]) - LENGTH(REPLACE($me[trans_pref], ' ', '')) + 1
          ) as word_count
          FROM (
            SELECT value
            FROM schedule_dates sd
            JOIN JSON_EACH(passage_chapter_ids)
            JOIN read_dates rd ON sd.id = rd.schedule_date_id
            WHERE rd.user_id = $my_id
          ) chp_ids
          LEFT JOIN verses v ON v.chapter_id = chp_ids.value
        "))."</li>
      </ul>
    </div>
    <div>
      ".four_week_trend_canvas($my_id)."
      <div style='width: ".$canvas_width."px; text-align: center;'>
        <small>4-week reading trend</small>
      </div>
    </div>
  </div>";

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