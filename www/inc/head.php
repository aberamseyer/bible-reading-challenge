<!doctype html>
<html lang="en-US">
  <head>
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta charset="utf-8">
    <link rel="icon" type="image/x-icon" href="<?= $site->resolve_img_src('favico') ?>">
    <?= cached_file('css', '/css/normalize.css', 'media="screen"') ?>
    <?= cached_file('css', '/css/sakura-coc.css', 'media="screen"') ?>
    <?= cached_file('css', '/css/style.css', 'media="screen"') ?>
    <?= $add_to_head ?>
    <title><?= html($site->data('short_name')) ?><?= $page_title ? " - ".$page_title : ""?></title>
    <style>
      <?php 
      $coords = json_decode($site->data('progress_image_coordinates'), true);
      ?>
      @media(prefers-color-scheme: dark) {
        :root {
          --color-blossom: #ffffff;
          --color-secondary: <?= $site->data('color_secondary') ?>;
          --color-fade: #c9c9c9;
          
          --color-bg: #222222;
          --color-bg-alt: #4a4a4a;
          
          --color-text: #c9c9c9;
          --color-success: rgb(73, 138, 73);
          --color-warning: rgb(143, 132, 48);
          --color-danger: rgb(143, 48, 48);
        }
      }
      @media(prefers-color-scheme: light) {
        :root {
          --color-blossom: <?= $site->data('color_primary') ?>;     /* buttons, horizontal lines, links */
          --color-secondary: <?= $site->data('color_secondary') ?>; /* currently unused */
          --color-fade: <?= $site->data('color_fade') ?>;           /* hovered buttons */
          
          --color-bg: #f9f9f9;     /* background */
          --color-bg-alt: #eeeeee; /* background for info banners at the top, translation selector, etc */
          
          --color-text: #4a4a4a;               /* text color */
          --color-success: rgb(153, 205, 153); /* currently selected schedule, completed days */
          --color-warning: rgb(251, 239, 139); /* warnings */
          --color-danger: rgb(251, 139, 139);  /* errors */
        }
      }
      .mountain-wrap .emoji {
        bottom: <?= $coords[1] ?>%;
        left: <?= $coords[0] ?>%;
      }
    </style>
    <script>
      window.COLORS = {
        primary: '<?= $site->data('color_primary') ?>',
        secondary: '<?= $site->data('color_secondary') ?>',
        fade: '<?= $site->data('color_fade') ?>'
      }
      <?php
        echo "
        window.PROGRESS_X_1 = $coords[0];
        window.PROGRESS_Y_1 = $coords[1];
        window.PROGRESS_X_2 = $coords[2];
        window.PROGRESS_Y_2 = $coords[3]";
        ?>
    </script>
  </head>
  <body>
    <?php if ($me && !$insecure): ?>
    <div class='navigation-wrap'>
      <?= site_logo() ?>
      <?= navigation() ?>
    </div>
    <hr>
    <?php endif; ?>
    <?php if($show_title): ?>
      <h1 id='title'><?= $page_title ?></h1>
    <?php endif; ?>
    <?php
      if ($_SESSION['error'] || $_SESSION['success'] || $_SESSION['email']) {
        echo "
          <blockquote id='message'>";

        if ($_SESSION['error']) {
          echo "<img class='icon' src='/img/static/circle-x.svg'>&nbsp;<small>".$_SESSION['error']."</small>";
        }
        else if ($_SESSION['success']) {
          echo "<img class='icon' src='/img/static/circle-check.svg'>&nbsp;<small>".$_SESSION['success']."</small>";
        }
        else if ($_SESSION['email']) {
          echo "<img class='icon' src='/img/static/email.svg'>&nbsp;<small>".$_SESSION['email']."</small>";
        }
        
        $_SESSION['error'] = $_SESSION['success'] = $_SESSION['email'] = '';
        echo "
          </blockquote>";
      }
    session_write_close();