<?php

ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}

// Uploaded pictures handler
if ($_FILES && $_FILES['upload'] && $_FILES['upload']['error'] == UPLOAD_ERR_OK) {
  $ext = '.'.pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION);
  $mime = mime_content_type($_FILES['upload']['tmp_name']);
  $valid_mime = strpos($mime, 'image/') === 0 || ($mime == 'text/plain' && $ext == '.svg'); // svg files appear as text/plain sometimes
  if (!$ext || !$valid_mime) {
    $_SESSION['error'] = "Please upload a regular image file";
  }
  else {
    $realpath = tempnam(UPLOAD_DIR, "upload-");
    unlink($realpath); // delete bc we're going to move the file there ourself later
    insert('images', [
      'site_id' => $site['id'],
      'uploaded_by_id' => $me['id'],
      'uploaded_name' => $_FILES['upload']['name'],
      'md5' => md5_file($_FILES['upload']['tmp_name']),
      'uploads_dir_filename' => basename($realpath).$ext,
      'extension' => $ext,
      'mime_type' => $mime
    ]);
    move_uploaded_file($_FILES['upload']['tmp_name'], $realpath.$ext);
    $_SESSION['success'] = 'File uploaded';
  }
  redirect();
}
// Pictures handler
if ($_POST['set_logo'] || $_POST['set_login'] || $_POST['set_progress'] || $_POST['set_favico'] || $_POST['delete_photo']) {
  $img_id = (int)$_POST['image_id'];
  $new_image = row("SELECT id, uploads_dir_filename FROM images WHERE id = $img_id AND site_id = $site[id]");
  if (!$img_id) {
    $_SESSION['error'] = 'Please provide an image';
  }
  else if (!$new_image) {
    $_SESSION['error'] = "That's not your image";
  }
  else if ($_POST['delete_photo']) {
    $using_row = row("
      SELECT id FROM sites WHERE 
        logo_image_id = $img_id OR login_image_id = $img_id
        OR progress_image_id = $img_id OR favico_image_id = $img_id");
    if ($using_row) {
      $_SESSION['error'] = "Image is being used";
    }
    else {
      unlink(UPLOAD_DIR.$new_image['uploads_dir_filename']);
      query("DELETE FROM images WHERE id = ".$new_image['id']);
      $_SESSION['success'] = "Image deleted";
    }
  }
  else {
    $image_keys = ['logo', 'login', 'progress', 'favico'];
    foreach($image_keys as $key) {
      // find the key we updated
      if ($_POST['set_'.$key]) {
        // if the site has this image set
        if ($site[$key.'_image_id']) {
          // determine if this photo is being used as something else before deleting it
          $img_to_del = row("
            SELECT id, uploads_dir_filename FROM images
            WHERE id = ".$site[$key.'_image_id']);
          $valid = true;
          // loop through the other keys and see if the $site is using it
          foreach(array_diff($image_keys, [$key]) as $other_key) {
            $valid = $valid && $site[$other_key.'_image_id'] != $img_to_del['id'];
          }
          if ($img_to_del && $valid) {
            // delete old image out of the public serve directory
            unlink(IMG_DIR.$img_to_del['uploads_dir_filename']);
          }
        }
        // copy the new image into the public serve directory from the uploads folder
        copy(UPLOAD_DIR.$new_image['uploads_dir_filename'], IMG_DIR.$new_image['uploads_dir_filename']);
        // activate the new image
        update('sites', [ $key.'_image_id' => $img_id ], 'id = '.$site['id']);
        $_SESSION['success'] = ucwords($key).' image updated';
        break;
      }
    }
  }
  redirect();
}

// Position form handler
if ($_POST['x_1'] !== null && $_POST['y_2'] !== null && $_POST['x_2'] !== null && $_POST['y_2'] !== null) {
  $x1 = clamp((float)$_POST['x_1'], -2, 102);
  $y1 = clamp((float)$_POST['y_1'], -2, 102);
  $x2 = clamp((float)$_POST['x_2'], -2, 102);
  $y2 = clamp((float)$_POST['y_2'], -2, 102);

  update('sites', [
    'progress_image_coordinates' => "[$x1,$y1,$x2,$y2]"
  ], 'id = '.$site['id']);
  $_SESSION['success'] = 'Progress image coordinates updated';

  redirect();
}

// Color form handler
if ($_POST['color_primary'] && $_POST['color_secondary'] && $_POST['color_fade']) {
  $error = null;
  $colors = [];
  foreach (['primary', 'secondary', 'fade'] as $color) {
    $colors[$color] = hex_to_rgb($_POST['color_'.$color]);
    if (!$colors[$color]) {
      $error = 'Please choose regular colors';
      break;
    }
  }
  if ($error) {
    $_SESSION['error'] = $error;
  }
  else {
    update('sites', [
      'color_primary' => $colors['primary'],
      'color_secondary' => $colors['secondary'],
      'color_fade' => $colors['fade']
    ], 'id = '.$site['id']);
    $_SESSION['success'] = 'Theme updated';
  }
  redirect();
}

// Site Configuration handler
if ($_POST['site_name'] || $_POST['short_name'] || $_POST['contact_name'] || $_POST['contact_email'] || $_POST['contact_phone'] || $_POST['default_emoji']) {
  $site_name = $_POST['site_name'];
  $short_name = $_POST['short_name'];
  $contact_name = $_POST['contact_name'];
  $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_VALIDATE_EMAIL);
  $contact_phone = preg_replace('/[^\d]/', '', $_POST['contact_phone']);
  $default_emoji = $_POST['default_emoji'];
  $start_of_week = (int)$_POST['start_of_week'];
  $timezone = $_POST['time_zone_id'];
  if (!$site_name) {
    $_SESSION['error'] = 'Dont forget to include a site name';
  }
  else if (!$short_name) {
    $_SESSION['error'] = 'Dont forget to include a short name for your site';
  }
  else if (!$contact_name) {
    $_SESSION['error'] = 'Please specify someone to contact';
  }
  else if (!$contact_email) {
    $_SESSION['error'] = 'Please specify a valid email';
  }
  else if (strlen($contact_phone) !== 10) {
    $_SESSION['error'] = 'Please specify a 10-digit phone number';
  }
  else if (grapheme_strlen($default_emoji) !== 1) {
    $_SESSION['error'] = 'Enter exactly 1 character for the default emoji';
  }
  else if ($start_of_week < 1 || 7 < $start_of_week) {
    $_SESSION['error'] = 'Weeks need to start on a day of the week';
  }
  else if(!in_array($timezone, timezone_identifiers_list(DateTimeZone::PER_COUNTRY, 'US'), true)) {
    $_SESSION['error'] = 'Invalid time zone';
  }
  else {
    update('sites', [
      'site_name' => $site_name,
      'short_name' => $short_name,
      'contact_name' => $contact_name,
      'contact_email' => $contact_email,
      'contact_phone' => $contact_phone,
      'default_emoji' => $default_emoji,
      'start_of_week' => $start_of_week,
      'time_zone_id' => $timezone
    ], 'id = '.$site['id']);
    $_SESSION['success'] = 'Site Configuration updated';
  }

  redirect();
}

$page_title = "Customize System";
$hide_title = true;
$add_to_head .= "
<link rel='stylesheet' href='/css/admin.css' media='screen'>";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  
echo admin_navigation();

echo "<h2>Customize Site</h2>";

// Theming form
echo "
<form method='post'>
  <fieldset>
  <legend>Theming</legend>
    <label>
      Primary Color: <input type='color' name='color_primary' value='".rgb_to_hex($site['color_primary'])."'> <button type='button'>Example</button>
    </label>
    <label>
      Secondary Color: <input type='color' name='color_secondary' value='".rgb_to_hex($site['color_secondary'])."'> <button type='' style='background-color: var(--color-secondary); border-color: var(--color-secondary)'>Example</button>
    </label>
    <label>
      Faded Color ".help('Used for disabled and hovered buttons').": <input type='color' name='color_fade' value='".rgb_to_hex($site['color_fade'])."'> <button type='button' style='background-color: var(--color-fade); border-color: var(--color-fade);'>Example</button>
    </label>
    <button type='submit'>Save Theme</button>
  </fieldset>
</form>";

// Site configuration form
echo "
<form method='post'>
  <fieldset>
  <legend>Site Configuration</legend>
    <label>
    Site name ".help('Title of your club')." <input type='text' name='site_name' value='".html($site['site_name'])."' style='width: 440px'>
    </label>
    <label>
    Title ".help('Used in the title bar of the web browser')." <input type='text' name='short_name' value='".html($site['short_name'])."'>
    </label>
    <label>
    Contact Person ".help('Who talks to Abe')." <input type='text' name='contact_name' value='".html($site['contact_name'])."'>
    </label>
    <label>
    Contact Email ".help('Email of the one who talks to Abe')." <input type='text' name='contact_email' value='".html($site['contact_email'])."'>
    </label>
    <label>
    Contact Phone # ".help('Phone # of the one who talks to Abe')." <input type='text' name='contact_phone' value='".html(format_phone($site['contact_phone']))."' placeholder='No country code'>
    </label>
    <label>
    Default Emoji ".help('What emoji new users will have by default')." <input type='text' name='default_emoji' minlength='1' maxlength='6' value='".html($site['default_emoji'])."' style='width: 70px'>
    </label>
    <label>
    Start of week ".help('What day the weekly reading stats begin on. It might be the date of your weekly campus meeting.')."
      <select name='start_of_week'>
        <option value='7' ".($site['start_of_week'] == '7' ? 'selected' : '').">Sunday</option>
        <option value='1' ".($site['start_of_week'] == '1' ? 'selected' : '').">Monday</option>
        <option value='2' ".($site['start_of_week'] == '2' ? 'selected' : '').">Tuesday</option>
        <option value='3' ".($site['start_of_week'] == '3' ? 'selected' : '').">Wednesday</option>
        <option value='4' ".($site['start_of_week'] == '4' ? 'selected' : '').">Thursday</option>
        <option value='5' ".($site['start_of_week'] == '5' ? 'selected' : '').">Friday</option>
        <option value='6' ".($site['start_of_week'] == '6' ? 'selected' : '').">Saturday</option>
      </select>
    </label>
    <label>
    Time zone ".help('What time zone do you live in? Please select appropriately based on whether where you are observes Daylight Savings Time or not.')."
      <select name='time_zone_id'>";
      $timezones = timezone_identifiers_list(DateTimeZone::PER_COUNTRY, 'US');
      sort($timezones);
      foreach($timezones as $id) {
        echo "<option value='$id' ".($id == $site['time_zone_id'] ? 'selected' : '').">$id</option>";
      }
echo "  </select>
    </label>
    <button type='submit'>Save Site Configuration</button>
  </fieldset>
</form>";

// Image form
echo "
<div>
  <fieldset>
    <legend>Image Management</legend>
    <label>
    Upload a new photo here to use as a logo or anything else. PNG files are best:
      <div>
        <small>
        Logos should be: 1033px by 404px<br>
        Login images should be: 502px by 639px<br>
        Progress images should be: 723px by 397px<br>
        Favico images should be: 32px by 32px
        </small>
      </div>
    <form method='post' enctype='multipart/form-data'>
      <input type='file' name='upload' accept='image/*'>
      <button type='submit'>Upload</button>
    </form>
    </label>
    <h5 class='text-center'>Uploaded photos</h5>
    <table>
      <thead>
        <tr>
          <th>File Name</th>
          <th>Thumbnail</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      ";
foreach(select("SELECT * FROM images WHERE site_id = $site[id]") as $image) {
  $logo_disabled = $site['logo_image_id'] == $image['id'] ? 'disabled' : '';
  $login_disabled = $site['login_image_id'] == $image['id'] ? 'disabled' : '';
  $progress_disabled = $site['progress_image_id'] == $image['id'] ? 'disabled' : '';
  $favico_disabled = $site['favico_image_id'] == $image['id'] ? 'disabled' : '';
  $delete_disabled = $logo_disabled || $login_disabled || $progress_disabled || $favico_disabled ? 'disabled' : '';
  echo "<tr>
    <td><small>".html($image['uploaded_name'])."</small></td>
    <td><img src='/admin/image?f=$image[uploads_dir_filename]' class='thumbnail'></td>
    <td>
      <form method='post'>
        <small style='font-size: 75%'>
          <input type='hidden' name='image_id' value='$image[id]'>
          <button type='submit' name='set_logo' value='1' $logo_disabled>Set Logo</button>
          <button type='submit' name='set_login' value='1' $login_disabled>Set Login Photo</button>
          <button type='submit' name='set_progress' value='1' $progress_disabled>Set Progress Photo</button>
          <button type='submit' name='set_favico' value='1' $favico_disabled>Set Favico Photo</button>
          <button type='submit' name='delete_photo' value='1' $delete_disabled onclick='return confirm(`Are you sure you want to delete this photo?`)'>Delete Photo</button>
        </small>
      </form>
    </td>
  </tr>";
}
echo "      </tbody>
    </table>

    <h5 class='text-center'>Progress Photo Positioning</h5>";
    if (!$site['progress_image_id']) {
      echo "<p><small>Please specify an image to use as the progress photo</small></p>";
    }
    else {
      $coords = json_decode($site['progress_image_coordinates'], true);
      echo "
      <form id='coord-controls' method='post'>
        <small>Use arrow keys to adjust beginning and ending positions of emoji progress</small>
        <div class='two-columns'>
          <div>
            <label>Begin x
              <input type='number' min='-2' step='0.1' max='102' name='x_1' value='$coords[0]' style='width: 100px'>
            </label>
            <label>Begin y
              <input type='number' min='-2' max='102' name='y_1' value='$coords[1]' style='width: 100px'>
            </label>
          </div>
          <div>
            <label>End x
              <input type='number' min='-2' step='0.1' max='102' name='x_2' value='$coords[2]' style='width: 100px'>
            </label>
            <label>End y
              <input type='number' min='-2' max='102' name='y_2' value='$coords[3]' style='width: 100px'>
            </label>
          </div>
        </div>
        <div class='mountain-wrap'>
          <span class='emoji' style='z-index: 1;' id='start'>
            <span class='inner'>▶️</span>
          </span>
          <span class='emoji' style='z-index: 1;' id='end'>
            <span class='inner'>⏹️</span>
          </span>
          <img src='".resolve_img_src($site, 'progress')."' class='mountain'>
        </div>
        <button type='submit'>Save Positions</button>
        <script>
          const coordInputs = document.querySelectorAll('#coord-controls input')
          const updatePositions = () => {
            start.style.left = coordInputs[0].value + '%'
            start.style.bottom = coordInputs[1].value + '%'
            end.style.left = coordInputs[2].value + '%'
            end.style.bottom = coordInputs[3].value + '%'
          }
          coordInputs.forEach(input => input.addEventListener('change', updatePositions))
          updatePositions()
        </script>
      </form>
      ";
    }
echo "  </fieldset>
</div>";

// Domain configuration form
echo "
<form method='post'>
  <fieldset>
    <legend>Domain Configuration</legend>
    <h4>These values are read-only</h4>
    <h5><small>Contact Abe (abe@ramseyer.dev) if they dont look right to you</small></h5>
    <label>
    <div><small>Ensure you have a DNS (A or CNAME) Record with this value pointing to <code style='display: inline-block;'>5.161.204.56</code></small></div>
    Site Domain ".help('The url in the address bar the students go to')." <input type='text' name='domain_www' value='".html($site['domain_www'])."' readonly='true'>
    </label>
    <label>
    Site Test Domain ".help('The url in the address bar you could go to see test changes')." <input type='text' name='domain_www_test' value='".html($site['domain_www_test'])."' readonly='true'>
    </label>
    <label>
    <div><small>Ensure you have a DNS (A or CNAME) Record with this value pointing to <code style='display: inline-block;'>5.161.204.56</code></small></div>
    Socket Server Domain ".help('The url the socket server connects to')." <input type='text' name='domain_socket' value='".html($site['domain_socket'])."' readonly='true'>
    </label>
    <label>
    Socket Server Test Domain ".help('The test url the socket server connects to')." <input type='text' name='domain_socket_test' value='".html($site['domain_socket_test'])."' readonly='true'>
    </label>
    <label>
    Site Environment File ".help('The file to load configuration values from (Sendgrid API keys, Google Auth tokens, etc.)')." <input type='text' name='env_file' value='".html($site['env_file'])."' readonly>
    </label>
  </fieldset>
</form>";
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";