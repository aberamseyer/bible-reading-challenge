<?php

namespace BibleReadingChallenge;

class Schedule {
  public readonly int $ID;
  private readonly array $data;
  private readonly bool $private;
  private readonly Database $db;
  private readonly array $schedule_days;
  private array $just_completed_arr;
  private array $day_completed_arr;

  public function __construct($id_or_private=false)
  {
    global $me, $site;

    $this->db = Database::get_instance();
    if (is_bool($id_or_private)) {
      $data = $id_or_private
        ? $this->db->row("SELECT * FROM schedules WHERE user_id = ".((int)$me['id'])." AND active = 1 AND site_id = ".$site->ID)
        : $this->db->row("SELECT * FROM schedules WHERE user_id IS NULL AND active = 1 AND site_id = ".$site->ID);
    }
    else { // id was passed as an int/string, not a boolean
      $data = $this->db->row("SELECT * FROM schedules WHERE id = ".((int)$id_or_private)." AND site_id = ".$site->ID);
    }
    if ($data) {
      $this->data = $data;
      $this->private = (bool)$data['user_id'];
      $this->ID = $data['id'];
    }
    else {
      $this->data = [];
      $this->ID = 0;
    }
    $this->just_completed_arr = [];
    $this->day_completed_arr = [];
  }

  public function data($key)
  {
    return $this->data[ $key ];
  }

  public function html_instructions()
  {
    $start_date = new \Datetime($this->data['start_date']);
    $end_date = new \Datetime($this->data['end_date']);
  
    return "
      <h3>Editing calendar for '".html($this->data['name'])."'</h3>
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
    
  }

  public function html_calendar_with_editor()
  {
    global $add_to_foot;
    ob_start();
  
    // sort books/chapters into some arrays for easier printing
    $book_chapters = $this->db->select("SELECT name, chapters FROM books");
    
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
    echo $this->html_calendar(true);
  
    $add_to_foot .= "
      <script>
        const CALENDAR_ID = ".$this->data['id']."
        const BOOK_CHAPTERS = ".json_encode($book_chapters)."
      </script>".
      cached_file('js', '/js/edit-calendar.js');
  

    return ob_get_clean();
  }

