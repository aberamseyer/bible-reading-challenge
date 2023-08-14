<?php

error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED);

$time = microtime(true);

// read environment variables
foreach (explode("\n", file_get_contents($_SERVER['DOCUMENT_ROOT']."../.env")) as $line) {
  if (!preg_match("/^\/\/.*$/", trim($line))) { // line doesn't begin with a comment
    list($key, $val) = explode("=", $line);
    define($key, $val);
  }
}

require $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

// phpinfo();
// die;

require_once "session.php";
session_set_save_handler(new MySessionHandler(), true);
session_name("brc-sessid");
session_start();

// GLOBAL VARIABLES
$my_id = $_SESSION['my_id'] ?: 0;
$me = row("SELECT * FROM users WHERE id = ".(int) $my_id);
if ($me) {
  update("users", [
    'last_seen' => time()
  ], 'id = '.$my_id);
}
$staff = $me['staff'];
$schedule = row("SELECT * FROM schedules WHERE active = 1");

if (!$insecure && !$me) {
	redirect('/auth/login?thru='.urlencode($_SERVER['REQUEST_URI']));
}
