<?php

define('PROD', __DIR__ === '/home/bible-reading-challenge/www/inc');

error_reporting(PROD ? 0 : E_ALL^E_NOTICE^E_WARNING);

require_once __DIR__."/functions.php";

define('CLI', php_sapi_name() === 'cli');
define('DOCUMENT_ROOT', __DIR__."/../");
define('DB_FILE', DOCUMENT_ROOT."/../brc.db");
define('SESSIONS_DB_FILE', DOCUMENT_ROOT."/../sessions.db"); // only used as backup if redis is offline
define('SCHEME', PROD ? 'https' : 'http');
define('SESSION_LENGTH', 60*60*24*14); // 14-day sessions

define('UPLOAD_DIR', DOCUMENT_ROOT."/../upload/");
define('IMG_DIR', DOCUMENT_ROOT."/img/");
define('SCHEDULE_DIR', DOCUMENT_ROOT."/../extras/schedules/");

define('ALL_TRANSLATIONS', ['rcv', 'kjv', 'esv', 'asv', 'niv', 'nlt']);
