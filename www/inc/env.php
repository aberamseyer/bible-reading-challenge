<?php

error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED);

define('PROD', __DIR__ === '/home/bible-reading-challenge/www/inc');
define('DB_FILE', __DIR__."/../../brc.db");
define('SCHEME', PROD ? 'https' : 'http');
define('SESSION_LENGTH', 60*60*24*30); // 30-day sessions

// directory constants
define('UPLOAD_DIR', __DIR__."/../../upload/");
define('ENV_DIR', __DIR__."/../../env-files/");
define('IMG_DIR', __DIR__."/../img/");

$time = time();