<?php

  require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

  if (!$staff) {
    redirect('/');
  }

  $calendar_sched = row("SELECT * FROM schedules WHERE id = ".(int)$_REQUEST['id']);
  if (!$calendar_sched) {
    redirect('/manage/schedules');
  }

  if ($_REQUEST['get_dates']) {
    // get the reading dates for this schedule
    print_json(select("SELECT id, date, passage FROM schedule_dates WHERE schedule_id = $calendar_sched[id]"));
  }

  if ($_POST['edit']) {
    foreach($_POST['days'] as $date => $day) {
      if ($day['id'] && $day['passage']) {
        // update
        update("schedule_dates", [
          'passage' => $day['passage']
        ], "id = ".(int)$day['id']);
      }
      else if ($day['id'] && !$day['passage']) {
        // delete
        query("DELETE FROM schedule_dates WHERE id = ".(int)$day['id']);
        query("DELETE FROM read_dates WHERE schedule_date_id = ".(int)$day['id']);
      }
      else if (!$day['id'] && $day['passage']) {
        // insert
        insert("schedule_dates", [
          'schedule_id' => $_POST['schedule_id'],
          'date' => $date,
          'passage' => $day['passage']
        ]);
      }
    }
  }

  if ($_REQUEST['fill_dates'] && $_REQUEST['rate'] && $_REQUEST['start_book'] && $_REQUEST['start_chp'] && $_REQUEST['days']) {
    // generate a schedule to fill in on the client side
    try {
      $start_date = date_create_from_format('Y-m-d H:i:s', $_REQUEST['fill_dates']." 00:00:00");
    } catch (Exception) { }
    // this is all input validation
    if ($start_date && $start_date->modify('+1 day') && $start_date->format('Y-m-d') >= $calendar_sched['start_date']) {
      // we +1 day bc the client sends us the day the user selected (one day before we start generating)
      $rate = max(1, min(10, (int)$_REQUEST['rate'])); // clamp rate between 1 and 10 chps/day
      $end_date = new Datetime($calendar_sched['end_date']);
      $period = new DatePeriod(
        $start_date,
        new DateInterval('P1D'),
        $end_date,
        DatePeriod::INCLUDE_END_DATE
      );
      $days = [];
      // this array generates a slot for every day that we will return to the client
      foreach($period as $date) {
        // if this day-of-week is in our list of days we want to read
        if (in_array($date->format('N'), $_REQUEST['days'])) {
          $days[] = $date->format('Y-m-d');
        }
      }
      // the id to start AFTER
      $starting_id = col("
        SELECT c.id
        FROM chapters c
        JOIN books b ON b.id = c.book_id
        WHERE b.name = '".db_esc($_REQUEST['start_book'])."'
          AND c.number = ".(int)$_REQUEST['start_chp']);
      // all the chapters we need, limited by the amount * the rate we are generating
      $chapters = array_reverse(select("
        SELECT b.name book, c.number
        FROM chapters c
        JOIN books b ON b.id = c.book_id
        WHERE c.id > ".intval($starting_id)."
        LIMIT ".count($days)*$rate)); // reverse the array so we can use array_pop in the loop for sorting instead of array_shift, which is slower

      if ($chapters) { // just to make sure no parameters were funky. lazy input validation.
        $sorted = [];
        // we group each book's chapters into a sub-array
        foreach($days as $day) {
          for($i = 0; $i < $rate; $i++) {
            $chp_row = array_pop($chapters);
            $sorted[$day][ $chp_row['book'] ][] = $chp_row['number'];
          }
        }
        $result = [];
        // we build references by pulling the last and first elements out of each sub-array
        foreach($sorted as $date => $book_arr) {
          $references = [];
          foreach($book_arr as $book => $chp_arr) {
            if ($end = array_pop($chp_arr)) { // sanity check
              if ($chp_arr) {
                $begin = array_shift($chp_arr);
                $references[] = $book." ".$begin."-".$end;
              }
              else {
                $references[] = $book." ".$end;
              }
            }
          }
          $result[$date] = implode('; ', $references);
        }
        print_json($result);
      }
    }
  }

  $start_date = new Datetime($calendar_sched['start_date']);
  $end_date = new Datetime($calendar_sched['end_date']);

  $page_title = "Edit Schedule Calendar";
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  echo "<style>
  #editor {
    position: fixed;
    top: 0;
    left: -290px;
    width: 300px;
    z-index: 1;
    background: var(--color-bg);
    padding: 1rem;
    border: 2px solid var(--color-bg-alt);
    transition: .2s left;
  }
  #editor:hover {
    left: -1px;
  }
  .chapters {
    display: flex;
    flex-flow: row wrap;
  }
  .chapters small {
    width: 50%;
  }
  .edit-input {
    font-size: 70%;
    padding: 0;
    width: 75px;
    height: 35px;
  }
  .today .date, .future .date {
    cursor: pointer;
  }
  </style>";

  echo "
    <p><a href='/manage/schedules'>&lt;&lt; Back to schedules</a></p>
    <h5>Editing calendar for '".html($calendar_sched['name'])."'</h5>
    <p><b>".$start_date->format('F j, Y')."</b> through <b>".$end_date->format('F j, Y')."</b></p>
    <p><small>Double-click white-space to add/edit/remove a day's reading<br>
    Hover mouse on the left side for a reference of how many chapters are in each book<br>
    Use format: <b>\"Matthew 28; John 1-2\"</b><br>
    Only future days can be edited</small></p>";

  // sort books/chapters into some arrays for easier printing
  $book_chapters = select("SELECT name, chapters FROM books");
  
  // fixed editor
  echo "
  <div id='editor'>
    <div>Chapters in each book</div>
    <div class='chapters'>";
    for($i = 0; $i < 39; $i++)
      echo "<small>".$book_chapters[$i]['name'].": ".$book_chapters[$i]['chapters']."</small>";
  echo "
  </div><br>
  <div class='chapters'>";
      for($i = 39; $i < 66; $i++)
        echo "<small>".$book_chapters[$i]['name'].": ".$book_chapters[$i]['chapters']."</small>";
  echo "
  </div><br>
    <div>
      <button type='button' id='fill' disabled>Fill after selected</button>&nbsp;
      <small><input type='number' id='chps-per-day' min='1' max='10' style='width: 50px; padding: 0.5rem;' value='1'> chps/day</small><br>
      <div style='display: flex; justify-content: space-between; align-items: center; padding: 0 7px;'>
        <label><input type='checkbox' name='days[]' value='7' checked> S</label>
        <label><input type='checkbox' name='days[]' value='1' checked> M</label>
        <label><input type='checkbox' name='days[]' value='2' checked> T</label>
        <label><input type='checkbox' name='days[]' value='3' checked> W</label>
        <label><input type='checkbox' name='days[]' value='4' checked> T</label>
        <label><input type='checkbox' name='days[]' value='5' checked> F</label>
        <label><input type='checkbox' name='days[]' value='6' checked> S</label>
      </div>
      <button type='button' id='clear' disabled>Clear after selected</button>
    </div>
  </div>";
  echo generate_schedule_calendar($calendar_sched, true);

  echo "
  <script>
    const SCHEDULE_ID = ".$calendar_sched['id']."
    const BOOK_CHAPTERS = ".json_encode($book_chapters)."
  </script>
  <script src='/js/edit-calendar.js'></script>";

  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";