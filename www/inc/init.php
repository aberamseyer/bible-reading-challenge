<?php

require_once "env.php";
require_once $_SERVER['DOCUMENT_ROOT']."/inc/functions.php";

// phpinfo();
// die;

require_once "session.php";
session_set_save_handler(new MySessionHandler(), true);
session_name("brc-sessid");
session_set_cookie_params(60*60*24*30, "/", DOMAIN, PROD, true); // 30-day session
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
