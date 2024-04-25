<!doctype html>
<html lang="en-US">
  <head>
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta charset="utf-8">
    <link rel="icon" type="image/x-icon" href="/img/favicon.png">
    <link rel="stylesheet" href="/css/normalize.css" media="screen" />
    <link rel="stylesheet" href="/css/sakura-coc.css" media="screen" />
    <link rel="stylesheet" href="/css/style.css" media="screen" />
    <?= $add_to_head ?>
    <title>U of I CoC<?= $page_title ? " - ".$page_title : ""?></title>
  </head>
  <body <?= $large ? 'style="max-width: 68em"' : '' ?>>
    <?php if ($me): ?>
    <div class='navigation-wrap'>
      <img class='logo' src='/img/coc-logo.svg' onclick='window.location = `/`'>
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