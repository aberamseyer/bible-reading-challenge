<?php

$insecure = true;
require __DIR__."/inc/init.php";

$page_title = "404";
require DOCUMENT_ROOT."inc/head.php";

?>
<?= site_logo() ?>
<hr>
<h1>Not found</h1>
<p><a href='/'>↩ Home</a></p>
<p>The page you requested
  <?php if($_GET['uri']) {
    echo "<code>$_GET[uri]</code>";
  } ?>
  could not be found.</p>
  <hr>
<?php
  
  require DOCUMENT_ROOT."inc/foot.php";

?>