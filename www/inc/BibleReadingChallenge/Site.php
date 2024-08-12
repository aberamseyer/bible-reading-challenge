<?php

namespace BibleReadingChallenge;

class SiteRegistry
{
  private static array $sites = [];

  private function __construct()
  {
    // Prevent direct instantiation
  }
  
  private function __clone()
  {
    // Prevent cloning
  }
  
  public function __wakeup()
  {
    // Prevent unserialization
    throw new \Exception("Cannot unserialize a singleton.");
  }

  public static function get_site($id = 0, $soft=false): Site
  {
    if (!isset(self::$sites[ $id ])) {
      $inst = new Site(new self(), $id, $soft);
      if ($id === 0) {
        self::$sites[ 0 ] = $inst; // if no $id is passed, we need the to put our site in the 'default' slot of 0
      }
      self::$sites[ $inst->ID ] = $inst;
    }
    return self::$sites[ $id ];
  }
}



class Site {
  public readonly string $DOMAIN;
  public readonly string $SOCKET_DOMAIN;
  public readonly \DateTimeZone $TZ;
  public readonly string $TZ_OFFSET;
  public readonly string $ID;

  private array $data;
  private Database $db;
  private array $env;

  private \Email\MailSender $ms;

  private Schedule|null $active_schedule;

  /**
   * @param $id a specific site to get
   * @param $soft 'soft'-retrieves a site without initializing all of its objects (useful when setting up a site)
   */
  public function __construct(object $token, $id, $soft=false)
  {
    if (!$token instanceof \BibleReadingChallenge\SiteRegistry) {
      // only allow SiteRegistry to instantiate us
      throw new \InvalidArgumentException('Invalid token');
    }

    $this->active_schedule = null;
    $this->db = Database::get_instance();
    // either a passed $id gets a specific site, or we default to the site that matches the server HOST value
    if ($id) {
      $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE enabled = 1 AND id =:id LIMIT 1');
      $stmt->bindValue(':id', $id);
    }
    else {
      if ($soft) {
        $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE domain_www = :domain OR domain_www_test = :domain LIMIT 1');
      }
      else {
        $stmt = $this->db->get_db()->prepare('SELECT * FROM sites WHERE enabled = 1 AND (domain_www = :domain OR domain_www_test = :domain) LIMIT 1');
      }
      $stmt->bindValue(':domain', $_SERVER['HTTP_HOST']);
    }
    $this->data = $this->db->get_db()->querySingle($stmt->getSQL(true), true);

    if (!$this->data) {
      die("Nothing to see here: ".$id);
    }
    else {
      $this->ID = $this->data('id');
      
      if (!$soft) {
        $this->DOMAIN = PROD ? $this->data('domain_www') : $this->data('domain_www_test');
        $this->SOCKET_DOMAIN = PROD ? $this->data('domain_socket') : $this->data('domain_socket_test');
        $this->TZ = new \DateTimeZone($this->data('time_zone_id') ?: 'UTC');
        $this->TZ_OFFSET = ''.intval($this->TZ->getOffset(new \DateTime('UTC')) / 3600);
      }
    }

    if (!$soft) {
      foreach (explode("\n", $this->data('env')) as $line) {
        $line = trim($line);
        if ($line && !preg_match("/^\/\/.*$/", $line)) { // line doesn't begin with a comment
          list($key, $val) = explode("=", $line);
          $this->env[ $key ] = $val;
        }
      }
  
      $this->ms = new \Email\MailSenderSendgrid(
        $this->env('SENDGRID_API_KEY'), 
        $this->env('SENDGRID_DAILY_EMAIL_TEMPLATE'), 
        $this->env('SENDGRID_REGISTER_EMAIL_TEMPLATE'), 
        $this->env('SENDGRID_FORGOT_PASSWORD_TEMPLATE'),
        $this->data('email_from_address'),
        $this->data('email_from_name')
      );
    }
  }

  public function data(string $key)
  {
    return $this->data[ $key ];
  }

  public function env(string $key)
  {
    return $this->env[ $key ];
  }

  public function send_register_email($to, $link)
  {
    $this->ms->send_dynamic_email($to, $this->ms->register_email_template(), [ 'confirm_link' => $link ]);
  }

