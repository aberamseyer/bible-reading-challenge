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

  echo "<p>From here you can jump to any past reading to complete it.</p>";

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
        tableCell.querySelector('small').textContent = matchingDay.passage
        if (matchingDay.read) {
          tableCell.classList.add('active')
        }
        if (!tableCell.classList.contains('future')) {
          const span = tableCell.querySelector('.date')
          const link = document.createElement('a')
          link.classList.add('date')
          link.setAttribute('href', '/?today=' + date)
          link.textContent = span.textContent
          span.replaceWith(link)
        }
      }
    })
  })
  </script>";
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";