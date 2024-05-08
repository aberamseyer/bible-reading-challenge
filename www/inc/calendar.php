<?php

  $calendar_sched = $db->row("SELECT * FROM schedules WHERE site_id = ".$site->ID." AND id = ".(int)$_REQUEST['calendar_id']);
  if (!$calendar_sched) {
    redirect('/admin/schedules');
  }

  if ($_REQUEST['get_dates']) {
    // get the reading dates for this schedule
    print_json($db->select("SELECT id, date, passage FROM schedule_dates WHERE schedule_id = $calendar_sched[id]"));
  }

  if ($_POST['edit']) {
    foreach($_POST['days'] as $date => $day) {
      if ($day['id'] && $day['passage']) {
        // update
        $chp_ids = array_column(
          array_column(
            parse_passage($day['passage']),
          'chapter'),
        'id');
        $db->update("schedule_dates", [
          'passage' => $day['passage'],
          'passage_chapter_ids' => json_encode($chp_ids)
        ], "schedule_id = $calendar_sched[id] AND id = ".(int)$day['id']);
      }
      else if ($day['id'] && !$day['passage']) {
        // delete
        $db->query("DELETE FROM schedule_dates WHERE schedule_id = $calendar_sched[id] AND id = ".(int)$day['id']);
        $db->query("DELETE FROM read_dates WHERE schedule_date_id = ".(int)$day['id']);
      }
      else if (!$day['id'] && $day['passage']) {
        // insert
        $chp_ids = array_column(
          array_column(
            parse_passage($day['passage']),
          'chapter'),
        'id');

        $db->insert("schedule_dates", [
          'schedule_id' => $calendar_sched['id'],
          'date' => $date,
          'passage' => $day['passage'],
          'passage_chapter_ids' => json_encode($chp_ids),
          'complete_key' => bin2hex(random_bytes(16))
        ]);
      }
    }
    $_SESSION['success'] = 'Schedule saved';
    redirect("/admin/schedules?calendar_id=".$calendar_sched['id']);
  }

  if ($_REQUEST['fill_dates'] && $_REQUEST['d'] && $_REQUEST['start_book'] && $_REQUEST['start_chp'] && $_REQUEST['days']) {
    // generate a schedule to fill in on the client side
    try {
      $start_date = date_create_from_format('Y-m-d H:i:s', $_REQUEST['fill_dates']." 00:00:00");
    } catch (Exception) { }
    // this is all input validation
    if ($start_date && $start_date->modify('+1 day') && $start_date->format('Y-m-d') >= $calendar_sched['start_date']) {
      // we +1 day bc the client sends us the day the user selected (one day before we start generating)
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

      $book_arr = is_array($_REQUEST['start_book'])
        ? $_REQUEST['start_book']
        : [ $_REQUEST['start_book'] ];
      $chp_arr = is_array($_REQUEST['start_chp'])
        ? $_REQUEST['start_chp']
        : [ $_REQUEST['start_chp'] ];
      $d_arr = is_array($_REQUEST['d'])
        ? $_REQUEST['d']
        : [ $_REQUEST['d'] ];
        
      $result = []; $i = 0;
      foreach(array_combine($book_arr, $chp_arr) as $b => $c) {
        $rate = clamp((int)$d_arr[ $i++ ], 1, 10); // between 1 and 10 chapters per day
        // the id to start AFTER
        $starting_id = $db->col("
          SELECT c.id
          FROM chapters c
          JOIN books b ON b.id = c.book_id
          WHERE b.name = '".$db->esc($b)."'
            AND c.number = ".(int)$c);
        // all the chapters we need, limited by the amount * the rate we are generating
        $chapters = array_reverse($db->select("
          SELECT b.name book, c.number
          FROM chapters c
          JOIN books b ON b.id = c.book_id
          WHERE c.id > ".intval($starting_id)."
          LIMIT ".count($days)*$rate)); // reverse the array so we can use array_pop in the loop for sorting instead of array_shift, which is slower
  
        if ($chapters) { // just to make sure no parameters were funky. lazy input validation.
          $sorted = [];
          // we group each book's chapters into a sub-array
          foreach($days as $day) {
            for($j = 0; $j < $rate; $j++) {
              $chp_row = array_pop($chapters);
              $sorted[$day][ $chp_row['book'] ][] = $chp_row['number'];
            }
          }
          $iter_result = [];
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
            $iter_result[$date] = implode('; ', $references);
          }
        }
        foreach($iter_result as $date => $passage) {
          if (!is_array($result[ $date ])) {
            $result[ $date ] = [];
          }
          $result[ $date ][] = $passage;
        }
      }
      print_json($result);
    }
  }

  $start_date = new Datetime($calendar_sched['start_date']);
  $end_date = new Datetime($calendar_sched['end_date']);

  $page_title = "Edit Schedule Calendar";
  $hide_title = true;
  $add_to_head .= "
  <link rel='stylesheet' href='/css/admin.css' media='screen'>";
  require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  echo admin_navigation();

  echo "
    <p><a href='/admin/schedules'>&lt;&lt; Back</a></p>
    <h5>Editing calendar for '".html($calendar_sched['name'])."'</h5>
    <p><b>".$start_date->format('F j, Y')."</b> through <b>".$end_date->format('F j, Y')."</b></p>
    <h6>Instructions</h6>
    <ul>
      <li><small>Double-click white-space to add/edit/remove a day's reading</li>
      <li>Hover mouse on the left edge of the screen for a reference of how many chapters are in each book</li>
      <li>Use format: <code>Matthew 28; John 1-2</code></li>
      <li>
        To use autofill:
        <ol>
          <li>Fill in two consecutive days</li>
          <li>Highlight the second day</li>
          <li>Click 'Fill after selected'</li>
        </ol>
        Chapters per day will be calculated by the difference between the two days across passages segments.<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;E.g., To read 3 OT chapters and 2 NT chapters each day, fill in two consecutive days with: <code>Genesis 1-3; Matthew 1-2</code> and <code>Genesis 4-6; Matthew 2-4</code>. Click the second day to select it, and choose 'Fill after selected' to populate the calendar.
      </li>
      <li>Only <b>future</b> days can be edited</small></li>
    </ul>";

  // sort books/chapters into some arrays for easier printing
  $book_chapters = $db->select("SELECT name, chapters FROM books");
  
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

  $add_to_foot .= "
  <script>
    const CALENDAR_ID = ".$calendar_sched['id']."
    const BOOK_CHAPTERS = ".json_encode($book_chapters)."
  </script>
  <script src='/js/edit-calendar.js'></script>";