  public function send_forgot_password_email($to, $link)
  {
    $this->ms->send_dynamic_email($to, $this->ms->forgot_password_template(), [ "reset_link" => $link ]);
  }

  public function send_daily_verse_email($to, $name, $subject, $content, $streak)
  {
    $this->ms->send_dynamic_email($to, $this->ms->forgot_password_template(), [
      "subject" => $subject,
      "name" => $name,
      "html" => $content,
      "streak" => $streak
    ]);
  }

	public function get_active_schedule($refresh=false)
  {
    if (!$this->active_schedule || $refresh) {
      $id = (int)$this->db->col("SELECT id FROM schedules WHERE active = 1 AND site_id = ".$this->ID);
      $this->active_schedule = new Schedule($this->ID, $id);
    }
    return $this->active_schedule;
	}

  public function all_users($stale = false) {
    $nine_mo = strtotime('-9 months');
    if ($stale) {
      $where = "last_seen < '$nine_mo' OR (last_seen IS NULL AND date_created < '$nine_mo')";
    }
    else {
      // all users
      $where = "last_seen >= '$nine_mo' OR (last_seen IS NULL AND date_created >= '$nine_mo')";
    }
    return $this->db->select("
      SELECT u.id, u.name, u.emoji, u.email, u.staff, u.date_created, u.last_seen, u.email_verses, streak, max_streak, u.trans_pref
      FROM users u
      WHERE site_id = ".$this->ID." AND ($where)
      ORDER BY LOWER(name) ASC");
  }

  public function mountain_for_emojis($emojis, $my_id = 0, $hidden = false)
  {  
    ob_start();
    echo "<div class='mountain-wrap ".($hidden ? 'hidden' : '')."'>";
  
    foreach($emojis as $i => $datum) {
      $style = '';
      if ($datum['id'] == $my_id) {
        $style = "style='z-index: 10'";
      }
      echo "
      <span class='emoji' data-percent='$datum[percent_complete]' data-id='$datum[id]' $style>
        <span class='inner'>$datum[emoji]</span>
      </span>";
    }
    
    echo "<img alt='progress' src='".$this->resolve_img_src('progress')."' class='mountain'>";
    echo "</div>";
    return ob_get_clean();
  }

  public function resolve_img_src($type)
  {
    static $pictures;
    if ($pictures[$type]) {
      // return cached value
      return '/img/'.$pictures[$type];
    }
    else {
      // find the active image
      if ($this->data($type.'_image_id')) {
        $img_filename = $this->db->col("SELECT uploads_dir_filename FROM images WHERE id = ".$this->data($type.'_image_id'));
      }
      // if the active image doesn't exist e.g., in development environment
      if (!$img_filename || !file_exists(IMG_DIR.$img_filename)) {
        $img_filename = "static/".$type."-placeholder.svg";
      }
      $pictures[$type] = $img_filename;
    }
    return '/img/'.$pictures[$type];
  }


	/**
	 * returns a structured array from the details within the passage_chapter_readings column of the schedule_dates table
	 * @param $json 	string|array	valid JSON, directly from the database, or a decoded json array
	 */
	function parse_passage_from_json(string|array $json_or_arr)
  {
		$arr = is_array($json_or_arr)
			? $json_or_arr
			: json_decode($json_or_arr, true);
		
		$passages = [];
		foreach($arr as $chp_reading) {
			$chapter = $this->db->row("SELECT * FROM chapters WHERE id = $chp_reading[id]");
			$book = $this->db->row("SELECT * FROM books WHERE id = $chapter[book_id]");
			$verses = $this->db->select("
				SELECT id, number, word_count, ".implode(',', $this->get_translations_for_site())."
				FROM verses 
				WHERE chapter_id = $chapter[id] 
					AND number IN(".implode(',', range($chp_reading['s'], $chp_reading['e'])).")
				ORDER BY number");
			$passages[] = [
				'book' => $book,
				'chapter' => $chapter,
				'word_count' => array_sum(array_column($verses, 'word_count')),
				'range' => [ $verses[0]['number'], $verses[ count($verses)-1 ]['number'] ],
				'verses' => $verses
			];
		}
		return $passages;
	}

  public function notification_info($name, $scheduled_reading)
  {
    // format the user's name by using everything but the last name
    $name_arr = explode(' ', $name);
    $name = array_pop($name_arr);
    if ($name_arr) {
      $name = implode(' ', $name_arr);
    }

    // total up the words in this day's reading
    $total_word_count = array_reduce(
      $scheduled_reading['passages'], 
      fn($acc, $cur) => $acc + $cur['word_count']);
    $minutes_to_read = ceil($total_word_count / ($this->data('reading_rate_wpm') ?: 240)); // words per minute, default to 240
    
    
    return [
      'name' => $name,
      'minutes' => $minutes_to_read
    ];
  }

	/*
	 * @param scheduled_reading array the return value of get_schedule_date()
	 * @param trans string one of the translations
	 * @param complete_key string the key to complete the reading from a row in the schedule_dates's table
	 * @param schedule the schedule from which we are generating a reading
	 * @param email bool whether this is going in an email or not
	 * @return the html of all the verses we are reading
	 */ 
	public function html_for_scheduled_reading($scheduled_reading, $trans, $complete_key, $schedule, $today, $email=false)
  {
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
        $verse_range = ":".$passage['range'][0]."-".$passage['range'][1];
        if ($passage['chapter']['verses'] == $passage['range'][1]-$passage['range'][0]+1) {
          // if the range is the whole chapter, hide the range
          $verse_range = "";
        }
				echo "<h4 class='text-center' $style>".$passage['book']['name']." ".$passage['chapter']['number'].$verse_range."</h4>";

				$ref_style = "class='ref'";
				$verse_style = "class='verse-text'";
				if ($email) {
					$ref_style = "style='font-weight: bold; user-select: none;'";
					$verse_style = "style='margin-left: 1rem;'";
				}
				foreach($passage['verses'] as $verse_row) {
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
			$copyright_text = json_decode(file_get_contents(DOCUMENT_ROOT."../extras/copyright.json"), true);
			$copyright_style = "";
			if ($email) {
				$copyright_style = "font-size: 75%;";
			}
			echo "
			<div style='text-align: center; $copyright_style'><small><i>".$copyright_text[$trans]."</i></small></div>
			<form action='".SCHEME."://".$this->DOMAIN."/today' method='get' $form_style>
        <input type='hidden' name='schedule_id' value='".$schedule->ID."'>
				<input type='hidden' name='complete_key' value='$complete_key".($email ? '-e' : '' /* bypass wpm check from an email */)."'>
				<input type='hidden' name='today' value='$scheduled_reading[date]'>
				<button type='submit' name='done' value='1' $btn_style>Done!</button>
			</form>";
		}
		else {
			echo "<p>Nothing to read today!</p>";
	
			// look for the next time to read in the schedule.
			$days = $schedule->get_next_reading($today);
			foreach($days as $day) {
				$dt = new \Datetime($day['date']);
				if ($today < $dt) {
					echo "<p>The next reading will be on <b>".$dt->format('F j')."</b>.</p>";
					break;
				}
			}
		}
		echo "</article>";
		return ob_get_clean();
	}

  public function on_target_days_for_user(int $user_id)
  {
    return $this->db->col("
      SELECT 
        COUNT(*)
      FROM read_dates rd
      JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      WHERE rd.user_id = $user_id AND sd.schedule_id = ".$this->get_active_schedule()->ID."
        AND sd.date = DATE(rd.timestamp, 'unixepoch', '".$this->TZ_OFFSET." hours')");
  }

  public function on_target_percent_for_user($user_id)
  {
    $schedule_days_until_now = $this->db->col("
      SELECT COUNT(*)
      FROM schedule_dates sd
      WHERE sd.schedule_id = ".$this->get_active_schedule()->ID." AND sd.date <= DATE('now', '".$this->TZ_OFFSET." hours')");
    return $schedule_days_until_now
      // this can be 0 if a schedule was created with no reading dates attached to it
      ? $this->on_target_days_for_user($user_id) / $schedule_days_until_now * 100
      : 0;
  }

  public function deviation_for_user($user_id)
  {
    $weekly_counts = $this->weekly_counts($user_id)['counts'];
    // standard deviation
    $n = count($weekly_counts);
    if ($n === 0) {
      return null;
    }
    $mean = array_sum($weekly_counts) / $n;
    $variance = 0.0;
    foreach ($weekly_counts as $val) {
        $variance += pow($val - $mean, 2);
    }
    $variance /= $n;

    return round(sqrt($variance), 3);
  }

  public function weekly_progress_canvas($user_id, $size=400)
  {
    $counts = $this->weekly_counts($user_id);
    $data = json_encode(array_combine($counts['day_start'], $counts['counts']));
    return "<canvas data-graph='$data' class='weekly-counts-canvas' width='$size'></canvas>";
  }

  /**
   * the number of days read each week, across personal and corporate schedules,
   * from days_in_schedule in the past up to today
   * @return  array  
   * [
   *    week => array of week numbers (within the year),
   *    counts => array of corresponding number of days read in the week,
   *    start_of_week => array of the corresponding starting dates of each of the weeks
   *  ]
   */
  public function weekly_counts($user_id)
  {
    $start = new \DateTime($this->get_active_schedule()->data('start_date'));
    $end = new \DateTime($this->get_active_schedule()->data('end_date'));
  
    $interval = $start->diff($end);
    $days_between = abs(intval($interval->format('%a')));
    $WEEKS = ceil($days_between / 7);
    
    $counts = $this->weekly_reading_data($user_id, $WEEKS);
    return [
      'week' => array_column($counts, 'week'),
      'counts' => array_column($counts, 'count'),
      'day_start' => array_column($counts, 'day_start')
    ];
  }

  /**
   * how many days we've read in the last four weeks
   */
  function four_week_trend_canvas($user_id) {
    $counts = $this->weekly_reading_data($user_id, 4);
    $data = json_encode(array_column($counts, 'count', 'day_start'));
    return "<canvas data-graph='$data' width='200' style='margin: auto;'></canvas>";
  }

  function weekly_reading_data($user_id, $WEEKS)
  {
    return $this->db->select("
      SELECT sd.day_start, COALESCE(count, 0) count, sd.week
      FROM (
        -- generates last $WEEKS weeks to join what we read to
        WITH RECURSIVE week_sequence AS (
          SELECT 
            DATE('now', '".$this->TZ_OFFSET." hours', '-7 days', 'weekday 0') AS cdate
          UNION ALL
          SELECT DATE(cdate, '-7 days') 
          FROM week_sequence
          LIMIT $WEEKS
        )
        SELECT STRFTIME('%Y-%W', cdate) AS week,
              STRFTIME('%Y-%m-%d', cdate, 'weekday 0') AS day_start
        FROM week_sequence
      ) sd
      LEFT JOIN (
        -- gives the number of days we have read each week
        SELECT STRFTIME('%Y-%W', DATE(sd.date, '".$this->TZ_OFFSET." hours', '-7 days', 'weekday 0')) AS week,
              COUNT(rd.user_id) count
        FROM read_dates rd
        JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
        WHERE rd.user_id = $user_id
        GROUP BY week
      ) rd ON rd.week = sd.week
      WHERE sd.week >= STRFTIME('%Y-%W', DATE('now', '".$this->TZ_OFFSET." hours', 'weekday 0', '-".(7*$WEEKS)." days')) 
      ORDER BY sd.week ASC
      LIMIT $WEEKS");
  }

  /**
   * a graph that shows the percentage of the challenge complete by the day
   * pass the value of progress($user_id) as the first parameter
   */
  public function progress_canvas($progress, $size=400)
  {
    return "<canvas data-graph='".json_encode($progress)."' class='progress-canvas' width='$size'></canvas>";
  }

  /**
   * @return  array indexed 1-100 (not 0) with the dates on which each threshhold was passed for the active schedule
   */
  public function progress($user_id)
  {
    $total_words_in_schedule = $this->get_active_schedule()->total_words_in_schedule();

    $threshholds = [];
    for($i = 1; $i <= 100; $i++) {
      $threshholds[ $i ] = floor($total_words_in_schedule / 100 * $i);
    }

    $progress = [];
    $read_dates = $this->db->select("
      SELECT DATE(rd.timestamp, 'unixepoch', '".$this->TZ_OFFSET." hours') date, SUM(sdv.word_count) word_count
      FROM read_dates rd
      JOIN schedule_date_verses sdv ON sdv.schedule_date_id = rd.schedule_date_id
      WHERE sdv.schedule_id = ".$this->get_active_schedule()->ID." AND rd.user_id = $user_id
      GROUP BY sdv.chapter_id
      ORDER BY timestamp");
    
    $accum_words_read = 0;
    foreach($read_dates as $rd) {
      $accum_words_read += (int)$rd['word_count'];
      foreach ($threshholds as $thresh => $words) {
        if ($accum_words_read >= (int)$words && !$progress[ $thresh ]) {
          $progress [ $thresh ] = date('Y-m-d', strtotime($rd['date']));
        }
      }
    }
    return $progress;
  }

  public function create_user($email, $name, $password=false, $emoji=false, $verified=false) {
    $uuid = uniqid();
    $hash = password_hash($password ?: bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $verify_token = uniqid("", true).uniqid("", true);
    $user_id = $this->db->insert("users", [ 
      'site_id' => $this->ID,
      'uuid' => $uuid,
      'name' => $name,
      'email' => $email,
      'password' => $hash,
      'trans_pref' => 'rcv',
      'date_created' => time(),
      '__email_verified' => $verified,
      'emoji' => $emoji ?: $this->data('default_emoji')
    ]);
    $this->db->insert('schedules', [
      'site_id' => $this->ID,
      'user_id' => $user_id,
      'name' => "$name's Default Schedule",
      'start_date' => date('Y-m-d', strtotime('January 1')),
      'end_date' => date('Y-m-d', strtotime('December 1')),
      'active' => 1
    ]);
    return [
      'insert_id' => $user_id,
      'verify_token' => $verify_token,
      'uuid' => $uuid
    ];
  }

  /**
   * @return  array   the allowed translations fora  site
   */
  public function get_translations_for_site()
  {
    static $translations;
    if (!$translations) {
      $translations = json_decode($this->data('translations'), true);
    }
    return $translations;
  }

  /**
   * @return  bool  validates a translation is within those allowed for the site
   */
  public function check_translation($trans)
  {
    return in_array($trans, $this->get_translations_for_site(), true);
  }

  /**
   * the number of words read for either a specific user across all schedules, within a specific schedule OR the entire club (Site)
   */
  public function words_read($user = 0, $schedule_id = 0) {
    $word_qry = "
        SELECT SUM(word_count)
        FROM schedule_dates sd
        JOIN read_dates rd ON sd.id = rd.schedule_date_id
        JOIN schedules s ON s.id = sd.schedule_id
        WHERE s.site_id = ".$this->ID." AND %s
    ";
    if (!$user) {
      $words_read = $this->db->col(sprintf($word_qry, '1'));
    }
    else {
      $schedule_where = $schedule_id
        ? " AND sd.schedule_id = ".$schedule_id
        : "";
      $words_read = $this->db->col(sprintf($word_qry, " rd.user_id = ".$user['id'].$schedule_where));
    }

    return (int)$words_read;
  }

  /**
   * returns an array filled with different statistics for the user, cached
   */
  public function user_stats($user_id, $refresh=false)
  {
    $redis = Redis::get_instance();
    $stats = $redis->get_user_stats($user_id);
    if ($refresh) {
      $stats = false;
    }

    if (!$stats) {
      $timer = new PerfTimer();
      $active_schedule = $this->get_active_schedule($refresh);
      
      $user = $this->db->row("SELECT * FROM users WHERE id = $user_id");
      $timer->mark('user');
      $badges = badges_for_user($user_id);
      $timer->mark('badges');
      $last_seen = (int) $redis->get_last_seen($user_id) ?: $user['last_seen'];
      $timer->mark('last_seen');
      $last_read_ts = (int) $this->db->col("SELECT MAX(timestamp) FROM read_dates WHERE user_id = $user[id]");
      $timer->mark('last_read_ts');
      $chapters_ive_read = (int) $this->db->col(
        sprintf($chp_qry =
          "SELECT SUM(chapters_read)
          FROM (
            SELECT sdv.chapter_id, COUNT(*) / c.verses AS chapters_read
            FROM read_dates rd
            JOIN schedule_date_verses sdv ON rd.schedule_date_id = sdv.schedule_date_id
            JOIN chapters c ON c.id = sdv.chapter_id
            JOIN users u ON u.id = rd.user_id
            WHERE u.site_id = ".$this->ID." AND %s
            GROUP BY chapter_id
            HAVING COUNT(*) >= c.verses
        )", "rd.user_id = $user_id"));
      $timer->mark('chapters_ive_read');
      $words_ive_read = (int) $this->words_read($user);
      $timer->mark('words_ive_read');
      $deviation = (float) $this->deviation_for_user($user_id);
      $timer->mark('deviation');
      $on_target = (float) $this->on_target_percent_for_user($user_id);
      $timer->mark('on_target');
      $all_club_chapters_read = (int) $this->db->col(sprintf($chp_qry, "1"));
      $timer->mark('all_club_chapters_read');
      $all_club_words_read = (int) $this->words_read();
      $timer->mark('words_read');
      $progress_graph_data = $this->progress($user_id);
      $timer->mark('progress_graph_data');
      $on_target_perc = (float) $this->on_target_percent_for_user($user_id);
      $timer->mark('on_target_percent');
      $days_behind = 
        $this->db->col("SELECT COUNT(*) FROM schedule_dates WHERE schedule_id = ".$active_schedule->ID." AND date <= '".date('Y-m-d')."'") - 
        $this->db->col("SELECT COUNT(*) FROM read_dates rd JOIN schedule_dates sd ON sd.id = rd.schedule_date_id WHERE sd.schedule_id = ".$active_schedule->ID." AND rd.user_id = $user_id");
      $timer->mark('days_behind');

      $stats = [
        'all_club_chapters_read' => $all_club_chapters_read,
        'all_club_words_read' => $all_club_words_read,
        'badges' => json_encode($badges),
        'challenge_percent' => 0,
        'chapters_ive_read' => $chapters_ive_read,
        'days_behind' => $days_behind,
        'deviation' => $deviation,
        'last_seen' => $last_seen,
        'last_read_ts' => $last_read_ts,
        'on_target' => $on_target,
        'on_target_percent' => $on_target_perc,
        'progress_graph_data' => json_encode($progress_graph_data),
        'words_ive_read' => $words_ive_read,
      ];

      $total_words_in_schedule = (int) $active_schedule->total_words_in_schedule();
      if ($total_words_in_schedule) {
        // challenge percent can only happen when a schedule has words
        $words_read = (int) $this->words_read($user, $active_schedule->ID);
        $stats['challenge_percent'] = $words_read / $total_words_in_schedule * 100;
        $timer->mark('challenge_percent');
      }

      $redis->set_user_stats($user_id, $stats);
      $stats['badges'] = $badges;
    }
    else {
      // badges are stored as a json string, so re-assign the decoded value
      $stats['badges'] = json_decode($stats['badges'], true);
      $stats['progress_graph_data'] = json_decode($stats['progress_graph_data'], true);
    }
    
    return $stats;
  }

  /**
   * deletes stats for either a single user id (if passed) or the entire site's users
   */
  public function invalidate_stats($user_id = 0)
  {
    $redis = Redis::get_instance();
    if ($user_id) {
      $redis->delete_stats($this->ID, $user_id);
    }
    else {
      foreach($this->all_users() as $user) {
        $redis->delete_stats($this->ID, $user['id']);
      }
    }
  }

  function logo_pngs($input_file, $output_path, $sizes)
  {
    foreach ($sizes as $size) {
      $output_file = "$output_path/logo_{$this->ID}_".$size."x".$size.".png";
      
      $command = "magick \"{$input_file}\" " .
                  "-background none " .
                  "-gravity center " .
                  "-resize {$size}x{$size} " .
                  "-extent {$size}x{$size} " .
                  "\"{$output_file}\"";
      
      shell_exec($command);
    }
  }
}
