<!doctype html>
<html lang="en-US">
  <head>
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta charset="utf-8">
    <link rel="icon" type="image/x-icon" href="<?= $site->resolve_img_src('favico') ?>">
    <link rel="stylesheet" href="/css/normalize.css" media="screen" />
    <link rel="stylesheet" href="/css/sakura-coc.css" media="screen" />
    <link rel="stylesheet" href="/css/style.css" media="screen" />
    <?= $add_to_head ?>
    <title><?= html($site->data('short_name')) ?><?= $page_title ? " - ".$page_title : ""?></title>
    <style>
      @media(prefers-color-scheme: dark) {
        :root {
          --color-blossom: #ffffff;
          --color-secondary: <?= $site->data('color_secondary') ?>;
          --color-fade: #c9c9c9;
          
          --color-bg: #222222;
          --color-bg-alt: #4a4a4a;
          
          --color-text: #c9c9c9;
          --color-success: rgb(73, 138, 73);
        }
      }
      @media(prefers-color-scheme: light) {
        :root {
          --color-blossom: <?= $site->data('color_primary') ?>; /* buttons, horizontal lines, links */
          --color-secondary: <?= $site->data('color_secondary') ?>;
          --color-fade: <?= $site->data('color_fade') ?>;
          
          --color-bg: #f9f9f9; /* background */
          --color-bg-alt: #eeeeee; /* background for info banners at the top, translation selector, etc */
          
          --color-text: #4a4a4a; /* text color */
          --color-success: rgb(153, 205, 153); /* currently selected schedule, completed days */
        }
      }
    </style>
  </head>
  <body>
    <?php if ($me): ?>
    <div class='navigation-wrap'>
      <img class='logo' src='<?= $site->resolve_img_src('logo') ?>' onclick='window.location = `/`'>
      <?= navigation() ?>
    </div>
    <hr>
    <?php endif; ?>
    <h1 id='title'><?= $page_title ?></h1>
    <?php if($hide_title): ?>
      <style>
        #title { display: none; }
      </style>
    <?php endif; ?>
    <?php
      if ($_SESSION['error'] || $_SESSION['success'] || $_SESSION['info']) {
        echo "
          <blockquote id='message'>";
        if ($_SESSION['error']) {
          echo "<img class='icon' src='/img/circle-x.svg'>&nbsp;<small>".$_SESSION['error']."</small>";
          $_SESSION['error'] = '';
        }
        else if ($_SESSION['success']) {
          echo "<img class='icon' src='/img/circle-check.svg'>&nbsp;<small>".$_SESSION['success']."</small>";
          $_SESSION['success'] = '';
        }
        else if ($_SESSION['info']) {
          echo "<small>".$_SESSION['info']."</small>";
          $_SESSION['info'] = '';
        }
        echo "
          </blockquote>";
      }
    session_write_close();