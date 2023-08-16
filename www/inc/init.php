<?php

require_once "env.php";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

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
