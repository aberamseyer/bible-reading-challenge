<?php

require_once "env.php";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

// phpinfo();
// die;

// get site
$escaped = db_esc($_SERVER['HTTP_HOST']);
$site = row("SELECT * FROM sites WHERE domain_www = '$escaped' OR domain_www_test = '$escaped' LIMIT 1");
if (!$site) {
  die("nothing to see here: ".$_SERVER['HTTP_HOST']);
}
define('DOMAIN', PROD ? $site['domain_www'] : $url);
define('SOCKET_DOMAIN', PROD ? $site['domain_socket'] : $site['domain_socket_test']);

// read environment variables
foreach (explode("\n", file_get_contents(ENV_DIR.$site['env_file'])) as $line) {
  $line = trim($line);
  if ($line && !preg_match("/^\/\/.*$/", $line)) { // line doesn't begin with a comment
    list($key, $val) = explode("=", $line);
    define($key, $val);
  }
}

require_once "session.php";
session_name("brc-sessid");

ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', SESSION_LENGTH);
session_set_cookie_params(SESSION_LENGTH, "/", DOMAIN, PROD, true);
session_set_save_handler(new MySessionHandler(), true);
session_start();

// GLOBAL VARIABLES
$my_id = $_SESSION['my_id'] ?: 0;
$me = row("SELECT * FROM users WHERE id = ".(int) $my_id);
if ($me) {
  update("users", [
    'last_seen' => $time
  ], 'id = '.$my_id);
}
$staff = $me['staff'];
$schedule = get_active_schedule();

if (!$insecure && !$me) {
  $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'];
	redirect('/auth/login');
}
