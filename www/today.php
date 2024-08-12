<?php

require __DIR__."/inc/init.php";

// this key is used by the websocket client to authenticate as the current user
$websocket_nonce = bin2hex(random_bytes(24));
$redis->set_websocket_nonce($me['id'], $websocket_nonce);

// set translation, update it if the select box changed

if ($_REQUEST['change_trans'] || array_key_exists('change_subscription_type', $_REQUEST)) {
  $new_trans = $_REQUEST['change_trans'];
  if (!$site->check_translation($new_trans)) {
    $new_trans = $site->get_translations_for_site()[0];
  }
  
  if (array_key_exists('change_subscription_type', $_REQUEST)) {
    // email subscriptions are enabled via a flag in the users table.
    // push notifications are enabled via a row in the push_subscriptions table. insertion into that table is handled in push/subscription.php
    if ($_REQUEST['change_subscription_type'] === 'both') {
      $me['email_verses'] = 1;
    }
    else if ($_REQUEST['change_subscription_type'] === 'email') {
      $db->query("DELETE FROM push_subscriptions WHERE user_id = ".$me['id']);
      $me['email_verses'] = 1;
    }
    else if ($_REQUEST['change_subscription_type'] === 'push') {
      $me['email_verses'] = 0;
    }
  }
  $db->update("users", [
    'trans_pref' => $me['trans_pref'] = $new_trans,
    'email_verses' => $me['email_verses']
  ], "id = ".$my_id);
}
$trans = $me['trans_pref'];

// figure out what today is (if overridden)
$today = new Datetime();
if ($_GET['today'] && strtotime($_GET['today'])) {
  $override_date = new Datetime($_GET['today']);
  $today = allowed_schedule_date($override_date)
    ? $override_date
    : $today;
}

