<?php

// Number of worker processes
define('WORKER_COUNT', (int)shell_exec("nproc") ?: 1);

// Tracks the worker PIDs
$workers = [];

// Function to start a worker
function start_worker() {
  $pid = pcntl_fork();
  if ($pid === -1) {
    die("Could not fork");
  } else if ($pid) {
    // Parent process
    echo "Started worker with PID [$pid]\n";
    return $pid;
  } else {
    // Child process
    require 'worker.php';
    exit();
  }
}

// Start initial workers
for ($i = 0; $i < WORKER_COUNT; $i++) {
  $workers[] = start_worker();
}

// Main loop to keep workers running
while (true) {
  // Check on the workers
  foreach ($workers as $i => $pid) {
    // WNOHANG allows us to cycle through all child processes instead of blocking at each one
    $res = pcntl_waitpid($pid, $status, WNOHANG);
    
    // If the worker has finished, start a new one
    if ($res === -1 || $res > 0) {
      unset($workers[$i]);
      $workers[] = start_worker();
    }
  }
  
  // Give the CPU time to breathe
  sleep(5);
}
