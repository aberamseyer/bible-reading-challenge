<?php
if (strpos(__DIR__, '/home/bible-reading-challenge') !== false) {
  die('dont do this on production please.').PHP_EOL;
}

$user = readline("Enter user: ");

shell_exec('stty -echo');
echo 'Enter pw: ';
$password = trim(fgets(STDIN, 4096));
shell_exec('stty echo');
echo PHP_EOL;


$today = date('Ymd');
$filename = "brc-".$today."_100501Z.sql.gz";
$remote_url = "https://files.ramseyer.dev/export/$filename";

$context = stream_context_create([
  'http'=> [
    'method'=> "GET",
    'header' => "Authorization: Basic ".base64_encode("$user:$password")                 
  ]
]);

// Open the file using the HTTP headers set above
$data = @file_get_contents($remote_url, false, $context);
if ($data) {
  // good to go
  $fullpath = __DIR__."/".$filename;
  @unlink(__DIR__.'/../brc.db');
  file_put_contents($fullpath, $data);
  shell_exec("gzip -c -d $fullpath | sqlite3 ".__DIR__."/../brc.db");
  unlink($fullpath);
}
else {
  print("Couldn't get file.\n");
}

