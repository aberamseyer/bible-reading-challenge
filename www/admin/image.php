<?php


require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}
session_write_close();

ini_set('open_basedir', $_SERVER['DOCUMENT_ROOT']."../upload");
$img_path = UPLOAD_DIR.$_GET['f'];
if (!file_exists($img_path) || is_dir($img_path)) {
  $img_path = UPLOAD_DIR."missing-placeholder.svg";
}
if ($fp = fopen($img_path, 'r')) {
  $mime = mime_content_type($img_path);
  if ($mime == 'text/plain') {
    if (pathinfo($img_path, PATHINFO_EXTENSION) === 'svg') {
      $mime = 'image/svg+xml';
    }
  }
  header("Content-Length: ".filesize($img_path));
  header("Content-Type: ".$mime);
  fpassthru($fp);
  die;
}