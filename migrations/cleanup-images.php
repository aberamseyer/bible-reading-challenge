<?php

//
// Finds orphaned images in the /www/img/ and /upload/ directories
// 

require __DIR__."/../www/inc/env.php";
require __DIR__."/../www/inc/functions.php";

$db = BibleReadingChallenge\Database::get_instance();

$used_images = $db->cols("SELECT uploads_dir_filename FROM images");

$all_files = [];

foreach([UPLOAD_DIR, IMG_DIR] as $directory) {
  // keep things we aren't using
  $files = array_values(array_diff(scandir($directory), $used_images));

  // Filter out the "." and ".." entries
  $files = array_values(array_diff($files, ['.', '..', '.gitignore']));

  // make the filename include the entire absolute path
  $files = array_map(function($file) use ($directory) {
    return $directory.$file;
  }, $files);

  // Filter out directories and keep only files
  $files = array_filter($files, function($file) {
      return is_file($file) && strpos($file, '-placeholder') === false;
  });

  array_push($all_files, ...$files);
}

if (!$all_files) {
  echo "Nothing to report";
}
else {
  printf("%d Files differences:\n", count($all_files));
  foreach($all_files as $difference) {
    printf("\t%s\n", $difference);;
  }
  printf("Would you like to DELETE them? [Y/n] ");
  $input = trim(readline());
  if ($input !== "Y") {
    echo "Nothing deleted.\n";
  }
  else {
    echo "Are you sure? [YES] ";
    $input = trim(readline());
    if ($input !== "YES") {
      echo "Nothing deleted.\n";
    }
    else {
      foreach($all_files as $difference) {
        unlink($difference);
      }
    }
  }
}