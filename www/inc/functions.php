<?php

require_once __DIR__ . "/../../vendor/autoload.php";

require_once __DIR__ . "/MailSender/MailSender.php";
require_once __DIR__ . "/MailSender/MailSenderMailgun.php";
require_once __DIR__ . "/MailSender/MailSenderPhpMailer.php";
require_once __DIR__ . "/BibleReadingChallenge/Site.php";
require_once __DIR__ . "/BibleReadingChallenge/Database.php";
require_once __DIR__ . "/BibleReadingChallenge/Redis.php";
require_once __DIR__ . "/BibleReadingChallenge/Schedule.php";
require_once __DIR__ . "/BibleReadingChallenge/PerfTimer.php";

function html($str, $lang_flag = ENT_HTML5)
{
	return htmlspecialchars($str, ENT_QUOTES | $lang_flag);
}

function debug()
{
	ob_start();
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

	$output = ob_get_clean();
	if (CLI) {
		$output = strip_tags($output);
	}

	echo $output;
	if ($die)
		die;
}

function redirect($url = '')
{
	header("Location: " . ($url ?: $_SERVER['REQUEST_URI']));
	die;
}

function perm_redirect($url)
{
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: $url");
	die;
}

function print_json($arr)
{
	header("Content-type: application/json");
	echo json_encode($arr, JSON_UNESCAPED_SLASHES);
	die;
}

