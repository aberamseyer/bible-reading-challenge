<?php

	require_once __DIR__."./MailSender/Mailsender.php";
	require_once __DIR__."./MailSender/MailsenderSES.php";
	require_once __DIR__."./MailSender/MailsenderSendgrid.php";

	function db($alt_db = null) {
		static $db;
		if (!$db) {
			$db = new SQLite3(DB_FILE);
			$db->busyTimeout(250);
		}
		return $alt_db ?: $db;
	}
	
	function query ($query, $return = "", $db = null) {
    if ($db = null) $db = db();

		$db = db($db);
		$result = $db->query($query);
		if (!$result) {
			echo "<p><b>Warning:</b> A sqlite3 error occurred: <b>" . $db->lastErrorMsg() . "</b></p>";
			debug($query);
		}
		if ($return == "insert_id")
			return $db->lastInsertRowID();
		if ($return == "num_rows")
			return $db->changes();
		return $result;
	}

	function select ($query, $db = null) {
    if ($db = null) $db = db();

		$rows = query($query, null, $db);
		for ($result = []; $row = $rows->fetchArray(); $result[] = $row) {
			foreach(array_keys($row) as $key)
				if (is_numeric($key))
					unset($row[$key]);
		}
		return $result;
	}

	function row ($query, $db = null) {
    if ($db = null) $db = db();

		$results = select($query, $db);
		return $results[0];
	}

	function col ($query, $db = null) {
    if ($db = null) $db = db();

		$row = query($query, null, $db)->fetchArray();
		return $row ? $row[0] : null;
	}

	function cols ($query, $db = null) {
    if ($db = null) $db = db();
    
		$rows = query($query, null, $db);
		if ($rows) {
			$results = [];
			while ($row = $rows->fetchArray(SQLITE3_NUM))
				$results[] = $row[0];
			return $results;
		}
		return null;
	}

	function format_db_vals ($db_vals, array $options = []) {
		$options = array_merge([
			"source" => $_POST
		], $options);
		return map_assoc(function ($col, $val) use ($options) {

			// Was a value provided for this column ("col" => "val") or not ("col")?
			$no_value_provided = is_int($col);
			if ($no_value_provided)
				$col = $val;

			// The modifiers should not contain regex special characters. If they do, then we will have to use preg_quote().
			$modifiers = [
				"nullable" => "__",
				"literal" => "##"
			];

			// Check for column modifiers
			if (preg_match("/^(" . implode("|", $modifiers) . ")/", $col,$matches))
				$col = substr($col, 2);

			// Keep track of whether each modifier is present (true) or not
			$modifiers = map_assoc(function ($name, $symbol) use ($matches) {
				return [$name => $matches && $matches[1] == $symbol];
			}, $modifiers);

			$val = $no_value_provided ? $options["source"][$col] : $val;
			// If it's not literal, then transform the value
			if (!$modifiers["literal"])
				$val = $modifiers["nullable"] && ($val === null || $val === false || $val === 0 || !strlen($val))
					? "NULL"
					: ("'" . db_esc($val) . "'");

			return [ $col => $val ];
		}, $db_vals);
	}

	function get_num_params (callable $callback) {
		try {
			return (new ReflectionFunction($callback))->getNumberOfParameters();
		}
		catch (ReflectionException $e) {}
	}

	function map_assoc (callable $callback, array $arr) {
		$ret = [];
		foreach($arr as $k => $v) {
			$u =
				get_num_params($callback) == 1
					? $callback($v)
					: $callback($k, $v);
			$ret[key($u)] = current($u);
		}
		return $ret;
	}

	/**
	 * @param $table
	 * @param $vals	array	An associative array of columns and values to update.
	 * 						Each value will be converted to a string UNLESS its
	 * 						corresponding column name begins with "__", in which
	 *						case its literal value will be used.
	 * @param $where
	 */
	function update ($table, $vals, $where, $db = null) {
    if ($db = null) $db = db();

		$SET = array();
		foreach (format_db_vals($vals) as $col => $val) {
			$col = preg_replace("/^__/", "", $col, 1, $use_literal);
			$SET[] = "$col = $val";
		}

		query("
			UPDATE $table
			SET " . implode(",", $SET) . "
			WHERE $where
		", null, $db);
	}

	function insert ($table, array $db_vals, array $options = [], $db = null) {
    if ($db = null) $db = db();

		$db_vals = format_db_vals($db_vals, $options);
		return query("
			INSERT INTO $table (" . implode(", ", array_keys($db_vals)) . ")
			VALUES (" . implode(", ", array_values($db_vals)) . ")
		", "insert_id", $db);
	}

	function num_rows ($query, $db = null) {
    if ($db = null) $db = db();

		$i = 0;
		$res = query($query, null, $db);
		while ($res->fetchArray(SQLITE3_NUM))
			$i++;
		return $i;
	}

	function html ($str, $lang_flag = ENT_HTML5) {
		return htmlspecialchars($str, ENT_QUOTES|$lang_flag);
	}

	function debug() {
		global $my_id;
		$args = func_get_args();
		$num_args = count($args);
		if (!$num_args)
			die("<pre><b>No arguments passed to debug()!</b></pre>");

		$output = [];
		$die = $args[0] !== "NO_DIE";
		if (!$die) {
			array_shift($args);
			--$num_args;
		}

		// Loop through arguments
		foreach ($args as $i => $arg) {
			$var_log_msg = "<pre><b>" . ($num_args > 1 ? "Argument <i>" . ($i + 1) . "</i> of <i>$num_args</i><br>" : "") . "Type: <i>(" . gettype($arg) . ")</i></b><br>";
			if (is_bool($arg))
				$var_log_msg .= $arg ? "TRUE" : "FALSE";
			else
				$var_log_msg .= html(print_r($arg, 1));
			$var_log_msg .= "</pre>";
			$output[] = $var_log_msg;
		}
		if ($die)
			$output[] = "<pre><b>Ending script execution</b></pre>";
		else
			$output[] = "<pre><b>NO_DIE passed as the first argument to debug(); continuing execution now</b></pre>";

		echo "
			<div style='border:2px dashed red;margin:20px 0;padding:0 10px'>
				<pre style='font-size:18px;font-weight:bold'>debug() output beginning:</pre>
				<hr>
				" . implode("<hr>", $output) . "
				<hr>
				<pre style='font-size:18px;font-weight:bold'>Stack trace:</pre>
				<pre>";
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		echo "
				</pre>
			</div>
		";

		if ($die)
			die;
	}

	function db_esc ($string, $alt_db = null) {
		$db = db($alt_db);
		return $db->escapeString($string);
	}

	function db_esc_like ($string, $alt_db = null) {
		return db_esc(str_replace(
			["\\", "_", "%"],
			["\\\\", "\\_", "\\%"],
			$string
		), $alt_db);
	}

	function redirect($url = false) {
		header("Location: ".($url ?: $_SERVER['REDIRECT_URL']));
		die;
	}

	function perm_redirect($url) {
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: $url");
		die;
	}

	function print_json($arr) {
		header("Content-type: application/json");
		echo json_encode($arr);
		die;
	}

	function cors() {
    
	    // Allow from any origin
	    if (isset($_SERVER['HTTP_ORIGIN'])) {
	        // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
	        // you want to allow, and if so:
	        header("Access-Control-Allow-Origin: $_SERVER[HTTP_ORIGIN]");
	        header('Access-Control-Allow-Credentials: true');
	        header('Access-Control-Max-Age: 86400');    // cache for 1 day
	    }
	    
	    // Access-Control headers are received during OPTIONS requests
	    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        
	        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
	            // may also be using PUT, PATCH, HEAD etc
	            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
	        
	        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
	            header("Access-Control-Allow-Headers: $_SERVER[HTTP_ACCESS_CONTROL_REQUEST_HEADERS]");
	    
	        die;
	    }
	}

	function curl_post_json($url, $headers, $arr) {
		// $debug = true;

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($arr));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json', ...$headers ]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		if ($debug) {
			$temp_file = fopen('php://temp', 'w+');
			curl_setopt($curl, CURLOPT_VERBOSE, true);
			curl_setopt($curl, CURLOPT_STDERR, $temp_file);
		}

		$response = curl_exec($curl);

		if ($debug) {
			// Get the verbose output
			rewind($temp_file);
			$verboseData = stream_get_contents($temp_file);
			fclose($temp_file);
		}

		if(curl_errno($curl)) {
				$error = curl_error($curl);
				debug($error);
		}
		curl_close($curl);

		if ($debug) {
			// Dump the cURL data
			echo "---- cURL Verbose Output ----\n";
			echo nl2br($verboseData);
			echo "---- Response Body ----\n";
			echo nl2br($response);
		}

		return $response;
	}

	function active_navigation_class($link) {
		return strpos($_SERVER['REQUEST_URI'], $link) !== false
			? 'active-page' : '';
	}

	function admin_navigation() {
		$nav = "
			<div class='admin-navigation'>";

		foreach([
			['users', 'Users'],
			['progress', 'Progress'],
			['schedules', 'Schedules']
		] as list($link, $title)) {
			$nav .= "<a class='nav-item ".active_navigation_class($link)."' href='/admin/$link'>$title</a>";
		}
		return $nav."</div>";
	}

	function navigation() {
		global $staff;

		$nav = "
    	<div class='navigation'>";

		$nav_elements = [
			['/my-schedule', 'My schedule'],
			['/today', 'Today'],
			['/profile', 'Profile']
		];
		if ($staff) {
			array_unshift($nav_elements, ['/admin', 'Admin']);
		}

		foreach($nav_elements as list($link, $title)) {
			$nav .= "<a class='".active_navigation_class($link)."' href='$link'>$title</a>";
		}

		return $nav."</div>";
	}

	function get_active_schedule() {
		return row("SELECT * FROM schedules WHERE active = 1");
	}

	function allowed_schedule_date(Datetime $date) {
		global $staff;
		return $staff || (new Datetime()) > $date;
	}

	function generate_calendar($year, $month, $start_date, $end_date, $editable = false) {
		$calendar = '
			<table>
				<thead>
					<tr>';
		$weekdays = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
		foreach ($weekdays as $weekday) {
			$calendar .= '
				<th>' . $weekday . '</th>';
		}
		$calendar .= '
			</tr>
		</thead>
		<tbody>';
		
		// first day of the month
		$current_day = date_create_from_format("Y-F-d", "$year-$month-01");
		$current_day->setTime(0, 0, 0, 0);
		
		//  of days in the month
		$days_in_month = $current_day->format('t');
		
		// day of the week for the first day of the month
		$current_day_of_week = $current_day->format('w');
		
		// first week
		$calendar .= '
			<tr>';
		for ($i = 0; $i < $current_day_of_week; $i++) {
			$calendar .= "
				<td></td>";
		}
		
		// Iterate through each day of the month
		$today = new Datetime();
		for ($day = 1; $day <= $days_in_month; $day++) {
			// Start a new row if it's Sunday
			if ($current_day_of_week == 0) {
				$calendar .= '
					</tr>
					<tr>';
			}
			
			// each individual day on the calendar
			$class = '';
			if ($current_day < $start_date || $current_day > $end_date)
				$class = "inactive";
			else if ($current_day->format('Y-m-d') > $today->format('Y-m-d'))
				$class .= " future";
			else if ($current_day->format('Y-m-d') < $today->format('Y-m-d'))
				$class .= " past";
			else
				$class .= " today";
			if ($current_day->format('Y-m-d') == $start_date->format('Y-m-d'))
				$class .= " start";
			if ($current_day->format('Y-m-d') == $end_date->format('Y-m-d'))
				$class .= " end";
			$calendar .= "
				<td class='reading-day $class' data-date='".$current_day->format('Y-m-d')."'>
					<span class='date'>$day</span><br>";
			if ($editable && !$inactive)
				$calendar .= "
					<input type='hidden' data-passage name='days[".$current_day->format('Y-m-d')."][passage]' value=''>
					<input type='hidden' data-id name='days[".$current_day->format('Y-m-d')."][id]' value=''>";
			$calendar .= "
					<small class='label'></small>
				</td>";
			
			// Move to the next day and update the day of the week
			$current_day->modify("+1 day");
			$current_day_of_week = $current_day->format('w');
		}
		
		// Fill in the blank cells after the last day of the month
		for ($i = $current_day_of_week; $i > 0 && $i < 7; $i++) {
			$calendar .= "
				<td></td>";
		}
		
		$calendar .= '
				</tr>
			</tbody>
		</table>';
		
		return $calendar;
	}
	function generate_schedule_calendar($schedule, $editable = false) {
		$start_date = new Datetime($schedule['start_date']);
		$end_date = new Datetime($schedule['end_date']);

		$period = new DatePeriod(
			new Datetime($start_date->format('Y-m').'-01'),
			new DateInterval('P1M'), // 1 month
			$end_date,
			DatePeriod::INCLUDE_END_DATE
		);
		
		ob_start();
		if ($editable) {
			echo "<form method='post'>
				<input type='hidden' name='calendar_id' value='$schedule[id]'>";
		}
		foreach ($period as $date) {
			echo "<div class='month table-scroll'>";
			echo "<h6 class='text-center'>".$date->format('F Y')."</h6>";
			echo generate_calendar($date->format('Y'), $date->format('F'), $start_date, $end_date, $editable);
			echo "</div>";
		}
		if ($editable) {
			echo "<br><button type='submit' name='edit' value='1'>Save Changes</button></form>";
		}
		return ob_get_clean();
	}

	/**
	 * returns one element from the array returned by get_schedule_days($schedule_id)
	 */
	function get_reading($datetime, $schedule_id) {
		$days = get_schedule_days($schedule_id);
		$today = $datetime->format('Y-m-d');
		foreach($days as $day) {
			if ($day['date'] == $today)
				return $day;
		}
		return false;
	}

	/**
	 * This parses a passage that appears in the schedule calendar
	 * $passage string e.g., Matthew 1-3; Mark 12-14; Jude 1
	 * @return [
	 *   'book' => (book info)
	 *   'chapter' => (chapter info)
	 * ]
	 */
	function parse_passage($passage) {
		$parts = explode(";", $passage);
		$chps = [];
		foreach($parts as $reference) {
			$reference = trim($reference);
			
			$pieces = explode(" ", $reference);
			if (count($pieces) > 3) {
				$book_str = $pieces[0]." ".$pieces[1]." ".$pieces[2];
				$chapter_str = $pieces[3];
			}
			else if(count($pieces) > 2) {
				// book name contains a space (e.g., 1 John)
				$book_str = $pieces[0]." ".$pieces[1];
				$chapter_str = $pieces[2];
			}
			else {
				// all other cases
				$book_str = $pieces[0];
				$chapter_str = $pieces[1];
			}
			$chapters = [ $chapter_str ];
			if (strpos($chapter_str, '-') !== false) {
				// reference contains multiple chapters via a '-'
				list($begin, $end) = explode("-", $chapter_str);
				$chapters = range($begin, $end);
			}
			
			// match the book and chapters to the database
			$book_row = row("SELECT * FROM books WHERE name = '".db_esc($book_str)."'");
			foreach(select("
				SELECT *
				FROM chapters
				WHERE book_id = $book_row[id]
					AND number IN(".implode(',', $chapters).")")
			as $chapter_row) {
				$chps[] = [
					'book' => $book_row,
					'chapter' => $chapter_row
				];
			}
		}
		return $chps;
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
	function get_schedule_days($schedule_id) {
		static $schedules = [];
		if ($schedules[$schedule_id]) {
			return $schedules[$schedule_id];
		}

		$schedule_dates = select("SELECT * FROM schedule_dates WHERE schedule_id = $schedule_id");
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
		$schedules[$schedule_id] = $days;

		return $days;
	}

	/*
	 * @param scheduled_reading array the return value of get_reading()
	 * @param trans string one of the translations
	 * @param complete_key string the key to complete the reading from a row in the user's table
	 * @param schedule the schedule from which we are generating a reading
	 * @param email bool whether this is going in an email or not
	 * @return the html of all the verses we are reading
	 */ 
	function html_for_scheduled_reading($scheduled_reading, $trans, $complete_key, $schedule, $email=false) {
		ob_start();
		$article_style = "";
		if ($email) {
			$article_style = "style='line-height: 1.618; font-size: 1.1rem;'";
		}
		echo "<article $article_style>";
		if ($scheduled_reading) {
			$style = "";
			if ($email) {
				$style = "style='text-align: center; font-size: 1.4rem;'";
			}
			foreach($scheduled_reading['passages'] as $passage) {
				echo "<h4 class='text-center' $style>".$passage['book']['name']." ".$passage['chapter']['number']."</h4>";
				$book = $passage['book'];
				$verses = select("SELECT number, $trans FROM verses WHERE chapter_id = ".$passage['chapter']['id']);
	
				$abbrev = json_decode($passage['book']['abbreviations'], true)[0];

				$ref_style = "class='ref'";
				$verse_style = "class='verse-text'";
				if ($email) {
					$ref_style = "style='font-weight: bold; user-select: none;'";
					$verse_style = "style='margin-left: 1rem;'";
				}
				foreach($verses as $verse_row) {
					echo "
						<div class='verse'><span $ref_style>".$verse_row['number']."</span><span $verse_style>".$verse_row[$trans]."</span></div>";
				}
			}
			$btn_style = "";
			$form_style = "id='done' class='center'";
			if ($email) {
				$btn_style = "style='color: rgb(249, 249, 249); padding: 2rem; width: 100%; background-color: #404892;'";
				$form_style = "style='display: flex; justify-content: center; margin: 7px auto; width: 50%;'";
			}
			$copyright_text = json_decode(file_get_contents(__DIR__."/../../copyright.json"), true);
			$copyright_style = "";
			if ($email) {
				$copyright_style = "font-size: 75%;";
			}
			echo "
			<div style='text-align: center; $copyright_style'><small><i>".$copyright_text[$trans]."</i></small></div>
			<form action='".SCHEME."://".DOMAIN."/today' method='get' $form_style>
				<input type='hidden' name='complete_key' value='$complete_key'>
				<input type='hidden' name='today' value='$scheduled_reading[date]'>
				<button type='submit' name='done' value='1' $btn_style>Done!</button>
			</form>";
		}
		else {
			echo "<p>Nothing to read today!</p>";
	
			// look for the next time to read in the schedule.
			$days = get_schedule_days($schedule['id']);
			$today = new Datetime();
			foreach($days as $day) {
				$dt = new Datetime($day['date']);
				if ($today < $dt) {
					echo "<p>The next reading will be on <b>".$dt->format('F j')."</b>.</p>";
					break;
				}
			}
		}
		echo "</article>";
		return ob_get_clean();
	}

function log_user_in($id) {
	global $me;
	$_SESSION['my_id'] = $id;
	if ($_SESSION['login_redirect']) {
		redirect($_SESSION['login_redirect']);
		$_SESSION['login_redirect'] = null;
	}
	else {
		redirect("/");
	}
}

function xs($num) {
	if ($num == 1) return '';
	else return 's';
}

function help($tip) {
	return "<span class='cursor' title='$tip'>?&#x20DD;</span>";
}

function four_week_trend_canvas($user_id) {
	$data = json_encode(four_week_trend_data($user_id));
	return "<canvas title='$data' data-graph='$data'></canvas>";
}

function four_week_trend_data($user_id) {
	// reach back 5 weeks so that we don't count the current week in the graph
	return cols("
		SELECT COALESCE(count, 0) count
		FROM (
			-- generates last 4 weeks to join what we read to
			WITH RECURSIVE week_sequence AS (
				SELECT
					date('now', 'localtime') AS cdate
				UNION ALL
				SELECT date(cdate, '-7 days')
				FROM week_sequence
				LIMIT 5
			)
			SELECT strftime('%Y-%W', cdate) AS week FROM week_sequence      
		) sd
		LEFT JOIN (
			-- gives the number of days we have read each week
			SELECT strftime('%Y-%W', sd.date) AS week, COUNT(rd.user_id) count
			FROM read_dates rd
			JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
			WHERE user_id = $user_id
			GROUP BY week
		) rd ON rd.week = sd.week
		WHERE sd.week >= strftime('%Y-%W', DATE('now', '-35 days', 'localtime'))
		ORDER BY sd.week ASC
		LIMIT 4");
}

function four_week_trend_js($width, $height) {
	return "
	const canvas = document.querySelectorAll('canvas');
	canvas.forEach(c => {
		const data = JSON.parse(c.getAttribute('data-graph'));
		const ctx = c.getContext('2d');
		
		// Set the canvas dimensions
		c.width = $width;
		c.height = $height;
		
		// Calculate the scale factors
		const maxDataValue = 8;
		const scaleFactor = c.height / maxDataValue;
		
		// Draw the sparkline
		ctx.beginPath();
		ctx.moveTo(0, c.height - data[0] * scaleFactor);
		for (let i = 1; i < data.length; i++) {
			const x = (c.width / (data.length - 2)) * i; // changed from (data.length - 1)
			const y = c.height - data[i] * scaleFactor;
			const prevX = (c.width / (data.length - 2)) * (i - 1); // changed from (data.length - 1)
			const prevY = c.height - data[i - 1] * scaleFactor;
			const cpx = (prevX + x) / 2;
			const cpy = (prevY + y) / 2;
			
			ctx.quadraticCurveTo(prevX, prevY, cpx, cpy);
		}
		
		let gradient = ctx.createLinearGradient(0, 0, 200, 0);
		gradient.addColorStop(0, 'rgb(63, 70, 143)');
		gradient.addColorStop(1, 'rgb(219, 184, 100)');
		ctx.strokeStyle = gradient;
		
		ctx.lineWidth = 1;
		ctx.stroke();
	})";
}

function day_completed($my_id, $schedule_date_id) {
	return num_rows("
		SELECT id
		FROM read_dates
		WHERE schedule_date_id = $schedule_date_id
			AND user_id = $my_id");
}

function number_chapters_in_book_read($book_id, $user_id) {
	return num_rows("
      SELECT json_each.value
      FROM read_dates rd
      JOIN schedule_dates sd, json_each(sd.passage_chapter_ids) ON sd.id = rd.schedule_date_id
      WHERE json_each.value IN (SELECT id FROM chapters WHERE book_id = $book_id)
        AND user_id = $user_id
      GROUP BY json_each.value");
}

function all_users($stale = false) {
  $nine_mo = strtotime('-9 months');
  if ($stale) {
    $where = "last_seen < '$nine_mo' OR (last_seen IS NULL AND date_created < '$nine_mo')";
  }
  else {
    // all users
    $where = "last_seen >= '$nine_mo' OR (last_seen IS NULL AND date_created >= '$nine_mo')";
  }
  return select("
    SELECT u.id, u.name, u.email, u.staff, u.date_created, u.last_seen, MAX(rd.timestamp) last_read, u.email_verses, streak, max_streak
    FROM users u
    LEFT JOIN read_dates rd ON rd.user_id = u.id
    WHERE $where
    GROUP BY u.id
    ORDER BY LOWER(name) ASC");
}

function toggle_all_users($initial_count) {

  echo "<div id='toggle-all-wrap'><div><b id='all-count'>$initial_count</b> reader".xs($initial_count)."</div>
    <label>
      <input type='checkbox' id='toggle-active'>
      Show those who have never read
    </label>
  </div>";
	echo "<style>
		#toggle-all-wrap {
			margin: 7px 0;
			display: flex;
			align-items: center;
			justify-content: space-between;
		}
	</style>";
	echo "<script>

	document.addEventListener('DOMContentLoaded', function() {
		// show/hide users who have never read via checkbox
		const count = document.getElementById('all-count')
		const countRows = Array.from(document.querySelectorAll('table tbody tr'))
		document.getElementById('toggle-active').addEventListener('click', function() {
			if (this.checked) {
				countRows.forEach(row => row.classList.remove('hidden'))
				count.textContent = countRows.length
			}
			else {
				countRows.forEach(row =>
					row.classList.toggle('hidden',
						row.querySelector('[data-last-read]').getAttribute('data-last-read') === '2100-09-15'))
				
				count.textContent = countRows.filter(x => !x.classList.contains('hidden')).length
			}
		})
	})
	</script>";
}

function badges_for_user($user_id) {
	return cols("
		SELECT b.name, COUNT(*) unique_chapters_read
		FROM
		(SELECT json_each.value chapter_id, b.id book_id, c.number
					FROM read_dates rd
					JOIN schedule_dates sd, json_each(sd.passage_chapter_ids) ON sd.id = rd.schedule_date_id
					JOIN chapters c ON c.id = json_each.value
					JOIN books b ON b.id = c.book_id
						AND user_id = $user_id
					GROUP BY json_each.value) chapter_read_counts
		JOIN books b ON b.id = book_id
		GROUP BY book_id
		HAVING unique_chapters_read = b.chapters");
}

function badges_html_for_user($user_id) {
	$books = select("SELECT id, name FROM books ORDER BY id");
	ob_start();
	$badges = badges_for_user($user_id);
  foreach([
    // [0, 10],
    // [17, 5],
    // [22, 17],
    [39, 4],
    [43, 22]
  ] as $section) {
		echo "<div class='badges'>";
		foreach(array_slice($books, $section[0], $section[1]) as $book) {
			if(in_array($book['name'], $badges)) {
				echo "<img class='badge' src='/img/badge/nt/rgb-small/".($book['id']-39).".png'>";
			}
		}
		echo "</div>";
  }
	return ob_get_clean();
}

function last_read_attr($last_read) {
	return "data-last-read='".date('Y-m-d', $last_read ?: "4124746800")."'";
}