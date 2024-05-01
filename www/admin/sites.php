<?php


require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff && $me['id'] != 1) {
  redirect('/');
}

if ($_POST) {
  if ($_POST['create']) {
    // create site
    $new_site_id = $db->insert('sites', [
      'site_name' => $_POST['site_name'],
      'contact_name' => $_POST['contact_name'],
      'contact_email' => $_POST['contact_email'],
      'contact_phone' => $_POST['contact_phone'],
      'domain_www' => $_POST['domain_www'],
      'domain_www_test' => $_POST['domain_www_test'],
      'default_emoji' => '😁',
      'start_of_week' => 0,
    ]);
    // create default schedule so everything doesn't break
    $db->insert('schedules', [
      'site_id' => $new_site_id,
      'name' => 'Default Schedule',
      'start_date' => date('Y-m-d'),
      'end_date' => date('Y-m-d'),
      'active' => 1
    ]);
    $new_site = BibleReadingChallenge\Site::get_site($new_site_id, true);
    // create user and assign as staff
    $ret = $new_site->create_user($_POST['email'], $_POST['name']);
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

$page_title = "Sites";
$hide_title = true;
$add_to_head .= "
<link rel='stylesheet' href='/css/admin.css' media='screen'>";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";

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
        Contact name: <input type='text' name='contact_name' required>
      </label>
      <label>
        Contact email: <input type='text' name='contact_email' required>
      </label>
      <label>
        Contact phone: <input type='text' name='contact_phone' required>
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
  // all schedules summary
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
        <td data-name><small>".html($each_site['site_name'])."</small></td>
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
  
  $add_to_foot .= "
  <script src='/js/lib/tableSort.js'></script>
  <script src='/js/sites.js'></script>";
}

require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";