function cors()
{
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

function curl_post_form($url, $headers, $arr, $json = false)
{
	$debug = false;
	// $debug = true;

	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $json ? json_encode($arr, JSON_UNESCAPED_SLASHES) : $arr);
	curl_setopt($curl, CURLOPT_HTTPHEADER, [
		'Content-type:  ' . ($json ? 'application/json' : 'multipart/form-data'),
		...$headers
	]);
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

	if (curl_errno($curl)) {
		$error = curl_error($curl);
		error_log($error);
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

function active_navigation_class($link)
{
	return strpos($_SERVER['REQUEST_URI'], $link) !== false
		? 'active-page' : '';
}

function do_nav($links, $buttons, $navigation_class)
{
	$nav = "
			<div class='$navigation_class'>";

	$button_class = $buttons ? 'nav-item' : '';

	foreach ($links as $entry) {
		list($link, $title) = $entry;
		$nav .= "<a class='$button_class " . active_navigation_class($link) . "' href='$link'>$title</a>";
	}
	return $nav . "</div>";
}

function admin_navigation()
{
	global $me;

	$arr = [
		['/admin/users', 'Users'],
		['/admin/progress', 'Progress'],
		['/admin/schedules', 'Schedules'],
		['/admin/customize', 'Customize']
	];
	if ($me['id'] == 1) {
		$arr[] = ['/admin/sites', 'Sites'];
	}

	return do_nav($arr, true, 'admin-navigation');
}

function navigation()
{
	global $staff;
	$site = BibleReadingChallenge\SiteRegistry::get_site();

	$nav_elements = [
		['/my-schedule', 'My schedule' . ($site->data('allow_personal_schedules') ? 's' : '')],
		['/today', 'Today'],
		['/profile', 'Profile']
	];
	if ($staff) {
		array_unshift($nav_elements, ['/admin', 'Admin']);
	}

	return do_nav($nav_elements, false, 'navigation');
}

function allowed_schedule_date(DateTime $date)
{
	global $staff, $site;
	return $staff || new DateTime("now", $site->TZ) > $date;
}

function generate_calendar($year, $month, $start_date, $end_date, $editable = false)
{
	global $site;
	$calendar = '
		<div class="table-scroll">
			<table>
				<thead>
					<tr>';
	foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday) {
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

	// # of days in the month
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
	$today = new DateTime("now", $site->TZ);
	for ($day = 1; $day <= $days_in_month; $day++) {
		// Start a new row if it's Sunday
		if ($current_day_of_week == 0) {
			$calendar .= '
					</tr>
					<tr>';
		}

		// each individual day on the calendar
		$class = '';
		$availble_day = true;
		if ($current_day < $start_date || $current_day > $end_date) {
			$class = "inactive";
			$availble_day = false;
		} else if ($current_day > $today)
			$class .= " future";
		else if ($current_day < $today)
			$class .= " past";
		else
			$class .= " today";
		if ($current_day == $start_date)
			$class .= " start";
		if ($current_day == $end_date)
			$class .= " end";
		$calendar .= "
				<td class='reading-day $class' data-date='" . $current_day->format('Y-m-d') . "'>";
		if ($editable)
			$calendar .= "<div class='date-container'>
						<small class='arrow-container'>
							<div>" . tip("Shift Left", "&larr;") . "</div>
							<div>" . tip("Merge left", "&#8606;") . "</div>
						</small>
						<span class='date'>$day</span>
						<small class='arrow-container'>
							<div>" . tip("Shift right", "&rarr;") . "</div>
							<div>" . tip("Merge right", "&#8608;") . "</div>
						</small>
					</div>";
		else
			$calendar .= "
					<div class='date-container'>
						<span class='date'>$day</span>
					</div>";
		if ($editable && $availble_day) {
			$calendar .= "
					<input type='hidden' data-passage name='days[" . $current_day->format('Y-m-d') . "][passage]' value=''>
					<input type='hidden' data-id name='days[" . $current_day->format('Y-m-d') . "][id]' value=''>";
			$calendar .= "
						<input type='text' class='label' size='1'" . (strpos($class, 'past') !== false ? 'disabled' : '') . ">";
		} else if ($availble_day) {
			$calendar .= "
					<small class='label'></small>";
		}
		$calendar .= "</td>";

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
		</table>
		</div>';

	return $calendar;
}

function preg_match_reference($reference)
{
	static $replacer_strings;
	static $replacement_strings;
	if (!$replacement_strings || !$replacer_strings) {
		$replacer_strings = array_map(fn($key) => '/\b' . preg_quote($key) . '\b/i', array_keys(BOOK_NAMES_AND_ABBREV));
		$replacement_strings = array_column(BOOK_NAMES_AND_ABBREV, 'name');
	}

	$reference = strtolower(str_replace('–', '-', trim($reference)));
	for ($i = 0; $i < count($replacement_strings); $i++) {
		$reference_formatted = preg_replace(
			$replacer_strings[$i],
			$replacement_strings[$i],
			$reference,
			1,
			$count
		);
		if ($count) {
			break;
		}
	}

	return preg_match(BOOKS_RE, $reference_formatted, $matches)
		? $matches
		: false;
}

/**
 * This parses a passage using a simplified syntax that appears in the schedule calendar
 * Verses must either be ommitted (entire chapter) or include ranges (same start and end verse is ok)
 * $passage string e.g., 'Gen 1:3-4; Song of Songs 1:5-8; Leviticus 3; Jude 1:8-12; Genesis 3-4'
 * 								THIS DOES NOT SUPPORT full verse syntax: Jude 1-4; 2 Cor 1:4, 5-6; 4:8
 * @return [
 * 	[
 *   		'book' => (book info)
 *   		'chapter' => (chapter info)
 * 		 	'verse_numbers' => (the verse numbers that are parsed, in order)
 * 	]...
 * ]
 */
function parse_passages($passages_reference)
{
	$db = BibleReadingChallenge\Database::get_instance();
	$parts = explode(";", $passages_reference);
	$passages = [];
	foreach ($parts as $reference) {
		$matches = preg_match_reference($reference);
		if (!$matches) {
			continue;
		}
		$book = $matches[1];
		$chapter = (int)$matches[2];
		$book_row = $db->row("SELECT * FROM books WHERE name = '" . $db->esc($book) . "'");
		$chp_row = $db->row("SELECT * FROM chapters WHERE book_id = " . intval($book_row['id']) . " AND number = $chapter");
		if (!$chp_row && in_array($book_row['name'], SINGLE_BOOKS)) { // csv exports for these books from https://biblereadingplangenerator.com don't have chapter numbers
			$chp_row = $db->row("SELECT * FROM chapters WHERE book_id = " . intval($book_row['id']) . " AND number = 1");
		}
		if ($book_row && $chp_row) {
			if ($matches[4] && $matches[5] && $matches[6]) {
				// Format: Genesis 12:3-15:10
				$v_start = (int)$matches[4];
				$chp_end = (int)$matches[5];
				$v_end = (int)$matches[6];
				$chp_end_row = $db->row("SELECT * FROM chapters WHERE book_id = $book_row[id] AND number = $chp_end");
				if (
					$chp_end_row && // ending chapter exists (valid within book)
					$chapter < $chp_end && // first chapter is less than last chapter
					$v_start <= $chp_row['verses'] && // starting verse is within starting chapter
					$v_end <= $chp_end_row['verses'] && // ending verse is within ending chapter
					$chp_end - $chapter < 10 // no more than 10 chapters requested
				) {
					for ($i = $chapter; $i <= $chp_end; $i++) {
						$curr_chp_row = null;
						$verse_numbers = null;
						if ($i === $chapter) {
							// first chapter in reference (chp 12 in Gen 12:3)
							$curr_chp_row = $chp_row;
							$verse_numbers = range($v_start, $chp_row['verses']);
						} else if ($i === $chp_end) {
							// last chapter in reference (chp 15 in Gen 15:10)
							$curr_chp_row = $chp_end_row;
							$verse_numbers = range(1, $v_end);
						} else {
							// every chapter in between that range
							$curr_chp_row = $db->row("SELECT * FROM chapters WHERE book_id = $book_row[id] AND number = $i");
							$verse_numbers = range(1, $curr_chp_row['verses']);
						}
						$passages[] = [
							'book' => $book_row,
							'chapter' => $curr_chp_row,
							'verse_numbers' => $verse_numbers
						];
					}
				}
			} else if ($matches[7] && $matches[8]) {
				// Format: Genesis 12:3-20
				$v_start = (int)$matches[7];
				$v_end = (int)$matches[8];
				if (1 <= $v_start && $v_start < $v_end && $v_end <= (int)$chp_row['verses']) {
					// verse ranges check out, we're good
					$verse_numbers = $db->cols("SELECT number FROM verses WHERE chapter_id = $chp_row[id] AND number BETWEEN $v_start AND $v_end ORDER BY number");
					$passages[] = [
						'book' => $book_row,
						'chapter' => $chp_row,
						'verse_numbers' => $verse_numbers
					];
				}
			} else if ($matches[3]) {
				// Format: Genesis 3-4 (multiple chapters)
				if (
					$chp_row['number'] < $matches[3] &&		// in order
					$matches[3] <= $book_row['chapters']	// valid chapter end
				) {
					foreach (
						$db->select(
							"
							SELECT *
							FROM chapters
							WHERE book_id = $book_row[id]
								AND number IN(" . implode(',', range($chp_row['number'], $matches[3])) . ")
							ORDER BY number"
						) as $range_chp_row
					) {
						$passages[] = [
							'book' => $book_row,
							'chapter' => $range_chp_row,
							'verse_numbers' => $db->cols("
										SELECT v.number
										FROM verses v
										JOIN chapters c ON c.id = v.chapter_id
										WHERE c.id = $range_chp_row[id]
										ORDER BY v.number")
						];
					}
				}
			} else if ($matches[9]) {
				// Format: Genesis 12:3 (single verse in a chapter)
				if (
					1 <= $matches[9] && // valid verse start
					$matches[9] <= $chp_row['verses']
				) {
					$passages[] = [
						'book' => $book_row,
						'chapter' => $chp_row,
						'verse_numbers' => $db->cols("SELECT number FROM verses WHERE chapter_id = $chp_row[id] AND number = " . (int)$matches[9])
					];
				}
			} else {
				// Format: Genesis 2 (single entire chapter)
				$passages[] = [
					'book' => $book_row,
					'chapter' => $chp_row,
					'verse_numbers' => $db->cols("SELECT number FROM verses WHERE chapter_id = $chp_row[id] ORDER BY number")
				];
			}
		}
	}
	return $passages;
}

/**
 * calculate the number of words in a passage reading based on the chapter id, start verse, and end verse
 */
function passage_readings_word_count($passage_readings)
{
	$word_count = 0;
	$db = \BibleReadingChallenge\Database::get_instance();
	foreach ($passage_readings as $pr) {
		$word_count += (int)$db->col("
				SELECT SUM(v.word_count)
					FROM verses v
					JOIN chapters c ON c.id = v.chapter_id
					WHERE c.id = $pr[id] AND v.number BETWEEN $pr[s] AND $pr[e]");
	}
	return $word_count;
}

/**
 * @param $parsed_passages	array		the return value of parse_passages()
 * @return array expected value to go into the db schedule_dates.passage_chapter_readings
 */
function parsed_passages_to_passage_readings($parsed_passages)
{
	return array_map(fn($pas) => [
		'id' => (int)$pas['chapter']['id'],
		's' => (int)$pas['verse_numbers'][0],
		'e' => (int)$pas['verse_numbers'][count($pas['verse_numbers']) - 1]
	], $parsed_passages);
}

function log_user_in($id)
{
	$_SESSION['my_id'] = $id;
	if ($_SESSION['login_redirect']) {
		$redir = $_SESSION['login_redirect'];
		$_SESSION['login_redirect'] = null;
		redirect($redir);
	} else {
		redirect("/");
	}
}

function xs($num)
{
	if ($num == 1) return '';
	else return 's';
}

function tip($help_text, $plain_text, $direction = 'bottom')
{
	return "<span class='cursor hint--large hint--" . $direction . "' aria-label='" . html($help_text) . "'>" . $plain_text . "</span>";
}

function help($tip, $direction = 'bottom')
{
	return tip($tip, "?&#x20DD;", $direction);
}

function toggle_all_users($initial_count)
{
	global $add_to_foot;

	echo "<div id='toggle-all-wrap'><div><b id='all-count'>$initial_count</b> reader" . xs($initial_count) . "</div>
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
			flex-flow: row wrap;
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

function badges_for_user($user_id)
{
	$db = BibleReadingChallenge\Database::get_instance();
	return $db->cols("
		SELECT b.name
		FROM books b
		JOIN (
			-- total words in distinct verses read
			SELECT book_id, SUM(word_count) book_sum
			FROM (
				-- distinct verses read
				SELECT DISTINCT sdv.book_id, sdv.book_name, sdv.chapter_number, sdv.verse_number, sdv.word_count
				FROM read_dates rd
				JOIN schedule_date_verses sdv ON sdv.schedule_date_id = rd.schedule_date_id
				WHERE rd.user_id = $user_id
      )
			GROUP BY book_id
		) words_read_in_distinct_verse_in_book ON b.id = words_read_in_distinct_verse_in_book.book_id
		-- book is complete if total words read is the same as the words in the book
		WHERE book_sum = b.word_count;");
}

function badges_html($badges)
{
	$db = BibleReadingChallenge\Database::get_instance();
	$books = $db->select("SELECT id, name FROM books ORDER BY id");
	ob_start();
	foreach (
		[
			// [0, 10],
			// [17, 5],
			// [22, 17],
			[39, 6],
			[45, 12],
			[57, 9]
		] as $section
	) {
		echo "<div class='badges'>";
		foreach (array_slice($books, $section[0], $section[1]) as $book) {
			if (in_array($book['name'], $badges)) {
				echo "<img alt='$book[name] badge' class='badge' src='/img/badge/nt/rgb-small/" . ($book['id'] - 39) . ".png'>";
			}
		}
		echo "</div>";
	}
	return ob_get_clean();
}

function last_read_attr($last_read)
{
	$date = $last_read ? date('Y-m-d', $last_read) : "2200-01-01";
	return "data-last-read='$date' title='$date'";
}

function hex_to_rgb($hex)
{
	if (!preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
		return false;
	}
	// Remove any '#' characters
	$hex = str_replace('#', '', $hex);

	// Convert shorthand hex color (e.g., #abc) to full hex color (e.g., #aabbcc)
	if (strlen($hex) == 3) {
		$hex = str_split($hex);
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	// Convert hex to RGB
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	// Return RGB values as an associative array
	return "rgb($r, $g, $b)";
}

function rgb_to_hex($rgb)
{
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

function format_phone($phone)
{
	return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
}

function clamp($value, $min, $max)
{
	return max($min, min($max, $value));
}

function chartjs_js()
{
	return
		cached_file('js', '/js/lib/chart.min.js') .
		cached_file('js', '/js/lib/chartjs-adapter-date-fns.min.js') .
		cached_file('js', '/js/lib/chart.inc.js');
}

function cached_file($type, $path, $attrs = '')
{
	if ($type == 'css') {
		return "\n<link rel='stylesheet' href='$path?v=" . VERSION . "' $attrs>";
	} else if ($type == 'js') {
		return "\n<script src='$path?v=" . VERSION . "' $attrs></script>";
	}
	return '';
}

function back_button($text)
{
	return "<a href='' onclick='history.back(); return false;'>&lt;&lt; $text</a>";
}

function site_logo()
{
	global $site;
	return "<img alt='logo' class='logo' src='" . $site->resolve_img_src('logo') . "' onclick='window.location = `/`'>";
}

function down_for_maintenance($msg_html = "")
{
?>
	<!doctype html>
	<html lang="en-US">

	<head>
		<meta content="width=device-width, initial-scale=1" name="viewport">
		<meta charset="utf-8">
		<title>Site Busy</title>
		<style>
			body {
				max-width: 48em;
				margin: auto;
				padding: 15px 25px;
			}
		</style>
	</head>

	<body>
		<h3>Down for Maintenance!</h3>
		<p>
			Sorry, but we're working on some technical stuff right now. We should be back up shortly.
		</p>
		<?= $msg_html ?>
		<details>
			<summary>Still not working?</summary>
			<p>
				If this has been here a while, please <a href='mailto:brc@ramseyer.dev?subject=<?= rawurlencode("Site is down") . '&body=' . rawurlencode("The BRC site is broken: \n" . var_export($_SERVER, true)) ?>'>let me know</a>.
			</p>
		</details>
	</body>

	</html>
<?php
}

// e.g., https://text.recoveryversion.bible/58_Hebrews_4.htm#Heb4-3
function recoveryversion_url($passage)
{
	$no_spaces = str_replace(' ', '', $passage['book']['name']);
	$abbreviated = [
		"Jude" => "Jde",		// collides with "Judges"
		"Philemon" => "Phm"	// collides with "Philippians"
	][$no_spaces] ?: $no_spaces;

	return 'https://text.recoveryversion.bible/' .
		str_pad($passage['book']['id'], 2, "0", STR_PAD_LEFT) .
		"_" . $no_spaces . "_" .
		$passage['chapter']['number'] .
		".htm#" .
		substr(
			str_replace(
				'1',
				'F',
				str_replace(
					'2',
					'S',
					str_replace('3', 'T', $abbreviated)
				)
			),
			0,
			3
		) .
		$passage['chapter']['number'] .
		'-' . $passage['range'][0];
}

function recoveryversion_link($passage, $link_text)
{
	return "<a target='_blank' rel='noopener noreferrer' href='" .
		recoveryversion_url($passage)
		. "'>" . $link_text . "</a>";
}

// https://github.com/PHPMailer/PHPMailer/blob/v6.9.3/examples/sendmail.phps
function send_system_email($subject, $text)
{
	$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
	$mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
	//Set PHPMailer to use the sendmail transport
	$mail->isSendmail();

	$mail->setFrom(getenv("DEPLOYMENT_EMAIL_FROM_ADDRESS"));
	$mail->addAddress(getenv("DEPLOYMENT_EMAIL_TO_ADDRESS"));

	$mail->Subject = $subject;

	$mail->msgHTML("<p>" . $text . "</p>");
	$mail->AltBody = $text;

	return $mail->send();
}

/**
 * Send a rate-limited notification
 *
 * @param string $notificationType Identifier for the notification type
 * @param string $message The message to send
 * @param int $lockExpiry Time in seconds before allowing another notification (default: 1 hour)
 * @return bool Whether the notification was sent
 */
function send_rate_limited_notification($notificationType, $message, $lockExpiry = 3600)
{
	$lockFile = "/tmp/bible-reading-challenge_notification_" . $notificationType . ".lock";

	// Check if the lock file exists and is not expired
	if (file_exists($lockFile)) {
		$lastNotificationTime = file_get_contents($lockFile);
		if (time() - $lastNotificationTime < $lockExpiry) {
			// Lock is still valid, don't send notification
			error_log("Notification '{$notificationType}' suppressed (rate limited): " . $message);
			return false;
		}
	}

	// Send the notification
	try {
		$result = send_system_email("Bible Reading Challenge Notification: " . $notificationType, $message);
	} catch (Exception $e) {
		$result = false;
		error_log("Exception: " . $e);
	}

	// Update the lock file with current timestamp
	file_put_contents($lockFile, time());

	return $result;
}

/**
 * Send error notification (with 1 hour rate limiting)
 */
function send_error_notification($msg)
{
	return send_rate_limited_notification('php-error', $msg, 3600);
}

/**
 * Send git hash error notification (with 1 hour rate limiting)
 */
function missing_git_hash_notification($msg)
{
	return send_rate_limited_notification('git-hash', $msg, 3600);
}

function health_checks()
{
	global $site, $redis, $db;

	$img_dir_not_writable = false;
	foreach ([UPLOAD_DIR, IMG_DIR] as $dir) {
    if (!is_writable($dir)) {
      $img_dir_not_writable = $dir;
    }
  }

	if (
		!$site || !$db || !$redis || !$site->ID ||
		($db->get_db()->lastErrorCode() !== 0) ||
		$redis->is_offline() ||
		$img_dir_not_writable
	) {
		$err_msg = "Site: " . print_r($site, true) . PHP_EOL .
			"DB: " . print_r($db, true) . PHP_EOL .
			"Redis: " . print_r($redis, true) . PHP_EOL
			"Img dir permissions failed?: " . $img_dir_not_writable . PHP_EOL;
		error_log($err_msg);
		send_error_notification("Something is wrong: \n" . $err_msg);

		down_for_maintenance();
		die;
	}

	// wait until we know redis is good to go to use it to check the version
	define('VERSION', $redis->get_site_version());

	if (VERSION == '') {
		missing_git_hash_notification("Git hash failed");
	}
}


function update_email_stats($email_id, $field) {
  global $db;
	if (in_array($field, ['clicked_done_timestamp', 'opened_timestamp'], true)) {
	  $db->update('verse_email_stats', [
		  $field => date('Y-m-d H:i:s')
		], "email_id = '".$db->esc($email_id)."'");
	}
}

function insert_email_stats($email_id, $user_id, $schedule_date_id) {
  global $db;
  return $db->insert('verse_email_stats', [
	  'email_id' => $db->esc($email_id),
		'user_id' => (int)$user_id,
		'schedule_date_id' => (int)$schedule_date_id,
		'sent_timestamp' => date('Y-m-d H:i:s')
	]);
}
