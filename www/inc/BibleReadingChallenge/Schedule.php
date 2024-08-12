<?php

namespace BibleReadingChallenge;

class Schedule {
  public readonly int $ID;
  private readonly array $data;
  private readonly bool $private;
  private readonly Database $db;
  private array $just_completed_arr;

  /**
   * @param site_id       int         required. the site for the schedule
   * @param id_or_private bool|int    optional. if an int is passed, it should be the id for a schedule
   *                                  if TRUE is passed, the third parameter must be passed, and it implies the user's active schedule
   * @param user_id       bool|int    the id of the user for the schedule to get
   */
  public function __construct(int $site_id, $id_or_private=false, $user_id=false)
  {
    $this->db = Database::get_instance();
    if (is_bool($id_or_private)) {
      $data = $id_or_private
        ? $this->db->row("SELECT * FROM schedules WHERE user_id = ".((int)$user_id)." AND active = 1 AND site_id = ".$site_id)
        : $this->db->row("SELECT * FROM schedules WHERE user_id IS NULL AND active = 1 AND site_id = ".$site_id);
    }
    else { // id was passed as an int/string, not a boolean
      $data = $this->db->row("SELECT * FROM schedules WHERE id = ".((int)$id_or_private)." AND site_id = ".$site_id);
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
      <h3>Editing calendar for '".html($this->data('name'))."'</h3>
      <p>
        <b>".$start_date->format('F j, Y')."</b> through <b>".$end_date->format('F j, Y')."</b><br>
        <small>To edit these dates, go to the <a href='".($this->data('user_id') ? '/my-schedule/personal' : '/admin/schedules')."?edit=".$this->ID."'>schedule editing</a> page.<br>
        Only <b>future</b> days can be edited</small>
      </p>
      <details>
        <summary><b>Instructions</b></summary>
        <small>
          Hover mouse on the left edge of the screen for a book reference and some autofill tools<br>
          You have many options to make filling these days easier:
          <h6>Manually</h6>
          <ul>
            <li>Type in the boxes to enter verse references. Use the <kbd>Tab</kbd>key with <kbd>Copy</kbd> and <kbd>Paste</kbd> to make this fast</li>
            <li>Available verse formats:
              <ul>
                <li>Full chapters: <code>Matthew 28</code></li>
                <li>Multiple Chapters: <code>John 1-2</code></li>
                <li>Partial Chapters: <code>Matthew 1:1-29</code></li>
                <li>Verses across chapters: <code>John 7:53-8:9</code></li>
                <li>Exactly one verse: <code>Zech 6:13</code></li>
              </ul>
              Verse abbreviations can be used, and any of these options can be combined with a <code>;</code> (e.g., <code>Eph 2:21-22; Jude 1; Galatians 3-4</code>)
            </li>
          </ul>
          <h6>Autofill</h6>
          To use autofill, begin by selecting a day by clicking it's number.
          <ul>
            <li>Clear button
              <ul>
                <li>Select a date</li>
                <li>Choose \"Clear after selected\"</li>
                <li>All the dates will be cleared following the selected date</li>
              </ul>
            </li>
            <li>Day-of-week checkboxes apply to all fill modes. Any un-selected days will not be included when filling dates.</li>
            <li>Fill by chapter:
              <ol>
                <li>Fill in two consecutive days</li>
                <li>Select the second date</li>
                <li>Choose \"Fill chapters to end\"</li>
                <li>Chapters per day will be calculated by the difference between the two days across passages segments.<br>
                    E.g., To read 3 OT chapters and 2 NT chapters each day, fill in two consecutive days with: <code>Genesis 1-3; Matthew 1-2</code> and <code>Genesis 4-6; Matthew 2-4</code>. Click the second day to select it, and choose 'Fill' to populate the calendar.</li>
              </ol>
            </li>
            <li>Fill with a 1-year plan:
              <ul>
                <li>Select a date</li>
                <li>Choose \"Fill from 1-yr plan\"</li>
                <li>Choose the type of pre-planned schedule you want</li>
                <li>Click \"Fill\"<br>
                  Notes:
                  <ul>
                    <li>Increments are based on a 365-day plan.</li>
                    <li>If the FIRST date on the calendar is selected, the system will try to evenly pack all the scheduled readings into as many calendar days as you have on the schedule.</li>
                    <li>If any other date is selected, daily portions will be filled starting from that day. They are not guaranteed to finish the schedule if not enough days are left on your calendar.</li>
                  </ul>
                </li>
              </ul>
            </li>
          </ul>
          <h6>Moving Dates</h6>
          When a date is selected, two sets of arrows appear:
          <ul>
            <li>Clicking the single arrow left or right will \"push\" all dates in that direction, stopping at an empty calendar date</li>
            <li>Clicking the double arrow left or right will merge that date's reading with the one immediately to its left or right</li>
          </ul>
        </small>
      </details>
      <small>When you're happy with the state of the calendar, <mark>DONT FORGET to click \"Save readings\"</mark> at the bottom!</small>";
    
  }

  public function html_calendar_with_editor()
  {
    global $add_to_foot;
    ob_start();
  
    // sort books/chapters into some arrays for easier printing
    $book_chapters = [];
    foreach($this->db->select("
      SELECT b.name, JSON_GROUP_ARRAY(c.verses ORDER BY c.number) chapters
      FROM books b
      JOIN chapters c ON c.book_id = b.id
      GROUP BY b.name
      ORDER BY b.id") as $b) {
        $book_chapters[] = [
          'name' => $b['name'],
          'chapters' => json_decode($b['chapters'], true)
        ];
      }
    
    // fixed editor
    echo "
    <div id='editor'>
      <div>Chapters in each book</div>
      <div class='chapters'>";
      for($i = 0; $i < 39; $i++)
        echo "<small>".$book_chapters[$i]['name'].": ".count($book_chapters[$i]['chapters'])."</small>";
    echo "
    </div><br>
    <div class='chapters'>";
        for($i = 39; $i < 66; $i++)
          echo "<small>".$book_chapters[$i]['name'].": ".count($book_chapters[$i]['chapters'])."</small>";
    echo "
    </div><br>
      <div>
        <label for='fill-mode'>Fill Mode</label>
        <select id='fill-mode'>
          <option value='automatic' selected>Fill from 1-yr plan</option>
          <option value='chapters'>Fill chapters to end</option>
        </select>
        <select id='schedule-sel'>
        ";
    foreach(scandir(SCHEDULE_DIR) as $file) {
      $absolute_path = SCHEDULE_DIR.$file;
      if (is_file($absolute_path) && pathinfo($absolute_path, PATHINFO_EXTENSION) === 'txt') {
        $fp = fopen($absolute_path, 'r');
        if ($fp) {
          echo "<option value='".html($file)."'>".html(fgets($fp))."</option>";
        }
        fclose($fp);
      }
    }
    echo "
        </select>
        <button type='button' id='fill' disabled>Fill</button>&nbsp;
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
        const BOOKS_RE = ".BOOKS_RE."
        const BOOKS_ABBR_LOOKUP = ".json_encode(
          array_combine(
            array_map('strtolower', array_keys(BOOK_NAMES_AND_ABBREV)), 
            array_column(BOOK_NAMES_AND_ABBREV, 'name'))
          )."
      </script>".
      cached_file('js', '/js/edit-calendar.js');
  

    return ob_get_clean();
  }

  public function fill_dates($fill_dates, $differences, $start_books, $start_chapters, $fill_mode, $fill_with, $active_days)
  {
    // generate a schedule to fill in on the client side
    try {
      $start_date = date_create_from_format('Y-m-d H:i:s', $fill_dates." 00:00:00");
    } catch (\Exception) { }
    // this is all input
    if ($start_date && $start_date->format('Y-m-d') >= $this->data['start_date']) {
      if ($fill_mode === 'chapters') {
        // we +1 day bc the client sends us the day the user selected (one day before we start generating)
        $start_date->modify('+1 day');
      }
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

      $result = [];
      if ($fill_mode === 'chapters') {
        $book_arr = is_array($start_books)
          ? $start_books
          : [ $start_books ];
        $chp_arr = is_array($start_chapters)
          ? $start_chapters
          : [ $start_chapters ];
        $d_arr = is_array($differences)
          ? $differences
          : [ $differences ];
          
        $i = 0;
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
  
          $iter_result = [];
          if ($chapters) { // just to make sure no parameters were funky. lazy input validation.
            $sorted = [];
            // we group each book's chapters into a sub-array
            foreach($days as $day) {
              for($j = 0; $j < $rate; $j++) {
                $chp_row = array_pop($chapters);
                if ($chp_row) { // this will be empty if we run out of chapters at the end of the bible
                  $sorted[$day][ $chp_row['book'] ][] = $chp_row['number'];
                }
              }
            }
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

      }
      else { // $fill_mode === 'automatic'
        $fp = false;
        foreach(scandir(SCHEDULE_DIR) as $file) {
          $absolute_path = SCHEDULE_DIR.$file;
          if (is_file($absolute_path) && pathinfo($absolute_path, PATHINFO_EXTENSION) === 'txt') {
            if ($fill_with == $file) {
              $fp = fopen($absolute_path, 'r');
              break;
            }
          }
        } 

        if ($fp) {
          // first, calculate the number of lines in the file "just in case"
          $fp2 = fopen($absolute_path, 'r');
          $lines = -1; // -1 to discount the title line
          while ($l = fgets($fp2)) {
            if (trim($l))
              $lines += 1;
          }
          fclose($fp2);
          $total_days_to_fill = count($days);
          $lines_per_day = floor($total_days_to_fill ? $lines / $total_days_to_fill : 0);
          $remainder = 0;
          if ($lines % $total_days_to_fill) {
            $remainder = $lines % $total_days_to_fill;
            $lines_per_day += 1;
          }

          // now we build the array
          fgets($fp); // dump title line
          $days = array_reverse($days);
          $curr_book = false;
          $has_book_regex = '/((?:\d )?\w+) \d+/i';
          if ($this->data('start_date') === $fill_dates) {
            while(!feof($fp) && $date = array_pop($days)) {
              // if we're filling from the first avaialble date in the schedule,
              // we assume the user wants the whole schedule packed into as many days as their schedule allows
              $value_arr = [];
              for($i = 0; $i < $lines_per_day && $line = fgets($fp); $i++) {
                if (preg_match($has_book_regex, $line, $matches)) {
                  $curr_book = $matches[1];
                }
                if (!preg_match($has_book_regex, $line)) {
                  $line = $curr_book.' '.$line;
                }  
                foreach(explode(';', trim($line)) as $piece) {
                  $value_arr[] = trim($piece);
                }
              }
              if ($remainder) {
                $remainder -= 1;
                if (!$remainder) {
                  $lines_per_day -= 1;
                }
              }
              
              
              $result[ $date ] = $value_arr;
            }
          }
          else {
            // regular filling
            while(($line = fgets($fp)) && ($date = array_pop($days))) {
              if (preg_match($has_book_regex, $line, $matches)) {
                $curr_book = $matches[1];
              }
              if (!preg_match($has_book_regex, $line)) {
                $line = $curr_book.' '.$line;
              }  
              $result[ $date ] = explode('; ', trim($line));
            }
          }
          fclose($fp);
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
    $today = date('Y-m-d');
    foreach($days as $date => $day) {
      if ($date <= $today) {
        // today and prior are not editable
        continue;
      }

      if ($day['id'] && $day['passage']) {
        // update
        $passages = parse_passages($day['passage']);
        $passage_readings = parsed_passages_to_passage_readings($passages);
        

        $this->db->update("schedule_dates", [
          'passage' => $day['passage'],
          'passage_chapter_readings' => json_encode($passage_readings),
          'word_count' => passage_readings_word_count($passage_readings)
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
        $passages = parse_passages($day['passage']);
        $passage_readings = parsed_passages_to_passage_readings($passages);
        $word_count = passage_readings_word_count($passage_readings);

        create_schedule_date(
          $this->ID,
          $date,
          $day['passage'],
          parsed_passages_to_passage_readings($passages),
          $word_count
        );
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
      // site-wide schedules
      $this->db->query("UPDATE schedules SET active = 0 WHERE user_id IS NULL AND site_id = ".$this->data['site_id']);
      $this->db->query("UPDATE schedules SET active = 1 WHERE user_id IS NULL AND site_id = ".$this->data['site_id']." AND id = ".$this->ID);
      SiteRegistry::get_site($this->data('site_id'))->invalidate_stats();
    }
    else {
      // private schedules
      $this->db->query("UPDATE schedules SET active = 0 WHERE user_id =".$this->data('user_id')." AND site_id = ".$this->data['site_id']);
      $this->db->query("UPDATE schedules SET active = 1 WHERE user_id =".$this->data('user_id')." AND site_id = ".$this->data['site_id']." AND id = ".$this->ID);
      SiteRegistry::get_site($this->data('site_id'))->invalidate_stats($this->data('user_id'));
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
        $schedules[] = new Schedule((int)$site_id, (int)$id);
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
      $schedules[] = new Schedule((int)$site_id, (int)$id);
    }
    return $schedules;
  }

  public function total_words_in_schedule()
  {
    static $total_words;
    if ($total_words == null) {
      $total_words = (int)$this->db->col("
        SELECT SUM(word_count)
        FROM schedule_dates sd
        WHERE sd.schedule_id = ".$this->ID);
    }

    return $total_words;
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

  public function emoji_data($for_user_id=null, $start_timestamp=null, $end_timestamp=null)
  {
    $days_in_schedule = $this->total_words_in_schedule();
    if (!$days_in_schedule) {
      return [];
    }
    $query = "
      SELECT ROUND(SUM(sd.word_count) * 1.0 / $days_in_schedule * 100, 2) percent_complete, u.emoji, u.id, u.name
      FROM schedule_dates sd
      JOIN read_dates rd ON sd.id = rd.schedule_date_id
      JOIN users u ON u.id = rd.user_id
      WHERE sd.schedule_id = ".$this->ID." AND %s
      GROUP BY u.id
      ORDER BY
        %s
      LIMIT 20";
    
    return $this->db->select(
      $for_user_id
        ? sprintf($query,
            "1",
            "CASE WHEN u.id = $for_user_id THEN 9999999999 -- sort me first, then the top readers
            ELSE percent_complete
            END DESC")
        : sprintf($query, 
            "rd.timestamp BETWEEN $start_timestamp AND $end_timestamp",
            "percent_complete DESC")
    );
  }

  public function get_next_reading(\DateTime $after_date)
  {
    $next_date = $this->db->col("
      SELECT date
      FROM schedule_dates
      WHERE date > '".$after_date->format('Y-m-d')."'");
    return $next_date ?
      $this->get_schedule_date(new \DateTime($next_date))
      : false;
  }

	/**
	 * [
	 * 		[0] => [
	 * 			'id' => 1
	 * 			'date' => '2023-06-27'
	 *			'reference' => 'Matthew 1; Mark 2',
	 *  		'passages' => [
	 *				'book' => book_row,
	 * 				'chapter' => chapter_row,
   *        'word_count' => number
   *        'range' => [ begin, end ]
   *        'verses' => [ ...$verse_rows ]
	 *			],
	 *			...
	 * 		],
	 * 		...
	 * ]
	 */
	public function get_schedule_date(\DateTime $specific_date)
  {
    $sd = $this->db->row("
      SELECT *
      FROM schedule_dates 
      WHERE schedule_id = ".$this->ID."
        AND date = '".$specific_date->format('Y-m-d')."'
      ORDER BY date ASC");
    $day = false;
    if ($sd) {
      $day = [
        'id' => $sd['id'],
        'date' => $sd['date'],
        'reference' => $sd['passage'],
        'passages' => SiteRegistry::get_site($this->data('site_id'))->parse_passage_from_json($sd['passage_chapter_readings']),
        'complete_key' => $sd['complete_key']
      ];
    }
    return $day;
	}

  public function days_in_schedule()
  {
    return $this->db->col("
      SELECT COUNT(*)
      FROM schedule_dates
      WHERE schedule_id = ".$this->ID);
  }

	public function completed($user_id)
  {
    $days_in_schedule = $this->days_in_schedule();

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
    return $this->db->num_rows("
      SELECT id
      FROM read_dates
      WHERE schedule_date_id = $scheduled_reading_id
        AND user_id = $user_id");
  }

  public static function create_schedule_form()
  {
    global $add_to_foot;
    ob_start();
    echo "<p>".back_button("Back to schedules")."</p>";
    
    echo "<h5>Create New Schedule</h5>";
    echo "<form method='post'>
      <input type='hidden' name='new_schedule' value='1'>
      <label>Name: <input type='text' name='name' minlength='1'></label>

      <label>
        Start date: <input type='date' name='start_date'>
      </label>
      <label>
        End date: <input type='date' name='end_date'>
      </label>
      <p>
        <b data-alldays></b> Calendar Days<br>
        <b data-weekdays></b> Weekdays
      </p>
      <button type='submit'>Save</button>
    </form>";
    $add_to_foot .= cached_file('js', '/js/schedule-bounds.js');

    return ob_get_clean();
  }

  public static function edit_schedule_form($id, $name, $start_date, $end_date, $active)
  {
    global $add_to_foot;
    ob_start();

    $start_date = new \Datetime($start_date);
    $end_date = new \Datetime($end_date);
    echo "<h5>Editing '".html($name)."' schedule</h5>";
    echo "<form method='post'>
      <input type='hidden' name='schedule_id' value='$id'>
      <label>Name: <input type='text' name='name' minlength='1' value='".html($name)."'></label>
      <label>
        Start date: <input type='date' name='start_date' value='".$start_date->format('Y-m-d')."'>
      </label>
      <label>
        End date: <input type='date' name='end_date' value='".$end_date->format('Y-m-d')."'>
      </label>
      <p>
        <b data-alldays></b> Calendar Days<br>
        <b data-weekdays></b> Weekdays
      </p>
      <button type='submit'>Save</button>
      <button type='submit' ".($active ? "disabled title='You cannot delete the active schedule'" : "")." name='delete' value='1' onclick='return confirm(`Are you sure you want to delete ".html($name)."? This can NEVER be recovered. All existing reading progress, INCLUDING BADGES, will be permanently lost.`)'>Delete Schedule</button>
      <button type='button' onclick='window.location = \"?calendar_id=$id\"'>Edit Calendar</button>
    </form>";
    $add_to_foot .= cached_file('js', '/js/schedule-bounds.js');
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
    else if ($_REQUEST['fill_dates'] && $_REQUEST['start_book'] && $_REQUEST['start_chp'] && $_REQUEST['d'] && $_REQUEST['fill_mode'] && $_REQUEST['fill_with'] && $_REQUEST['days']) {
      print_json(
        $this->fill_dates($_REQUEST['fill_dates'], $_REQUEST['d'], $_REQUEST['start_book'], $_REQUEST['start_chp'], $_REQUEST['fill_mode'], $_REQUEST['fill_with'], $_REQUEST['days'])
      );
    }
    else if ($_POST['edit']) {
      $this->edit($_POST['days']);

      $_SESSION['success'] = 'Schedule saved';

      // all users affected by this schedule need to have their stats invalidated
      SiteRegistry::get_site($this->data('site_id'))->invalidate_stats($for_user_id);

      redirect('?calendar_id='.$this->ID);
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
        else if ($difference->y > 3) {
          $_SESSION['error'] = 'Schedule must be shorter than 4 years';
        }
        else if (!$_POST['name']) {
          $_SESSION['error'] = "Schedule must have a name.";
        }
        else if (
          ($this->data('start_date') != $start->format('Y-m-d') || $this->data('end_date') != $end->format('Y-m-d'))
          && $this->db->col("
                SELECT COUNT(*)
                FROM read_dates rd
                JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
                WHERE sd.schedule_id = ".$this->ID)) {
          $_SESSION['error'] = "This schedule has already been started by some readers. You can no longer change the start and end dates.";
        }
        else {
          $this->update($start, $end, $_POST['name']);

          $_SESSION['success'] = "Saved schedule";
        }
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
          From here, you can create and edit your very own Bible reading schedules. This is useful if you already read the Bible on your own
          and desire to track everything on this website. Everything read here will be recorded and count toward your personal streak and statistics, but it will
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