  public function fill_dates($fill_dates, $differences, $start_books, $start_chapters, $active_days)
  {
    // generate a schedule to fill in on the client side
    try {
      $start_date = date_create_from_format('Y-m-d H:i:s', $fill_dates." 00:00:00");
    } catch (Exception) { }
    // this is all input validation
    if ($start_date && $start_date->modify('+1 day') && $start_date->format('Y-m-d') >= $this->data['start_date']) {
      // we +1 day bc the client sends us the day the user selected (one day before we start generating)
      $end_date = new \Datetime($this->data['end_date']);
      $period = new \DatePeriod(
        $start_date,
        new \DateInterval('P1D'),
        $end_date,
        \DatePeriod::INCLUDE_END_DATE
      );
      $days = [];
      // this array generates a slot for every day that we will return to the client
      foreach($period as $date) {
        // if this day-of-week is in our list of days we want to read
        if (in_array($date->format('N'), $active_days)) {
          $days[] = $date->format('Y-m-d');
        }
      }

      $book_arr = is_array($start_books)
        ? $start_books
        : [ $start_books ];
      $chp_arr = is_array($start_chapters)
        ? $start_chapters
        : [ $start_chapters ];
      $d_arr = is_array($differences)
        ? $differences
        : [ $differences ];
        
      $result = []; $i = 0;
      foreach(array_combine($book_arr, $chp_arr) as $b => $c) {
        $rate = clamp((int)$d_arr[ $i++ ], 1, 10); // between 1 and 10 chapters per day
        // the id to start AFTER
        $starting_id = $this->db->col("
          SELECT c.id
          FROM chapters c
          JOIN books b ON b.id = c.book_id
          WHERE b.name = '".$this->db->esc($b)."'
            AND c.number = ".(int)$c);
        // all the chapters we need, limited by the amount * the rate we are generating
        $chapters = array_reverse($this->db->select("
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
      return $result;
    }
    else {
      // invalid input
      return [ ];
    }
  }

  public function get_dates($user_id)
  {
    return $this->db->select("
      SELECT sd.id, sd.date, sd.passage, rd.id read
      FROM schedule_dates sd
      LEFT JOIN (
        SELECT * FROM read_dates WHERE user_id = ".intval($user_id)."
      ) rd ON rd.schedule_date_id = sd.id
      WHERE sd.schedule_id = ".$this->ID."
      ORDER BY sd.date ASC");
  }

  public function edit($days)
  {
    foreach($days as $date => $day) {
      if ($day['id'] && $day['passage']) {
        // update
        $chp_ids = array_column(
          array_column(
            parse_passage($day['passage']),
          'chapter'),
        'id');
        $this->db->update("schedule_dates", [
          'passage' => $day['passage'],
          'passage_chapter_ids' => json_encode($chp_ids)
        ], "schedule_id = ".$this->ID." AND id = ".(int)$day['id']);
      }
      else if ($day['id'] && !$day['passage']) {
        // delete
        $this->db->query("
          DELETE FROM read_dates
          WHERE id IN (
            SELECT rd.id
            FROM read_dates rd
            JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
            WHERE sd.schedule_id = ".$this->ID." AND rd.schedule_date_id = ".intval($day['id'])."
        )");
        $this->db->query("DELETE FROM schedule_dates WHERE schedule_id = ".$this->ID." AND id = ".(int)$day['id']);
      }
      else if (!$day['id'] && $day['passage']) {
        // insert
        $chp_ids = array_column(
          array_column(
            parse_passage($day['passage']),
          'chapter'),
        'id');

        $this->db->insert("schedule_dates", [
          'schedule_id' => $this->ID,
          'date' => $date,
          'passage' => $day['passage'],
          'passage_chapter_ids' => json_encode($chp_ids),
          'complete_key' => bin2hex(random_bytes(16))
        ]);
      }
    }
  }

  public function update(\Datetime $start_date, \Datetime $end_date, $name)
  {
    $this->db->update("schedules", [
      'name' => $name,
      'start_date' => $start_date->format('Y-m-d'),
      'end_date' => $end_date->format('Y-m-d')
    ], "id = ".$this->ID);

    // delete all scheduled readings that have been invalidated by the new start and end dates
    $this->db->query("
    DELETE FROM schedule_dates
    WHERE schedule_id = ".$this->ID." AND
      (
        date < '".$start_date->format('Y-m-d')."' OR
        date > '".$end_date->format('Y-m-d')."'
      )");
  }

  public static function create(\Datetime $start_date, \Datetime $end_date, $name, $site_id, $active, $user_id=null)
  {
    $values = [
      'site_id' => $site_id,
      'name' => $name,
      'start_date' => $start_date->format('Y-m-d'),
      'end_date' => $end_date->format('Y-m-d'),
      'active' => $active ? 1 : 0,
    ];
    if ($user_id) {
      $values['user_id'] = $user_id;
    }
    return Database::get_instance()->insert('schedules', $values);
  }

  public static function too_many_personal_schedules(int $user_id)
  {
    if ($user_id) {
      $num_schedules = (int) Database::get_instance()->col("SELECT COUNT(*) FROM schedules WHERE user_id = ".$user_id);
      return $num_schedules >= 20;
    }
    return false;
  }

  public function set_active()
  {
    if (!$this->private) {
      $this->db->query("UPDATE schedules SET active = 0 WHERE site_id = ".$this->data['site_id']);
      $this->db->query("UPDATE schedules SET active = 1 WHERE site_id = ".$this->data['site_id']." AND id = ".$this->ID);
    }
  }

  public function delete()
  {
    $this->db->query("DELETE FROM read_dates WHERE schedule_date_id IN(
      SELECT id FROM schedule_dates WHERE schedule_id = ".$this->ID."
    )");
    $this->db->query("DELETE FROM schedule_dates WHERE schedule_id = ".$this->ID);
    $this->db->query("DELETE FROM schedules WHERE site_id = ".$this->data('site_id')." AND id  = ".$this->ID);
  }

  public function duplicate()
  {
    $s_id = Schedule::create(new \Datetime($this->data('start_date')), new \Datetime($this->data('end_date')), "Copy of ".$this->data('name'), $this->data('site_id'), 0, $this->data('user_id') ?: null);
    $this->db->query("
      INSERT INTO schedule_dates (schedule_id, date, passage)
        SELECT $s_id, date, passage
        FROM schedule_dates WHERE schedule_id = ".$this->ID);
    return $s_id;
  }

  public static function schedules_for_site($site_id)
  {
    $schedules = [];
    foreach(Database::get_instance()->cols("
      SELECT id FROM schedules
      WHERE user_id IS NULL AND site_id = ".intval($site_id)."
      ORDER BY active DESC, start_date DESC") as $id) {
        $schedules[] = new Schedule((int)$id);
    }
    return $schedules;
  }

  public static function schedules_for_user($site_id, $user_id)
  {
    if (!$user_id) {
      return Schedule::schedules_for_site($site_id);
    }
    $schedules = [];
    foreach(Database::get_instance()->cols("
      SELECT id FROM schedules
      WHERE 
        user_id = ".intval($user_id)." AND 
        site_id = ".intval($site_id)."
      ORDER BY active DESC, start_date DESC
      ") as $id) {
      $schedules[] = new Schedule((int)$id);
    }
    return $schedules;
  }

	public function html_calendar($editable = false)
  {
		$start_date = new \Datetime($this->data('start_date'));
		$end_date = new \Datetime($this->data('end_date'));

		$period = new \DatePeriod(
			new \Datetime($start_date->format('Y-m').'-01'),
			new \DateInterval('P1M'), // 1 month
			$end_date,
			\DatePeriod::INCLUDE_END_DATE
		);
		
		ob_start();
		if ($editable) {
			echo "<form method='post'>
				<input type='hidden' name='calendar_id' value='".$this->ID."'>";
		}
		foreach ($period as $date) {
			echo "<div class='month table-scroll'>";
			echo "<h6 class='text-center'>".$date->format('F Y')."</h6>";
			echo generate_calendar($date->format('Y'), $date->format('F'), $start_date, $end_date, $editable);
			echo "</div>";
		}
		if ($editable) {
			echo "<br><button type='submit' name='edit' value='1'>Save readings</button></form>";
		}
		return ob_get_clean();
	}

	/**
	 * returns one element from the array returned by get_schedule_days($schedule_id)
	 */
	public function get_reading($datetime)
  {
		$days = $this->get_schedule_days();
		$today = $datetime->format('Y-m-d');
		foreach($days as $day) {
			if ($day['date'] == $today)
				return $day;
		}
		return false;
	}

	/**
	 * [
	 * 		[0] => [
	 * 			'id' => 1
	 * 			'date' => '2023-06-27'
	 *			'reference' => 'Matthew 1; Mark 2',
	 *  		'passages' => [
	 *				'book' => book_row,
	 * 				'chapter' => chapter_row
	 *			],
	 *			...
	 * 		],
	 * 		...
	 * ]
	 */
	public function get_schedule_days()
  {
    if (!isset($this->schedule_days)) {
      $schedule_dates = $this->db->select("
        SELECT * FROM schedule_dates 
        WHERE schedule_id = ".$this->ID."
        ORDER BY date ASC");
      $days = [];
      foreach ($schedule_dates as $sd) {			
        $days[] = [
          'id' => $sd['id'],
          'date' => $sd['date'],
          'reference' => $sd['passage'],
          'passages' => parse_passage($sd['passage']),
          'complete_key' => $sd['complete_key']
        ];
      }
      $this->schedule_days = $days;
    }

		return $this->schedule_days;
	}

	public function completed($user_id)
  {
    $days_in_schedule = $this->db->col("
      SELECT COUNT(*)
      FROM schedule_dates
      WHERE schedule_id = ".$this->ID);

    return $days_in_schedule > 0 && $days_in_schedule == $this->db->col("
      SELECT COUNT(*)
      FROM read_dates rd
      JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      WHERE user_id = $user_id AND schedule_id = ".$this->ID);
	}

  public function set_just_completed($user_id, bool $complete)
  {
    $this->just_completed_arr[ $user_id ] = $complete;
  }

  public function get_just_completed($user_id)
  {
    return $this->just_completed_arr[ $user_id ] ?: false;
  }

  public function day_completed($user_id, $scheduled_reading_id)
  {
    if (!isset($this->day_completed_arr[ $user_id ])) {
      $this->day_completed_arr[ $user_id ] = $this->db->num_rows("
        SELECT id
        FROM read_dates
        WHERE schedule_date_id = $scheduled_reading_id
          AND user_id = $user_id");
    }
    return $this->day_completed_arr[ $user_id ];
  }

  public static function create_schedule_form()
  {
    ob_start();
    echo "<p>".back_button("Back to schedules")."</p>";
    
    echo "<h5>Create New Schedule</h5>";
    echo "<form method='post'>
      <input type='hidden' name='new_schedule' value='1'>

      <label>Start date: <input type='date' name='start_date'></label>
      <label>End date: <input type='date' name='end_date'></label>
      <label>Name: <input type='text' name='name' minlength='1'></label>
      <button type='submit'>Save</button>
    </form>";
    return ob_get_clean();
  }

  public static function edit_schedule_form($id, $name, $start_date, $end_date, $active)
  {
    ob_start();

    $start_date = new \Datetime($start_date);
    $end_date = new \Datetime($end_date);
    echo "<h5>Editing '".html($name)."' schedule</h5>";
    echo "<form method='post'>
      <input type='hidden' name='schedule_id' value='$id'>

      <label>Start date: <input type='date' name='start_date' value='".$start_date->format('Y-m-d')."'></label>
      <label>End date: <input type='date' name='end_date' value='".$end_date->format('Y-m-d')."'></label>
      <label>Name: <input type='text' name='name' minlength='1' value='".html($name)."'></label>
      <button type='submit'>Save</button>
      <button type='submit' ".($active ? "disabled title='You cannot delete the active schedule'" : "")." name='delete' value='1' onclick='return confirm(`Are you sure you want to delete ".html($name)."? This can NEVER be recovered. All existing reading progress, INCLUDING BADGES, will be permanently lost.`)'>Delete Schedule</button>
      <button type='button' onclick='window.location = \"?calendar_id=$id\"'>Edit Calendar</button>
    </form>";
    return ob_get_clean();
  }

  public static function handle_create_sched_post($site_id, $user_id=null)
  {
    $start_date = new \Datetime($_POST['start_date']);
    $end_date = new \Datetime($_POST['end_date']);
    if (!$start_date || !$end_date || $start_date >= $end_date) {
      $_SESSION['error'] = "Start date must be before end date.";
    }
    else if (!$_POST['name']) {
      $_SESSION['error'] = "Schedule must have a name.";
    }
    else if (Schedule::too_many_personal_schedules((int)$user_id)) {
      $_SESSION['error'] = "You are only permitted to create 20 personal schedules.";
    }
    else {
      $new_id = Schedule::create($start_date, $end_date, $_POST['name'], $site_id, 0, $user_id);
      $_SESSION['success'] = "Created schedule.&nbsp;<a href='?calendar_id=$new_id'>Edit new schedule's calendar &gt;&gt;</a>";
      redirect("?edit=$new_id");
    }
  }

  public function handle_edit_sched_days_post($for_user_id=0)
  {
    if ($_REQUEST['get_dates']) {
      print_json($this->get_dates($for_user_id));
    }
    else if ($_REQUEST['fill_dates'] && $_REQUEST['d'] && $_REQUEST['start_book'] && $_REQUEST['start_chp'] && $_REQUEST['days']) {
      print_json(
        $this->fill_dates($_REQUEST['fill_dates'], $_REQUEST['d'], $_REQUEST['start_book'], $_REQUEST['start_chp'], $_REQUEST['days'])
      );
    }
    else if ($_POST['start_date'] && $_POST['end_date']) {
      $start = new \Datetime($_POST['start_date']);
      $end = new \Datetime($_POST['end_date']);
      if (!$start || !$end) {
        $_SESSION['error'] = "Invalid dates";
      }
      else {
        $difference = $start->diff($end);
        if ($start >= $end) {
          $_SESSION['error'] = 'Start date must come before end date';
        }
        else if ($difference->y > 4) {
          $_SESSION['error'] = 'Schedule must be shorter than 4 years';
        }
        else {
          $this->update($start, $end, $this->data('name'));
          $_SESSION['success'] = "Updated your schedule's dates.";
          redirect();
        }
      }
    }
    else if ($_POST['edit']) {
      $this->edit($_POST['days']);

      $_SESSION['success'] = 'Schedule saved';
      redirect();
    }
  }

  public function handle_edit_sched_post()
  {
    if ($_POST['set_active']) {
      $this->set_active();
      $_SESSION['success'] = "<b>".html($this->data('name'))."</b> is now the active schedule";
    }
    else if ($_POST['delete']) {
      if ($this->data('active')) {
        $_SESSION['error'] = "Can't delete the active schedule.";
      }
      else {
        $this->delete();
        $_SESSION['success'] = 'Schedule deleted';
        redirect('?');
      }
    }
    else if ($_POST['duplicate']) {
      if (Schedule::too_many_personal_schedules((int)$this->data('user_id'))) {
        $_SESSION['error'] = "You are only permitted to create up to 20 schedules.";
      }
      else {
        $new_id = $this->duplicate();
        $_SESSION['success'] = "Schedule duplicated.&nbsp;<a href='?calendar_id=$new_id'>Edit new schedule's calendar &gt;&gt;</a>";
      }
      redirect();
    }
    else {
      $start_date = strtotime($this->data('start_date'));
      if ($new_date = strtotime($_POST['start_date']))
        $start_date = $new_date;
      $end_date = strtotime($this->data('end_date'));
      if ($new_date = strtotime($_POST['end_date']))
        $end_date = $new_date;

      $start_date_obj = new \Datetime('@'.$start_date);
      $end_date_obj = new \Datetime('@'.$end_date);
      $interval = $start_date_obj->diff($end_date_obj);
      if ($interval->y > 3) {
        $_SESSION['error'] = "Schedule must be shorter 4 years";
      }
      else if ($start_date_obj >= $end_date_obj) {
        $_SESSION['error'] = "Start date must be before end date.";
      }
      else if (!$_POST['name']) {
        $_SESSION['error'] = "Schedule must have a name.";
      }
      else if (
        ($this->data('start_date') != $start_date_obj->format('Y-m-d') || $this->data('end_date') != $end_date_obj->format('Y-m-d'))
         && $this->db->col("
              SELECT COUNT(*)
              FROM read_dates rd
              JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
              WHERE sd.schedule_id = ".$this->ID)) {
        $_SESSION['error'] = "This schedule has already been started by some readers. You can no longer change the start and end dates.";
      }
      else {
        $this->update($start_date_obj, $end_date_obj, $_POST['name']);

        $_SESSION['success'] = "Saved schedule";
      }   
    }
    redirect();
  }

  public function personal_schedule_instructions($for_user_id)
  {
    return "
      <details ".(count($this->get_dates($for_user_id)) ? "" : "open").">
        <summary>Introduction</summary>
        <p>
          From here, you can create and edit your very own Bible reading schedule. This is useful if you already read the Bible on your own schedule
          and want to track everything on the website. Everything read here will be recorded and count toward your personal streak and statistics, but it will
          not count toward the all-club schedule, goals, or rewards (if any).
        </p>
        <ol>
          <li>Click a schedule's name to edit the start and end dates</li>
          <li>Click 'Edit Calendar' to go to the reading editor</li>
          <li>On that page, follow the editor instructions, and use the popout tool on the left edge of the page to fill in the dates.</li>
          <li>Click 'Save readings'</li>
        </ol>
        <p>If you would like to stop using the personal schedule, you can simply ignore it, or use the 'Clear after selected' button to delete all the future
        readings and choose 'Save readings'</p>
      </details>";
  }

  public static function fill_read_dates_js()
  {
    return "
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
  }

  public static function schedules_table($site_id, $user_id)
  {
    ob_start();
    $my_schedules = Schedule::schedules_for_user($site_id, $user_id);
    echo "
    <table>
      <thead>
        <tr>
          <th data-sort='name'>
            Name
          </th>
          <th data-sort='start'>
            Start
          </th>
          <th data-sort='end'>
            End
          </th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>";

    foreach($my_schedules as $each_schedule) {
        echo "
          <tr class='".($each_schedule->data('active') ? 'active' : '')."'>
            <td data-name><a href='?edit=".$each_schedule->ID."'><small>".html($each_schedule->data('name'))."</small></a></td>
            <td data-start='".$each_schedule->data('start_date')."'><small>".date('F j, Y', strtotime($each_schedule->data('start_date')))."</small></td>
            <td data-end='".$each_schedule->data('end_date')."'><small>".date('F j, Y', strtotime($each_schedule->data('end_date')))."</small></td>
            <td>
              <form method='post'>
                <small>
                  <input type='hidden' name='schedule_id' value='".$each_schedule->ID."'>
                  <button type='submit' name='set_active' value='1' ".($each_schedule->data('active') ? 'disabled' : '').">Set active</button>
                  <button type='submit' name='duplicate' value='1'>Duplicate</button>
                  <button type='button' onclick='window.location = `?calendar_id=".$each_schedule->ID."`'>Edit Calendar</button>
                </small>
              </form>
            </td>
          </tr>";
    }
    echo "
        </tbody>
      </table>
      <br>";
    
    return ob_get_clean();
  }
}