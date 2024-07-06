<?php

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}

$edit_site_id = (int)$_SESSION['edit_site_id'];
$abe = $my_id == 1;
if ($abe) {
  if ($edit_site_id) {
    $site = BibleReadingChallenge\Site::get_site($edit_site_id, true);
  }
  if ($_POST['reset']) {
    $_SESSION['edit_site_id'] = '';
    redirect();
  }
}


// Uploaded pictures handler
if ($_FILES && $_FILES['upload']) {
  $ext = '.'.pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION);
  $mime = mime_content_type($_FILES['upload']['tmp_name']);
  $valid_mime = strpos($mime, 'image/') === 0 || ($mime == 'text/plain' && $ext == '.svg'); // svg files appear as text/plain sometimes
  $md5 = md5_file($_FILES['upload']['tmp_name']);
  if (!$ext || !$valid_mime) {
    $_SESSION['error'] = "Please upload a regular image file";
  }
  else if ($existing_match = $db->col("SELECT uploaded_name FROM images WHERE md5 = '".$db->esc($md5)."' AND site_id = ".$site->ID)) { 
    $_SESSION['error'] = "A file like that seems to already exist by the name '".html($existing_match)."'";
  }
  else if ($_FILES['upload']['error'] != UPLOAD_ERR_OK) {
    $msg = [
      0 => 'There is no error, the file uploaded with success',
      1 => 'The uploaded file exceeds the maximum: '.ini_get('upload_max_filesize'),
      2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
      3 => 'The uploaded file was only partially uploaded',
      4 => 'No file was uploaded',
      6 => 'Missing a temporary folder',
      7 => 'Failed to write file to disk.',
      8 => 'A PHP extension stopped the file upload.',
    ][ $_FILES['upload']['error'] ];
    $_SESSION['error'] = "Error uploading file: $msg";
  }
  else {
    $realpath = tempnam(UPLOAD_DIR, "upload-");
    unlink($realpath); // delete bc we're going to move the file there ourself later
    $realpath .= uniqid();
    $db->insert('images', [
      'site_id' => $site->ID,
      'uploaded_by_id' => $me['id'],
      'uploaded_by_name' => $me['name'], // because users are hard deleted
      'uploaded_name' => $_FILES['upload']['name'],
      'md5' => $md5,
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
  $new_image = $db->row("SELECT id, uploads_dir_filename FROM images WHERE id = $img_id AND site_id = ".$site->ID);
  if (!$img_id) {
    $_SESSION['error'] = 'Please provide an image';
  }
  else if (!$new_image) {
    $_SESSION['error'] = "That's not your image";
  }
  else if ($_POST['delete_photo']) {
    $using_row = $db->row("
      SELECT id FROM sites WHERE 
        logo_image_id = $img_id OR login_image_id = $img_id
        OR progress_image_id = $img_id OR favico_image_id = $img_id");
    if ($using_row) {
      $_SESSION['error'] = "Image is being used";
    }
    else {
      unlink(UPLOAD_DIR.$new_image['uploads_dir_filename']);
      $db->query("DELETE FROM images WHERE id = ".$new_image['id']);
      $_SESSION['success'] = "Image deleted";
    }
  }
  else {
    $image_keys = ['logo', 'login', 'progress', 'favico'];
    foreach($image_keys as $key) {
      // find the key we updated
      if ($_POST['set_'.$key]) {
        // if the site has this image set
        if ($site->data($key.'_image_id')) {
          // determine if this photo is being used as something else before deleting it
          $img_to_del = $db->row("
            SELECT id, uploads_dir_filename FROM images
            WHERE id = ".$site->data($key.'_image_id'));
          $valid = true;
          // loop through the other keys and see if the site is using it
          foreach(array_values(array_diff($image_keys, [$key])) as $other_key) {
            $valid = $valid && $site->data($other_key.'_image_id') != $img_to_del['id'];
          }
          if ($img_to_del && $valid) {
            // delete old image out of the public serve directory
            unlink(IMG_DIR.$img_to_del['uploads_dir_filename']);
          }
        }
        // copy the new image into the public serve directory from the uploads folder
        copy(UPLOAD_DIR.$new_image['uploads_dir_filename'], IMG_DIR.$new_image['uploads_dir_filename']);
        // activate the new image
        $db->update('sites', [ $key.'_image_id' => $img_id ], 'id = '.$site->ID);
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

  $db->update('sites', [
    'progress_image_coordinates' => "[$x1,$y1,$x2,$y2]"
  ], 'id = '.$site->ID);
  $_SESSION['success'] = 'Progress image coordinates updated';

  redirect();
}

// Color form handler
if ($_POST['color_primary'] && $_POST['color_secondary']) {
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
    $db->update('sites', [
      'color_primary' => $colors['primary'],
      'color_secondary' => $colors['secondary'],
      'color_fade' => $colors['fade']
    ], 'id = '.$site->ID);
    $_SESSION['success'] = 'Theme updated';
  }
  redirect();
}

// Site Configuration handler
if ($_POST['site_name'] || $_POST['short_name'] || $_POST['contact_name'] || $_POST['contact_email'] || 
    $_POST['contact_phone'] || $_POST['default_emoji'] || $_POST['reading_timer_wpm'] || $_POST['start_of_week'] || 
    $_POST['time_zone_id'] || $_POST['trans_pref']) {
  $site_name = $_POST['site_name'];
  $short_name = $_POST['short_name'];
  $contact_name = $_POST['contact_name'];
  $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_VALIDATE_EMAIL);
  $contact_phone = preg_replace('/[^\d]/', '', $_POST['contact_phone']);
  $default_emoji = $_POST['default_emoji'];
  $reading_timer_wpm = (int)$_POST['reading_timer_wpm'];
  $start_of_week = (int)$_POST['start_of_week'];
  $timezone = $_POST['time_zone_id'];
  $allow_personal_schedules = (int)$_POST['allow_personal_schedules'];
  $trans_pref_arr = $_POST['trans_pref'];
  $trans_pref_arr_for_db = [];
  foreach($trans_pref_arr as $val) {
    if (in_array($val, ALL_TRANSLATIONS, true)) {
      $trans_pref_arr_for_db []= $val;
    }
  }
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
  else if ($reading_timer_wpm < 0 || 800 < $reading_timer_wpm) {
    $_SESSION['error'] = 'Choose a wpm value between 0 (off) and 800 (slow)';
  }
  else if (!$trans_pref_arr || !is_array($trans_pref_arr)) {
    $_SESSION['error'] = 'Allow at least one translation';
  }
  else {
    $db->update('sites', [
      'site_name' => $site_name,
      'short_name' => $short_name,
      'contact_name' => $contact_name,
      'contact_email' => $contact_email,
      'contact_phone' => $contact_phone,
      'default_emoji' => $default_emoji,
      'reading_timer_wpm' => $reading_timer_wpm,
      'start_of_week' => $start_of_week,
      'time_zone_id' => $timezone,
      'allow_personal_schedules' => $allow_personal_schedules,
      'translations' => json_encode($trans_pref_arr_for_db)
    ], 'id = '.$site->ID);
    foreach(ALL_TRANSLATIONS as $trans) {
      // anyone using a translation that is not allowed gets the default translation
      if (!in_array($trans, $trans_pref_arr_for_db, true)) {
        $db->query("UPDATE users SET trans_pref = '".$trans_pref_arr_for_db[0]."' WHERE site_id = ".$site->ID." AND trans_pref = '$trans'");
      }
    }
    $_SESSION['success'] = 'Site Configuration updated';
  }

  redirect();
}

if ($abe && ($_POST['domain_www'] || $_POST['domain_www_test'] || $_POST['domain_socket'] || $_POST['domain_socket_test'] || $_POST['env']))  {
  $db->update('sites', [
    'domain_www' => $_POST['domain_www'],
    'domain_www_test' => $_POST['domain_www_test'],
    'domain_socket' => $_POST['domain_socket'],
    'domain_socket_test' => $_POST['domain_socket_test'],
    'env' => $_POST['env']
  ], 'id = '.$site->ID);
  $_SESSION['success'] = "Domain configuration updated";
  redirect();
}

$page_title = "Customize Site";
$add_to_head .= cached_file('css', '/css/admin.css', 'media="screen"');
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  
echo admin_navigation();

if ($edit_site_id) {
  echo "
  <blockquote>You're editing '<b>".$site->data('site_name')."</b>'.
  <form method='post'>
    <button type='submit' name='reset' value='1'>Back to home site</button>
  </form></blockquote>";
}

// Theming form
echo "
<form method='post'>
  <fieldset>
  <legend>Theming</legend>
    <label>
      Primary Color <input type='color' name='color_primary' value='".rgb_to_hex($site->data('color_primary'))."'> <button type='button'>Example</button>
    </label>
    <label>
      Secondary Color <input type='color' name='color_secondary' value='".rgb_to_hex($site->data('color_secondary'))."'> <button type='button' style='background-color: var(--color-secondary); border-color: var(--color-secondary)'>Example</button>
    </label>
    <label>
      Faded Color ".help('The color of buttons when hovered over')." <input type='color' name='color_fade' value='".rgb_to_hex($site->data('color_fade'))."'> <button type='button' style='background-color: var(--color-fade); border-color: var(--color-fade);'>Example</button>
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
    Site name ".help('Title of your club')." <input type='text' name='site_name' value='".html($site->data('site_name'))."' style='width: 440px'>
    </label>
    <label>
    Title ".help('Used in the title bar of the web browser')." <input type='text' name='short_name' value='".html($site->data('short_name'))."'>
    </label>
    <label>
    Contact Person ".help('Who talks to Abe')." <input type='text' name='contact_name' value='".html($site->data('contact_name'))."'>
    </label>
    <label>
    Contact Email ".help('Email of the one who talks to Abe')." <input type='text' name='contact_email' value='".html($site->data('contact_email'))."'>
    </label>
    <label>
    Contact Phone # ".help('Phone # of the one who talks to Abe')." <input type='text' name='contact_phone' value='".html(format_phone($site->data('contact_phone')))."' placeholder='No country code'>
    </label>
    <label>
    Default Emoji ".help('What emoji new users will have by default')." <input type='text' name='default_emoji' minlength='1' maxlength='6' value='".html($site->data('default_emoji'))."' style='width: 70px'>
    </label>
    <div class='form-group'>
      <label>
        Reading Timer Words Per Minute (wpm) rate ".help('Readers will be unable to submit a reading from the website unless they have been on the page long enough to read all the words at the following rate in wpm. Disable this by setting this to 0.')." 
        <div class='form-help'>
          0: Disabled <br>
          170: Audiobook spoken reading pace <br>
          240: Average adult silent reading pace <br>
          340: Quicker silent reading pace <br>
          800: Maxmimum reading pace (very fast!)
        </div>
      </label>
      <input type='number' name='reading_timer_wpm' min='0' max='800' step='10' value='".$site->data('reading_timer_wpm')."'>
    </div>
    <label>
    Start of week ".help('What day the weekly reading stats begin on. It might be the date of your weekly campus meeting.')."
      <select name='start_of_week'>
        <option value='7' ".($site->data('start_of_week') == '7' ? 'selected' : '').">Sunday</option>
        <option value='1' ".($site->data('start_of_week') == '1' ? 'selected' : '').">Monday</option>
        <option value='2' ".($site->data('start_of_week') == '2' ? 'selected' : '').">Tuesday</option>
        <option value='3' ".($site->data('start_of_week') == '3' ? 'selected' : '').">Wednesday</option>
        <option value='4' ".($site->data('start_of_week') == '4' ? 'selected' : '').">Thursday</option>
        <option value='5' ".($site->data('start_of_week') == '5' ? 'selected' : '').">Friday</option>
        <option value='6' ".($site->data('start_of_week') == '6' ? 'selected' : '').">Saturday</option>
      </select>
    </label>
    <label>
    Time zone ".help('What time zone do you live in? Please select appropriately based on whether where your location observes Daylight Savings Time or not.')."
      <select name='time_zone_id'>";
      $timezones = timezone_identifiers_list(DateTimeZone::PER_COUNTRY, 'US');
      sort($timezones);
      foreach($timezones as $id) {
        echo "<option value='$id' ".($id == $site->data('time_zone_id') ? 'selected' : '').">".str_replace('_', ' ', str_replace('America/', '', $id))."</option>";
      }
echo "  </select>
    </label>
    <label>
      Allow personal reading schedules ".help('Allow the creation of personal reading schedules alongside the corporate schedule.')."
      <input type='checkbox' name='allow_personal_schedules' value='1' ".($site->data('allow_personal_schedules') ? 'checked' : '').">
    </label>
    <div class='form-group draggable'>
    Available Tranlsations ".help('These translations will be available for reading. Drag to re-order them; the first one in will be the default translation.');
    $difference = array_diff(ALL_TRANSLATIONS, $site->get_translations_for_site());
    foreach([ ...$site->get_translations_for_site(), ...$difference ] as $trans) {
      echo "<label draggable='true'><input type='checkbox' name='trans_pref[]' value='$trans' ".($site->check_translation($trans) ? 'checked' : '')."> $trans</label>";
    }
    echo "</div>";
    $add_to_foot .= cached_file('js', '/js/customize.js');
    echo "<button type='submit'>Save Site Configuration</button>
  </fieldset>
</form>";

// Image form
echo "
<div>
  <fieldset>
    <legend>Image Management</legend>
    <div class='form-group'>
      <label>
        Upload photos here to use as logos or anything else. PNG files are best:
        <div class='form-help'>
          Logo should be: <code>1033px</code> by <code>404px</code> <br>
          Login image should be: <code>502px</code> by <code>639px</code> <br>
          Progress image should be: <code>723px</code> by <code>397px</code> <br>
          Favico image should be: <code>32px</code> by <code>32px</code>
        </div>
      </label>
      <form method='post' enctype='multipart/form-data'>
        <input type='file' name='upload' accept='image/*'>
        <button type='submit'>Upload</button>
      </form>
    </div>
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
foreach($db->select("SELECT * FROM images WHERE site_id = ".$site->ID) as $image) {
  $logo_disabled = $site->data('logo_image_id') == $image['id']? 'disabled' : '';
  $login_disabled = $site->data('login_image_id') == $image['id'] ? 'disabled' : '';
  $progress_disabled = $site->data('progress_image_id') == $image['id'] ? 'disabled' : '';
  $favico_disabled = $site->data('favico_image_id') == $image['id'] ? 'disabled' : '';
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
          <button type='submit' name='delete_photo' value='1' $delete_disabled onclick='return confirm(`Are you sure you want to delete this photo?`)'>
            Delete Photo".($delete_disabled ? ' '.help("This photo is being used and cannot be deleted") : '')."
          </button>
        </small>
      </form>
    </td>
  </tr>";
}
echo "      </tbody>
    </table>

    <h5 class='text-center'>Progress Photo Positioning</h5>";
    if (!$site->data('progress_image_id')) {
      echo "<p><small>Please specify an image to use as the progress photo</small></p>";
    }
    else {
      $coords = json_decode($site->data('progress_image_coordinates'), true) ?: [0,0,0,0];
      echo "
      <form id='coord-controls' method='post'>
        <small>Use arrow keys to adjust beginning and ending positions of emoji progress. They will move from ▶️ to ⏹️ on the <a target='_blank' href='/profile'>\"Profile\"</a> page</small>
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
          <img src='".$site->resolve_img_src('progress')."' class='mountain'>
        </div>
        <button type='submit'>Save Positions</button>
      </form>";
      $add_to_foot .= "
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
        </script>";
    }
echo "  </fieldset>
</div>";

// Domain configuration form
$readonly = !$abe ? "readonly='true'" : "";
echo "
<form method='post'>
  <fieldset>
    <legend>Domain Configuration</legend>
    <h4>The following values are not editable</h4>
    <h5><small>Contact Abe (<a href='mailto:abe@ramseyer.dev?subject=Update%20Site%20Configuration&body=I%20need%20help%20updating%20the%20site%20configuration%20for%20".$site->data('site_name').".'>abe@ramseyer.dev</a>) if you think they need to be changed</small></h5>
    <div class='form-group'>
      <label>
        Site Domain ".help('The url in the address bar the students go to')."
        <div class='form-help'>
          Ensure you have a DNS Record (A, or CNAME for a subdomain) with this value pointing to <code>5.161.204.56</code>
        </div>
      </label>
      <input type='text' name='domain_www' value='".html($site->data('domain_www'))."' $readonly>
    </div>
    <label>
      Site Test Domain ".help('The url in the address bar you could go to see test changes')." <input type='text' name='domain_www_test' value='".html($site->data('domain_www_test'))."' $readonly>
    </label>
    <div class='form-group'>
      <label>
        Socket Server Domain ".help('The url the socket server connects to')."
        <div class='form-help'>
          Ensure you have a DNS Record (A, or CNAME for a subdomain) with this value pointing to <code>5.161.204.56</code>
        </div>
      </label>
      <input type='text' name='domain_socket' value='".html($site->data('domain_socket'))."' $readonly>
    </div>
    <label>
      Socket Server Test Domain ".help('The test url the socket server connects to')." <input type='text' name='domain_socket_test' value='".html($site->data('domain_socket_test'))."' $readonly>
    </label>
    <label>
    <details>
      <summary>Site Environment Configuration ".help('API keys for sending emails and enabling Signin with Google. DO NOT share these with anyone else.')." </summary>";
      echo $abe
        ? "<textarea name='env' required rows='30'>".html($site->data('env'))."</textarea><button type='submit'>Submit</button>"
        : "<pre>".html($site->data('env'))."</pre>";
  echo "  </details>
    </label>
  </fieldset>
</form>";
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";