<?php

$insecure = true;
require __DIR__."/inc/init.php";

global $db;

$db->update("users", [
  'email_verses' => 0
], "uuid = '".$db->esc($_GET['uuid'] ?: '')."'");

$page_title = "Unsubscribe";

require DOCUMENT_ROOT."inc/head.php";

?>
<?= site_logo() ?>
<hr>
<h2>Unsubscribe Successful</h2>
<p>You will no longer receive verse reading emails from us. This is effective immediately.</p>
<hr>
<?php
  
  require DOCUMENT_ROOT."inc/foot.php";

?>