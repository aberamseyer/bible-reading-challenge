<?php


require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}

ini_set('open_basedir', $_SERVER['DOCUMENT_ROOT']."../upload");

if ($fp = fopen(UPLOAD_DIR.$_GET['f'], 'r')) {
  $mime = mime_content_type(UPLOAD_DIR.$_GET['f']);
  if ($mime == 'text/plain') {
    if (pathinfo(UPLOAD_DIR.$_GET['f'], PATHINFO_EXTENSION) === 'svg') {
      $mime = 'image/svg+xml';
    }
  }
  header("Content-Length: ".filesize(UPLOAD_DIR.$_GET['f']));
  header("Content-Type: ".$mime);
  fpassthru($fp);
  die;
}