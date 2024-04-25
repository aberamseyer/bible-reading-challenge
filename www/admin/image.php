<?php


require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}

$file = $_GET['f'];
if (file_exists(UPLOAD_DIR.$file)) {
  $mime = mime_content_type(UPLOAD_DIR.$file);
  if ($mime == 'text/plain') {
    if (pathinfo(UPLOAD_DIR.$file, PATHINFO_EXTENSION) === 'svg') {
      $mime = 'image/svg+xml';
    }
  }
  header("Content-Length: ".filesize(UPLOAD_DIR.$file));
  header("Content-Type: ".$mime);
  readfile(UPLOAD_DIR.$file);
  die;
}