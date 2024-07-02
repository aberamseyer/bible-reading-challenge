<?php

require_once $_SERVER['DOCUMENT_ROOT']."/../vendor/autoload.php";
try {
  $REDIS_CLIENT = new Predis\Client(null, [ 'prefix' => 'bible-reading-challenge:' ]);
  $REDIS_CLIENT->connect();
}
catch (Exception $e) {
  error_log("redis offline");
  $REDIS_CLIENT = null;
}
require_once "env.php";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

// phpinfo();
// die;

// session setup
require_once "session/DBSessionHandler.php";
require_once "session/RedisSessionHandler.php";
session_name("brc-sessid");

$site = BibleReadingChallenge\Site::get_site();
$db = BibleReadingChallenge\Database::get_instance();

ini_set('session.use_strict_mode', 1);
session_set_cookie_params(SESSION_LENGTH, "/", $site->DOMAIN, PROD, true);
session_set_save_handler(
  $REDIS_CLIENT
  ? new RedisSessionHandler($REDIS_CLIENT)
  : new DBSessionHandler(new SQLite3(SESSIONS_DB_FILE))
  , true);
session_start();

// GLOBAL VARIABLES

$my_id = (int)$_SESSION['my_id'] ?: 0;
$me = $db->row("SELECT * FROM users WHERE site_id = ".$site->ID." AND id = ".(int) $my_id);
if ($me) {
  $db->update("users", [
    'last_seen' => time()
  ], 'id = '.$my_id);
}
$staff = $me['staff'];
$schedule = $site->get_active_schedule();

if (!$insecure && !$me) {
  $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
	redirect('/auth/login');
}