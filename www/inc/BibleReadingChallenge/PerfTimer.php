<?php

namespace BibleReadingChallenge;

class PerfTimer {
  private $marks;
  private $start_time;

  public function __construct()
  {
    $this->marks = [];
    $this->start_time = hrtime(true);
  }

  public function mark($name)
  {
    $this->marks[ $name ] = hrtime(true);
  }


  public function format($html = false)
  {
    $output = "";
    $last = $this->start_time;
    $output .= "---------------------------------\n";
    $output .= "Timer results: ".PHP_EOL;
    foreach($this->marks as $mark => $ns) {
      $output .= sprintf("\t%-12s: %-12s\t\tTotal: %s", $mark, $this->format_ns($ns - $last), $this->format_ns($ns - $this->start_time)).PHP_EOL;
      $last = $ns;
    }
    $output .= "---------------------------------\n";
    return $html ? nl2br($output) : $output;
  }

  private function format_ns($ns)
  {
    return number_format($ns / 1e6, 2)."ms";
  }
}