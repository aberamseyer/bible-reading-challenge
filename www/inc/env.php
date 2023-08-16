<?php

error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED);

date_default_timezone_set("America/Chicago");


// paths
define('PROD', __DIR__ === '/home/bible-reading-challenge/www/inc');
define('DB_PATH', __DIR__."/../../brc.db");

define('DOMAIN', PROD ? 'app.uoficoc.com' : 'brc.local');

// read environment variables
foreach (explode("\n", file_get_contents(__DIR__."/../../.env")) as $line) {
  $line = trim($line);
  if ($line && !preg_match("/^\/\/.*$/", $line)) { // line doesn't begin with a comment
    list($key, $val) = explode("=", $line);
    define($key, $val);
  }
}

$time = time();