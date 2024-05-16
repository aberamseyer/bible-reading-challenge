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
        ? $this->db->row("SELECT * FROM schedules WHERE user_id = ".((int)$me['id'])." AND site_id = ".$site->ID)
        : $this->db->row("SELECT * FROM schedules WHERE active = 1 AND site_id = ".$site->ID);
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
      <h5>Editing calendar for '".html($this->data['name'])."'</h5>
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

  public function html_calendar()
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
    echo $this->generate_schedule_calendar(true);
  
    $add_to_foot .= "
      <script>
        const CALENDAR_ID = ".$this->data['id']."
        const BOOK_CHAPTERS = ".json_encode($book_chapters)."
      </script>
      <script src='/js/edit-calendar.js'></script>";
  

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

  public static function create(\Datetime $start_date, \Datetime $end_date, $name, $site, $active)
  {
    return Database::get_instance()->insert('schedules', [
      'site_id' => $site->ID,
      'name' => $name,
      'start_date' => $start_date->format('Y-m-d'),
      'end_date' => $end_date->format('Y-m-d'),
      'active' => 0
    ]);
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
    $s_id = Schedule::create(new \Datetime($this->data('start_date')), new \Datetime($this->data('end_date')), "Copy of ".$this->data('name'), $this->data('site_id'), 0);
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
      SELECT * FROM schedules
      WHERE user_id IS NULL AND site_id = ".intval($site_id)."
      ORDER BY active DESC, start_date DESC") as $id) {
        $schedules[] = new Schedule((int)$id);
    }
    return $schedules;
  }

	public function generate_schedule_calendar($editable = false)
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
    return $this->db->col("
      SELECT COUNT(*)
      FROM read_dates rd
      JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      WHERE user_id = $user_id AND schedule_id = ".$this->ID)
      == $this->db->col("SELECT COUNT(*)
        FROM schedule_dates WHERE schedule_id = ".$this->ID);
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
}