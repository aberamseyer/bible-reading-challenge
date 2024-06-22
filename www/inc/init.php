<?php

require_once "env.php";
require_once $_SERVER['DOCUMENT_ROOT']."/../vendor/autoload.php";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

// phpinfo();
// die;

require_once "session/DBSessionHandler.php";
require_once "session/RedisSessionHandler.php";
session_name("brc-sessid");

$site = BibleReadingChallenge\Site::get_site();
$db = BibleReadingChallenge\Database::get_instance();

ini_set('session.use_strict_mode', 1);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', SESSION_LENGTH);
session_set_cookie_params(SESSION_LENGTH, "/", $site->DOMAIN, PROD, true);
session_set_save_handler(new RedisSessionHandler(), true);
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