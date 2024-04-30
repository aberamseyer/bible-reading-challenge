<?php
  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  require "index.php";  
  
  echo "<h1>$page_title</h1>";
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
      Array.from(document.querySelectorAll('.active')).at(-1).scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
    </script>";
    
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";