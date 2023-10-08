<?php

require_once "env.php";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

// phpinfo();
// die;

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
