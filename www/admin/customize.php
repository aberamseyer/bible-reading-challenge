<?php

ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');

require $_SERVER['DOCUMENT_ROOT']."inc/init.php";

if (!$staff) {
  redirect('/');
}

// uploaded pictures
if ($_FILES && $_FILES['upload'] && $_FILES['upload']['error'] == UPLOAD_ERR_OK) {
  $ext = '.'.pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION);
  $mime = mime_content_type($_FILES['upload']['tmp_name']);
  $valid_mime = strpos($mime, 'image/') === 0 || ($mime == 'text/plain' && $ext == '.svg'); // svg files appear as text/plain sometimes
  if (!$ext || !$valid_mime) {
    $_SESSION['error'] = "Please upload a regular image file";
  }
  else {
    $realpath = tempnam(UPLOAD_DIR, "upload-");
    unlink($realpath); // delete bc we're going to move the file there ourself
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
// Toggle active pictures
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
    foreach(['logo', 'login', 'progress', 'favico'] as $key) {
      // find the key we updated
      if ($_POST['set_'.$key]) {
        // if the site has this image set
        if ($site[$key.'_image_id']) {
          // delete old image out of the public serve directory
          unlink(IMG_DIR.col("SELECT uploads_dir_filename FROM images WHERE id = ".$site[$key.'_image_id']));
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

// Color form
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
    $_SESSION['success'] = 'Colors updated';
  }
  redirect();
}


$page_title = "Customize System";
$hide_title = true;
$large = true;
$add_to_head .= "
<link rel='stylesheet' href='/css/admin.css' media='screen'>";
require $_SERVER["DOCUMENT_ROOT"]."inc/head.php";
  
echo admin_navigation();

echo "<h2>Customize Site</h2>";

// Theming form
echo "
<form method='post'>
  <fieldset>
  <legend>Color Theming</legend>
    <label>
      Primary Color: <input type='color' name='color_primary' value='".rgb_to_hex($site['color_primary'])."'>
    </label>
    <label>
      Secondary Color: <input type='color' name='color_secondary' value='".rgb_to_hex($site['color_secondary'])."'>
    </label>
    <label>
      Faded Color ".help('Used for disabled and hovered buttons').": <input type='color' name='color_fade' value='".rgb_to_hex($site['color_fade'])."'>
    </label>
    <button type='submit'>Save Colors</button>
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
    Contact Person Email ".help('Email of the one who talks to Abe')." <input type='text' name='contact_email' value='".html($site['contact_email'])."'>
    </label>
    <label>
    Contact Person Phone # ".help('Phone # of the one who talks to Abe')." <input type='text' name='contact_phone' value='".html(format_phone($site['contact_phone']))."' placeholder='No country code'>
    </label>
    <label>
    Default Emoji ".help('What emoji new users will have by default')." <input type='text' name='default_emoji' minlength='1' maxlength='6' value='".html($site['default_emoji'])."' style='width: 70px'>
    </label>
    <label>
    Site Time Zone Emoji ".help('GMT offset as a one or two-digit positive/negative number')." <input type='text' name='default_emoji' minlength='1' maxlength='6' value='".html($site['default_emoji'])."' style='width: 70px'>
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
    <br>
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
  </fieldset>
</div>";

// Domain configuration form
echo "
<form method='post'>
  <fieldset>
    <legend>Domain Configuration</legend>
    <div>These values are read-only</div>
    <div><small>Contact abe@ramseyer.dev if they dont look right to you</small></div>
    <label>
    Site Domain ".help('The url in the address bar the students go to')." <input type='text' name='domain_www' value='".html($site['domain_www'])."' readonly='true'>
    </label>
    <label>
    Site Test Domain ".help('The url in the address bar you could go to see test changes')." <input type='text' name='domain_www_test' value='".html($site['domain_www_test'])."' readonly='true'>
    </label>
    <label>
    Site Domain ".help('The url the socket server connects to')." <input type='text' name='domain_socket' value='".html($site['domain_socket'])."' readonly='true'>
    </label>
    <label>
    Site Test Domain ".help('The test url the socket server connects to')." <input type='text' name='domain_socket_test' value='".html($site['domain_socket_test'])."' readonly='true'>
    </label>
    <label>
    Site Environment File ".help('The file to load configuration values from (Sendgrid API keys, Google Auth tokens, etc.)')." <input type='text' name='env_file' value='".html($site['env_file'])."' readonly>
    </label>
  </fieldset>
</form>";
require $_SERVER["DOCUMENT_ROOT"]."inc/foot.php";