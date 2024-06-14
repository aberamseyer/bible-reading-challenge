<?php

// creates schedules for existing users

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = BibleReadingChallenge\Database::get_instance();

$schedule_user_ids = $db->query("UPDATE schedules SET active = 1 WHERE user_id IS NOT NULL");
