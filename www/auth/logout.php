<?php
  require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

  session_destroy();

  redirect("/auth/login");