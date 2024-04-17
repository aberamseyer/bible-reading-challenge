<?php

error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED);

// paths
define('PROD', __DIR__ === '/home/bible-reading-challenge/www/inc');
define('DB_FILE', __DIR__."/../../brc.db");
define('DOMAIN', PROD ? 'app.uoficoc.com' : $_SERVER['HTTP_HOST']);
define('SOCKET_DOMAIN', PROD ? 'app-socket.uoficoc.com' : 'app-socket.uoficoc.local');
define('SCHEME', PROD ? 'https' : 'http');
define('SESSION_LENGTH', 60*60*24*30); // 30-day sessions

// read environment variables
foreach (explode("\n", file_get_contents(__DIR__."/../../.env")) as $line) {
  $line = trim($line);
  if ($line && !preg_match("/^\/\/.*$/", $line)) { // line doesn't begin with a comment
    list($key, $val) = explode("=", $line);
    define($key, $val);
  }
}

$time = time();