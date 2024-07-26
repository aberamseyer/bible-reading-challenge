<?php
  require __DIR__."/../inc/init.php";

  session_destroy();

  redirect("/auth/login");