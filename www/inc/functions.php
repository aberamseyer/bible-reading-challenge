<?php

	function db($alt_db = null) {
		static $db;
		if (!$db) {
			$db = new SQLite3(DB_PATH);
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

	function redirect($url) {
		header("Location: $url");
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

	function navigation() {
		global $staff;

		$active_navigation_class = function($link) {
			return $_SERVER['REQUEST_URI'] == $link
				? 'active-page' : '';
		};
		$nav = "
    <div id='navigation'>";
		if ($staff) {
			$nav .= "<a class='".$active_navigation_class('/manage/users')."' href='/manage/users'>Users</a>";
			$nav .= "<a class='".$active_navigation_class('/manage/schedules')."' href='/manage/schedules'>Schedules</a>";
		}

		foreach([
			['/schedule', 'My schedule'],
			['/', 'Today'],
			['/event/check-in', 'Check-in'],
			['/auth/logout', 'Log out']
		] as list($link, $title)) {
			$nav .= "<a class='".$active_navigation_class($link)."' href='$link'>$title</a>";
		}
		return $nav."</div>";
	}

  function send_register_email($to, $link) {		
		$body = [
			"from" => [
				"email" => "uofichristiansoncampus@gmail.com",
				"name" => "U of I Christians on Campus"
			],
			"template_id" => "d-834e3be872e84d1eb57a7f2b7d4c5bec",
			"personalizations" => [
				[
					"to" => [[ "email" => $to ]],
					"dynamic_template_data" => [ "confirm_link" => $link ]
				]
			]
		];
		curl_post_json("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.SENDGRID_API_KEY], $body);
  }

	function send_forgot_password_email($to, $link) {
		$body = [
			"from" => [
				"email" => "uofichristiansoncampus@gmail.com",
				"name" => "U of I Christians on Campus"
			],
			"template_id" => "d-da3c099d74854e919f8819a6f7d9e274",
			"personalizations" => [
				[
					"to" => [[ "email" => $to ]],
					"dynamic_template_data" => [ "reset_link" => $link ]
				]
			]
		];
		curl_post_json("https://api.sendgrid.com/v3/mail/send", [ 'Authorization: Bearer '.SENDGRID_API_KEY], $body); 
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
				<td class='reading-day $class' data-date='".$current_day->format('Y-m-d')."'><span class='date'>$day</span><br>";
			if ($editable && !$inactive)
				$calendar .= "<input type='hidden' data-passage name='days[".$current_day->format('Y-m-d')."][passage]' value=''>
					<input type='hidden' data-id name='days[".$current_day->format('Y-m-d')."][id]' value=''>";
			$calendar .= "<small class='label'></small></td>";
			
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
				<input type='hidden' name='schedule_id' value='$schedule[id]'>";
		}
		foreach ($period as $date) {
			echo "<div class='month'>";
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
	function get_reading($datetime) {
		global $schedule;
		$days = get_schedule_days($schedule['id']);
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
	function get_schedule_days($schedule_id) {
		$schedule_dates = select("SELECT * FROM schedule_dates WHERE schedule_id = $schedule_id");
		$days = [];
		foreach ($schedule_dates as $sd) {
			// we have a portion to read today
			$parts = explode(";", $sd['passage']);
			$chps = [];
			foreach($parts as $reference) {
				$reference = trim($reference);
				
				$pieces = explode(" ", $reference);
				if(count($pieces) > 2) {
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
						AND number IN(".implode(',', $chapters).")") as $chapter_row
				) {
					$chps[] = [
						'book' => $book_row,
						'chapter'=> $chapter_row
					];
				}
			}
			$days[] = [
				'id' => $sd['id'],
				'date' => $sd['date'],
				'reference' => $sd['passage'],
				'passages' => $chps
			];
		}
		return $days;
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