// get list of schedules (corporate and personal)
$schedules = [ &$schedule ];
if ($site->data('allow_personal_schedules')) {
  $personal_schedule = new BibleReadingChallenge\Schedule($site->ID, true, $me['id']);
  $schedules[] = &$personal_schedule;
}
foreach($schedules as $each_schedule) {
  $scheduled_reading = $each_schedule->get_schedule_date($today);
  if (!$scheduled_reading) {
    continue;
  }
  // determine reading timer to use
  $reading_timer_wpm = (int)$site->data('reading_timer_wpm');
  if ($reading_timer_wpm) {
    $saved_start_time = (int)$_SESSION['reading_start_time'];
    $saved_reading_date = $_SESSION['reading_date'];
    if ($saved_reading_date !== $today->format('Y-m-d')) {
      $saved_start_time = $_SESSION['reading_start_time'] = time();
      $saved_reading_id = $_SESSION['reading_date'] = $today->format('Y-m-d');
    }
  }
  // "Done!" clicked
  if ($_REQUEST['done'] && !$each_schedule->day_completed($my_id, $scheduled_reading['id'])) {
    $valid = true;
    if ($_REQUEST['complete_key']) {
      // we need a way to bypass the wpm check from an email.
      // when the email is generated, a '-e' is appended to the complete key (see notifications.php's call to html_for_scheduled_reading() and Site.php)
      list($complete_key, $e) = explode('-', $_REQUEST['complete_key']);
      if ($e !== 'e') {
        $valid = $complete_key === $scheduled_reading['complete_key'];
      }
    }
    if ($reading_timer_wpm) {
      $words_per_second = round($reading_timer_wpm / 60.0, 4);
      $elapsed = time() - $saved_start_time;
      $words_on_page = (int)$db->col("
      SELECT SUM(sdv.word_count)
      FROM schedule_date_verses sdv
      WHERE sdv.schedule_date_id = ".(int)$scheduled_reading['id']);
      if ($elapsed * $words_per_second < $words_on_page) {
        $valid = false;
        $_SESSION['error'] = "You read ".number_format($words_on_page)." words in ".round($elapsed)." second".xs($elapsed)."! Thats pretty fast.";
      }
    }
  
    // handle "Done" click
    if ($valid) {
      $db->insert("read_dates", [
        'user_id' => $my_id,
        'schedule_date_id' => $scheduled_reading['id'],
        'timestamp' => time()
      ]);
      $site->invalidate_stats($my_id);
      if ($each_schedule->completed($my_id)) {
        $each_schedule->set_just_completed($my_id, true);
      }
    }
  }
}

$page_title = "Read";
require DOCUMENT_ROOT."inc/head.php";

$push_subscribed = $db->col("SELECT id FROM push_subscriptions WHERE user_id = ".$me['id']);

// header with translation selector and email pref
echo "<div id='date-header'>
  <h5>".$today->format("l, F j")."</h5>
  <form style='display: flex; width: 30rem; justify-content: space-between; align-items: flex-end;'>
    <select name='change_subscription_type' value=''>
      <option value='none' ".(!$me['email_verses'] && !$push_subscribed ? "selected" : "").">-Notifications-</option>
      <option value='email' ".($me['email_verses'] && !$push_subscribed ? "selected" : "").">Emails</option>
      <option value='push' ".(!$me['email_verses'] && $push_subscribed ? "selected" : "").">Push Notifications</option>
      <option value='both' ".($me['email_verses'] && $push_subscribed ? "selected" : "").">Both</option>
    </select>
    <input type='hidden' name='today' value='".$today->format('Y-m-d')."'>
    <select name='change_trans' onchange='this.form.submit();'>";
    foreach($site->get_translations_for_site() as $trans_opt)
      echo "
        <option value='$trans_opt' ".($trans_opt == $trans ? "selected" : "").">".strtoupper($trans_opt)."</option>";
  echo "</select>
  </form>
  </div>";

if ($me['streak']) {
  $streak = (int)$me['streak'];
  echo "
    <div>";
  echo $streak > 46
    ? '🔥 x'.number_format($streak)
    : str_repeat('🔥', (int)$me['streak']);
  echo "</div>";
}


if ($site->data('allow_personal_schedules')) {
  echo "
  <div class='tabs'>";
}
foreach($schedules as $i => $each_schedule) {
  $personal = (bool)$each_schedule->data('user_id');
  $scheduled_reading = $each_schedule->get_schedule_date($today);
  $today_completed = $scheduled_reading && $each_schedule->day_completed($my_id, $scheduled_reading['id']);

  if ($site->data('allow_personal_schedules')) {
    echo "
      <input type='radio' name='tabs' id='tab-$i' ".($i == 0 ? 'checked' : '').">
      <label for='tab-$i'>".($i == 0 ? 'Corporate' : 'Personal')." Schedule ".($scheduled_reading && !$today_completed ? "<span class='dot'></span>" : "")."</label>
      <div class='tab'>";
  }

  if (!$personal && $scheduled_reading) {
    // how many have read today
    $total_readers = $db->col("SELECT COUNT(*)
      FROM read_dates rd
      JOIN schedule_dates sd ON sd.id = rd.schedule_date_id
      WHERE sd.id = ".$scheduled_reading['id']);
    
    echo "<p><small>";
    if ($today_completed) {
      if ($total_readers == 1) {
        echo "You're the first to complete this reading";
      }
      else {
        echo "You and ".($total_readers-1)." other".xs($total_readers-1)." have completed this reading";
      }
    }
    echo "</small></p>";
  }
  if ($scheduled_reading) {    
    if ($i === 0) {
      $add_to_foot .= "<style>
        article {
          position: relative;
        }
        .mug {
          display: inline-block;
          position: absolute;
          width: 2rem;
          height: 2rem;
          left: 2px;
          top: 0;
          line-height: 1rem;
          font-size: 1.5rem;
          transition: .7s all;
          box-shadow: 1px 1px  2.4px var(--color-text);
          border-radius: 50%;
          padding: 3px;
          text-align: center;
          background: var(--color-fade);
        }
        .mug small {
          font-size: 0.7rem;
          display: block;
          text-align: center;
          margin-top: 2px;
          color: var(--color-bg);
        }
        .mug .caret-up {
          position: absolute;
          left: 0.9rem;
          top: -1.5rem;
          color: var(--color-fade);
          font-size: 1.1rem;
        }
      </style>
      <script>
        const WS_URL = 'ws".(PROD ? 's' : '')."://".$site->SOCKET_DOMAIN."'
        const WEBSOCKET_NONCE = '$websocket_nonce'
      </script>".
      cached_file('js', '/js/client.js');
    }
  }

  if (!$personal && $each_schedule->completed($my_id)) {
    echo "<blockquote><img alt='check' class='icon' src='/img/static/circle-check.svg'> You've completed the challenge! <button type='button' onclick='party()'>Congratulations!</button></blockquote>";
    $add_to_foot .= 
      cached_file('js', '/js/lib/js-confetti.min.js').
      "<script>
        const jsConfetti = new JSConfetti()
        function party() {
          const mess = () => {
            jsConfetti.addConfetti({  
              emojis: ['$me[emoji]'],
              emojiSize: 80,
              confettiNumber: 10,
            })
            jsConfetti.addConfetti({
              confettiNumber: 100,
            })
          }
          setTimeout(mess, 0)
          setTimeout(mess, 1300)
          setTimeout(mess, 2000)
          setTimeout(mess, 3300)
        }
        ".($each_schedule->get_just_completed($my_id) ? "party()" : "")."
      </script>";
  }
  if ($today_completed) {
    $each_day = clone($today);
    do {
      $next_reading = $each_schedule->get_next_reading($each_day);
      $dt = new Datetime($next_reading['date']);
      if ($today < $dt && $dt < new Datetime() && !$each_schedule->day_completed($my_id, $next_reading['id'])) { // if reading to check is between the real day and our current "today", and it's not yet read 
        $next_reading_link = " <a href='?today=".$dt->format('Y-m-d')."'>Next reading &gt;&gt;</a>";
        break;
      }
    } while(!$next_reading);
    echo "<blockquote><img alt='check' class='icon' src='/img/static/circle-check.svg'> You've completed the reading for today!$next_reading_link</blockquote>";
  }

  echo $site->html_for_scheduled_reading($scheduled_reading, $trans, $scheduled_reading['complete_key'], $each_schedule, $today);
  if ($site->data('allow_personal_schedules')) {
    echo "
      </div><!-- .tab -->";
  }
}
if ($site->data('allow_personal_schedules')) {
  echo "</div><!-- .tabs -->";
  
  // set active tab on page load
  $add_to_foot .= "
    <script type='text/javascript'>
      document.querySelector('.tabs').addEventListener('change', e => {
        localStorage.setItem('activeTabId', e.target.id)
      })
      const activeTabId = localStorage.getItem('activeTabId')
      const activeTabEl = document.getElementById(activeTabId)
      if (activeTabId && activeTabEl) {
        document.querySelectorAll('[name=tabs]').forEach(x => x.checked = false)
        activeTabEl.checked = true
      }
    </script>";

  // service worker registration for notifications
}
$add_to_foot .= "
  <script type='text/javascript'>
    const VAPID_PUBKEY = `".trim($site->data('vapid_pubkey'))."`
    const SERVICE_WORKER_FILE = `/service-worker.js?v=".VERSION."`
  </script>
  ".cached_file('js', '/js/today.js');


require DOCUMENT_ROOT."inc/foot.php";