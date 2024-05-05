<?php
  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";


  $calendar_sched = new BibleReadingChallenge\Schedule(false);
  if ($_REQUEST['get_dates']) {
    print_json($calendar_sched->get_dates());
  }

  $hide_title = true;
  $page_title = "Schedule".($allow_personal_schedules ? 's' : '');
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";  

  echo do_nav([
    ['/my-schedule/corporate', 'Corporate'],
    ['/my-schedule/personal', 'Personal'],
  ], true, 'admin-navigation');
  
  echo "<h1>".($allow_personal_schedules ? "Corporate " : "")." Schedule</h1>";
  echo "<p>Click a date to jump to any past reading to complete it</p>";
  
  echo generate_schedule_calendar($schedule);
  
  $add_to_foot .= "
    <script>
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
      // Array.from(document.querySelectorAll('.active')).at(-1).scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
    </script>";
    
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";