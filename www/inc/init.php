<?php

require_once "env.php";

global $insecure;

$db = BibleReadingChallenge\Database::get_instance();
$redis = BibleReadingChallenge\Redis::get_instance();
$site = BibleReadingChallenge\SiteRegistry::get_site();

health_checks();

// session setup
require_once "session/DBSessionHandler.php";
require_once "session/RedisSessionHandler.php";
session_name("brc-sessid");
ini_set('session.use_strict_mode', 1);
session_set_cookie_params(SESSION_LENGTH, "/", $site->DOMAIN, PROD, true);
session_set_save_handler(new BibleReadingChallenge\RedisSessionHandler($redis), true);
session_start();

// GLOBAL VARIABLES

$my_id = (int)$_SESSION['my_id'] ?: 0;
$me = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND id = ".(int) $my_id);
$staff = $me && $me['staff'];
$schedule = $site->get_active_schedule();

if ($my_id === 1) {
  error_reporting(E_ALL^E_NOTICE^E_WARNING);
    // phpinfo();
    // die($site->data("time_zone_id"));
}

if (!$insecure && !$me) {
  $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
	redirect('/auth/login');
}
else if ($my_id) {
  $redis->update_last_seen($my_id, time());
}

define('SINGLE_BOOKS', [ 'Obadiah', 'Philemon', '2 John', '3 John', 'Jude']);

define('BOOK_NAMES_AND_ABBREV', array_column(
  $db->select("
    SELECT b.id, b.name, b.name key
    FROM books b
    UNION ALL
    SELECT b.id, b.name, je.value key
    FROM books b, JSON_EACH(b.abbreviations) je"), null, 'key'));
/*
 * 4 cases for this to match a string:
 *    groups 1 && 2 && 4 && 5 && 6: Genesis 12:3-13:7 (verse range across chapters)
 *    groups 1 && 2 && 7 && 8:      Genesis 12:3-20 (multiple verses within one chapter)
 *    groups 1 && 2 && 3:           Genesis 3-4 (multiple chapters)
 *    groups 1 && 2 && 9:           Genesis 12:3 (exactly one verse)
 *    group  1 && 2:                Genesis 2 (exactly one entire chapter)
 */
define('BOOKS_RE', '/\b('.
  implode('|',
    array_map('preg_quote', array_keys(BOOK_NAMES_AND_ABBREV))
  ).')\b(?: (\d+)(?:[\-\–](\d+)|:(\d+)[\-\–](\d+):(\d+)|:(\d+)[\-\–](\d+)|:(\d+))?)?/im');
