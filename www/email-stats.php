<?php
require __DIR__."/inc/env.php";

if (isset($_GET['email_id'])) {
  $email_id = $_GET['email_id'];
  $db = BibleReadingChallenge\Database::get_instance();
  $db->update_email_stats($email_id, 'opened_timestamp');
}

header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
exit;
