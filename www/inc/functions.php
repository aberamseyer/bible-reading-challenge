<?php

require_once __DIR__."/MailSender/MailSender.php";
require_once __DIR__."/MailSender/MailSenderSES.php";
require_once __DIR__."/MailSender/MailSenderSendgrid.php";
require_once __DIR__."/BibleReadingChallenge/Site.php";
require_once __DIR__."/BibleReadingChallenge/Database.php";

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
		$debug = false;
		// $debug = true;

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($arr));
		curl_setopt($curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json', ...$headers ]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		/*
		if ($debug) {
			$temp_file = fopen('php://temp', 'w+');
			curl_setopt($curl, CURLOPT_VERBOSE, true);
			curl_setopt($curl, CURLOPT_STDERR, $temp_file);
		}
		*/

		$response = curl_exec($curl);

		/*
		if ($debug) {
			// Get the verbose output
			rewind($temp_file);
			$verboseData = stream_get_contents($temp_file);
			fclose($temp_file);
		}
		*/

		if(curl_errno($curl)) {
				$error = curl_error($curl);
				debug($error);
		}
		curl_close($curl);

		/*
		if ($debug) {
			// Dump the cURL data
			echo "---- cURL Verbose Output ----\n";
			echo nl2br($verboseData);
			echo "---- Response Body ----\n";
			echo nl2br($response);
		}
		*/

		return $response;
	}

	function active_navigation_class($link) {
		return strpos($_SERVER['REQUEST_URI'], $link) !== false
			? 'active-page' : '';
	}

	function admin_navigation() {
		global $me;

		$nav = "
			<div class='admin-navigation'>";

		$arr = [
			['users', 'Users'],
			['progress', 'Progress'],
			['schedules', 'Schedules'],
			['customize', 'Customize']
		];
		if ($me['id'] == 1) {
			$arr[] = ['sites', 'Sites'];
		}

		foreach($arr as list($link, $title)) {
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
			if ($editable)
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
			$db = BibleReadingChallenge\Database::get_instance();
			$book_row = $db->row("SELECT * FROM books WHERE name = '".$db->esc($book_str)."'");
			foreach($db->select("
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

		$db = BibleReadingChallenge\Database::get_instance();
		$schedule_dates = $db->select("SELECT * FROM schedule_dates WHERE schedule_id = $schedule_id");
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

	function schedule_completed($user_id, $schedule_id) {
		$db = BibleReadingChallenge\Database::get_instance();
		return $db->col("SELECT COUNT(*)
			FROM read_dates rd
			JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
			WHERE user_id = $user_id AND schedule_id = $schedule_id")
			== $db->col("SELECT COUNT(*)
				FROM schedule_dates WHERE schedule_id = $schedule_id");
	}

function log_user_in($id) {
	global $me;
	$_SESSION['my_id'] = $id;
	if ($_SESSION['login_redirect']) {
		$redir = $_SESSION['login_redirect'];
		$_SESSION['login_redirect'] = null;
		redirect($redir);
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
	return "<canvas title='$data' data-graph='$data' width='200' style='margin: auto;'></canvas>";
}

function four_week_trend_data($user_id) {
	$site = BibleReadingChallenge\Site::get_site();
	$db = BibleReadingChallenge\Database::get_instance();
	// reach back 5 weeks so that we don't count the current week in the graph
	$values = $db->select("
		SELECT COALESCE(count, 0) count, day_start
		FROM (
			-- generates last 4 weeks to join what we read to
			WITH RECURSIVE week_sequence AS (
				SELECT
					date('now', '".$site->TZ_OFFSET." hours') AS cdate
				UNION ALL
				SELECT date(cdate, '-7 days')
				FROM week_sequence
				LIMIT 5
			)
			SELECT strftime('%Y-%W', cdate) AS week, strftime('%Y-%m-%d', cdate) AS day_start FROM week_sequence      
		) sd
		LEFT JOIN (
			-- gives the number of days we have read each week
			SELECT strftime('%Y-%W', sd.date) AS week, COUNT(rd.user_id) count
			FROM read_dates rd
			JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
			WHERE user_id = $user_id
			GROUP BY week
		) rd ON rd.week = sd.week
		WHERE sd.week >= strftime('%Y-%W', DATE('now', '-35 days', '".$site->TZ_OFFSET." hours'))
		ORDER BY sd.week ASC
		LIMIT 4");
		return array_column($values, 'count', 'day_start');
}

function day_completed($my_id, $schedule_date_id) {
	$db = BibleReadingChallenge\Database::get_instance();
	return $db->num_rows("
		SELECT id
		FROM read_dates
		WHERE schedule_date_id = $schedule_date_id
			AND user_id = $my_id");
}

function number_chapters_in_book_read($book_id, $user_id) {
	$db = BibleReadingChallenge\Database::get_instance();
	return $db->num_rows("
      SELECT json_each.value
      FROM read_dates rd
      JOIN schedule_dates sd, json_each(sd.passage_chapter_ids) ON sd.id = rd.schedule_date_id
      WHERE json_each.value IN (SELECT id FROM chapters WHERE book_id = $book_id)
        AND user_id = $user_id
      GROUP BY json_each.value");
}

function toggle_all_users($initial_count) {
	global $add_to_foot;

  echo "<div id='toggle-all-wrap'><div><b id='all-count'>$initial_count</b> reader".xs($initial_count)."</div>
		<label>
			<input type='search' id='filter-table' placeholder='Search..'>
		</label>
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
	$add_to_foot .= "<script>

	document.addEventListener('DOMContentLoaded', function() {
		// search box
		document.getElementById('filter-table').addEventListener('keyup', e => {
			const rows = document.querySelectorAll('table tbody tr')
			if (e.target.value) {
				rows.forEach(row => 
					row.classList.toggle('filtered', 
						!(~row.querySelector('td:first-child').textContent.toLowerCase().indexOf(e.target.value))))
			}
			else {
				rows.forEach(row => row.classList.remove('filtered'))
			}
		})
		
		// hidden/shown stale users
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
						row.querySelector('[data-last-read]').getAttribute('data-last-read') === '2200-01-01'))
				
				count.textContent = countRows.filter(x => !x.classList.contains('hidden')).length
			}
		})
	})
	</script>";
}

function badges_for_user($user_id) {
	$db = BibleReadingChallenge\Database::get_instance();
	return $db->cols("
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
	$db = BibleReadingChallenge\Database::get_instance();
	$books = $db->select("SELECT id, name FROM books ORDER BY id");
	ob_start();
	$badges = badges_for_user($user_id);
  foreach([
    // [0, 10],
    // [17, 5],
    // [22, 17],
    [39, 4],
    [43, 23]
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
	return "data-last-read='".($last_read ? date('Y-m-d', $last_read) : "2200-01-01")."'";
}

function words_read($user = 0, $schedule_id = 0) {
	$db = BibleReadingChallenge\Database::get_instance();
	$word_qry = "
			SELECT SUM(word_count)
			FROM schedule_dates sd
			JOIN JSON_EACH(passage_chapter_ids)
			JOIN chapters c on c.id = value
			JOIN read_dates rd ON sd.id = rd.schedule_date_id
			WHERE 1 %s
	";
	$schedule_where = $schedule_id ? " AND sd.schedule_id = ".$schedule_id : "";
	if (!$user) {
		$words_read = $db->col(sprintf($word_qry, ''));
	}
	else {
		$words_read = $db->col(sprintf($word_qry, " AND rd.user_id = ".$user['id'].$schedule_where));
	}

	return $words_read;
}

function total_words_in_schedule($schedule_id) {
	$db = BibleReadingChallenge\Database::get_instance();
	return $db->col("
			SELECT SUM(word_count)
			FROM schedule_dates sd
			JOIN JSON_EACH(passage_chapter_ids)
			JOIN chapters c ON c.id = value
			WHERE sd.schedule_id = $schedule_id");
}

function hex_to_rgb($hex) {
	if (!preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
		return false;
	}
	// Remove any '#' characters
	$hex = str_replace('#', '', $hex);

	// Convert shorthand hex color (e.g., #abc) to full hex color (e.g., #aabbcc)
	if (strlen($hex) == 3) {
		$hex = str_split($hex);
		$hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
	}

	// Convert hex to RGB
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	// Return RGB values as an associative array
	return "rgb($r, $g, $b)";
}

function rgb_to_hex($rgb) {
	preg_match('/^rgb\((\d{1,3}), (\d{1,3}), (\d{1,3})\)$/', $rgb, $matches);
	if (!$matches) {
		return '';
	}
	$r = (int)$matches[1];
	$g = (int)$matches[2];
	$b = (int)$matches[3];
	// Ensure that RGB values are within valid range (0-255)
	$r = clamp($r, 0, 255);
	$g = clamp($g, 0, 255);
	$b = clamp($b, 0, 255);
	
	// Convert RGB to hex
	$hex = sprintf("#%02x%02x%02x", $r, $g, $b);
	
	return $hex;
}

function format_phone($phone) {
	return substr($phone, 0, 3).'-'.substr($phone, 3, 3).'-'.substr($phone, 6, 4);
}

function clamp($value, $min, $max) {
	return max($min, min($max, $value));
}

function chartjs_js() {
	global $add_to_foot;
	return "
	<script src='/js/lib/chart.min.js'></script>
  <script src='/js/lib/chartjs-adapter-date-fns.min.js'></script>
  <script src='/js/lib/chart.inc.js'></script>";

}