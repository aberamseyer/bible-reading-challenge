<?php
  require $_SERVER["DOCUMENT_ROOT"]."inc/init.php";

  require "index.php";

  if (!$allow_personal_schedules) {
    redirect('/my-schedule/corporate');
  }

  echo "<h1>Personal Schedule</h1>";
    
  require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";