<?php


require __DIR__."/../inc/init.php";

if (!$staff && $me['id'] != 1) {
  redirect('/');
}

if ($_POST) {
  if ($_POST['create']) {
    // create web push keys
    $keys = Minishlink\WebPush\VAPID::createVapidKeys();

    // create site
    $new_site_id = $db->insert('sites', [
      'site_name' => $_POST['site_name'],
      'short_name' => $_POST['short_name'],
      'contact_name' => $_POST['contact_name'],
      'contact_email' => $_POST['contact_email'],
      'contact_phone' => $_POST['contact_phone'],
      'email_from_address' => $_POST['email_from_address'],
      'email_from_name' => $_POST['email_from_name'],
      'domain_www' => $_POST['domain_www'],
      'domain_www_test' => $_POST['domain_www_test'],
      'default_emoji' => '😁',
      'start_of_week' => 1,
      'time_zone_id' => 'America/Chicago',
      'translations' => json_encode(ALL_TRANSLATIONS),
      'web_push_pubkey' => $keys['publicKey'],
      'web_push_privkey' => $keys['privateKey']
    ]);
    // create default schedule so everything doesn't break
    $start_date = new DateTime(date('Y-m-d', strtotime('January 1')));
    $end_date = new DateTime(date('Y-m-d', strtotime('December 31')));
    $new_sched_id = BibleReadingChallenge\Schedule::create($start_date, $end_date, 'Default Schedule', $new_site_id, 1);
    $new_schedule = new BibleReadingChallenge\Schedule($new_site_id, $new_sched_id);

    $passage_readings = parsed_passages_to_passage_readings(parse_passages('Genesis 1'));
    $new_schedule->create_schedule_date($start_date->format('Y-m-d'), 'Genesis 1', $passage_readings, passage_readings_word_count($passage_readings));
    
    $new_site = BibleReadingChallenge\SiteRegistry::get_site($new_site_id, true);
    // create user and assign as staff:
    // already verified, so no email sent or verify key cached
    $ret = $new_site->create_user($_POST['email'], $_POST['name'], false, false, true);
    $db->update('users', [ 'staff' => 1 ], 'id = '.$ret['insert_id']);

    $_SESSION['success'] = 'Created '.html($_POST['site_name']);
    redirect();
  }
  else {
    $edit_site = $db->row("SELECT * FROM sites WHERE id = ".(int)$_POST['site_id']);
    if (!$edit_site) {
      $_SESSION['error'] = "No site given";
    }
    else {
      if ($_POST['toggle']) {
        if ($edit_site['id'] == $site->ID) {
          $_SESSION['error'] = 'Cant disable the site youre working from.';
        }
        else {
          $db->update('sites', [
            'enabled' => $edit_site['enabled'] ? 0 : 1
          ], 'id = '.$edit_site['id']);
          $_SESSION['success'] = (!$edit_site['enabled'] ? "Enabled": "Disabled")." ".html($edit_site['site_name']);
        }
        redirect();
      }
      else if ($_POST['edit']) {
        $_SESSION['edit_site_id'] = $edit_site['id'];
        redirect('/admin/customize');
      }
    }
  }
}
$active_edit_site = $_SESSION['edit_site_id'];

$page_title = "Sites";
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require DOCUMENT_ROOT."inc/head.php";

echo admin_navigation();

if ($_GET['create']) {
  echo "
  <form method='post'>
    <fieldset>
      <legend>Create new site</legend>
      <h5>Site info</h5>
      <label>
        Site name: <input type='text' name='site_name' required>
      </label>
      <label>
        Short name: <input type='text' name='short_name' required>
      </label>
      <label>
        Contact name: <input type='text' name='contact_name' required>
      </label>
      <label>
        Contact email: <input type='text' name='contact_email' required>
      </label>
      <label>
        Contact phone: <input type='text' name='contact_phone' required>
      </label>
      <label>
        System email address: <input type='email' name='email_from_address' required>
      </label>
      <label>
        System email name: <input type='name' name='email_from_name' required>
      </label>
      <label>
        WWW Domain: <input type='text' name='domain_www' required>
      </label>
      <label>
        WWW Test Domain: <input type='text' name='domain_www_test'>
      </label>
      <hr>
      <h5>Initial staff account info</h5>
      <label>
        Name: <input type='text' name='name' required>
      </label>
      <label>
        Email address: <input type='email' name='email' required>
      </label>
      <button type='submit' name='create' value='1'>Create</button>
    </fieldset>
  </form>";
}
else {
  // all sites summary
  $sites = $db->select("SELECT * FROM sites ORDER BY enabled DESC, site_name ASC");
  echo "<p><button type='button' onclick='window.location = `?create=1`'>+ Create Site</button></p>";
  echo "<table>
    <thead>
      <tr>
        <th data-sort='name'>
          Name
        </th>
        <th data-sort='enabled'>
          Enabled
        </th>
        <th data-sort='domain'>
          Domain
        </th>
        <th data-sort='test-domain'>
          Test Domain
        </th>
        <th data-sort='contact'>
          Contact
        </th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>";
  foreach($sites as $each_site) {
    echo "
      <tr>
        <td data-name><small>".html($each_site['site_name'])."</small>".($each_site['id'] == $active_edit_site ? "<div class='dot'></div>" : "")."</td>
        <td data-enabled='".($each_site['enabled'] == 1 ? 1 : 0)."'>".($each_site['enabled'] == 1 ? '<img src="/img/static/circle-check.svg" class="icon">' : '<img src="/img/static/circle-x.svg" class="icon">')."</td>
        <td data-domain='$each_site[domain_www]'><small><a href='".SCHEME."://$each_site[domain_www]' target='_blank'>".html($each_site['domain_www'])."</a></small></td>
        <td data-test-domain='$each_site[domain_www_test]'><small><a href='http://$each_site[domain_www_test]' target='_blank'>".html($each_site['domain_www_test'])."</a></small></td>
        <td data-contact='$each_site[contact_name]'><small><a href='mailto:$each_site[contact_email]'>".html($each_site['contact_name'].': '.format_phone($each_site['contact_phone']))."</a></small></td>
        <td>
          <form method='post'>
            <small>
              <input type='hidden' name='site_id' value='$each_site[id]'>
              <button type='submit' name='toggle' value='1'>".($each_site['enabled'] ? 'Disable' : 'Enable')."</button>
              <button type='submit' name='edit' value='1'>Edit</button>
            </small>
          </form>
        </td>
      </tr>";
  }
  
  echo "
    </tbody>
  </table>";
  
  $add_to_foot .= 
    cached_file('js', '/js/lib/tableSort.js').
    cached_file('js', '/js/sites.js');
}

require DOCUMENT_ROOT."inc/foot.php";