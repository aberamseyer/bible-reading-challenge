<?php

require __DIR__."/../inc/init.php";

if (!$staff) {
  redirect('/');
}

$since = false;
if (isset($_GET['since'])) {
  $since = (int)strtotime($_GET['since']);
}
if (!$since) {
  $since = strtotime('July 1 -'.$site->TZ_OFFSET.' hours');
}

$recent_users = BibleReadingChallenge\Database::get_instance()->select("
  SELECT id, name, email, email_verses, DATE(date_created, 'unixepoch', '".$site->TZ_OFFSET." hours') registered
  FROM users
  WHERE site_id = ".$site->ID." AND date_created >= $since
  ORDER BY date_created DESC;");

$page_title = "Recent Signups";
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require DOCUMENT_ROOT."inc/head.php";
?>
<form action='/admin/recent' method='get'>
  <label>
    Show those registered since:
    <input 
      type='date'
      name='since'
      value='<?= date('Y-m-d', $since) ?>'
    >
    <button type='submit'>Refresh</button>
  </label>
</form>
<div class='table-scroll'>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Receiving Emails?</th>
        <th>Registered</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($recent_users as $user): ?>
        <tr>
          <td><?= $user['name'] ?></td>
          <td><?= $user['email'] ?></td>
          <td><img alt='check' class='icon' src='/img/static/circle-<?= $user['email_verses'] == 1 ? 'check' : 'x' ?>.svg'>
          <td><?= date('D, M j, Y', strtotime($user['registered'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?= !$recent_users ? "<tr><td>No one has registered since then.</td></tr>" : "" ?>
    </tbody>
  </table>
</div>

<?php
require DOCUMENT_ROOT."inc/foot.php";