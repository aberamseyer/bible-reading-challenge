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

  echo "<p>Click a date to jump to any past reading to complete it.</p>";

  // Generate the calendar for the current month and year
  echo generate_schedule_calendar($schedule);
            
  echo "<script>
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
          tableCell.setAttribute('href', '/today?today=' + date)
          tableCell.onclick = () => window.location = tableCell.getAttribute('href')
          tableCell.classList.add('cursor')

        }
      }
    })
  })
  </script>";
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";