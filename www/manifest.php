<?php

$insecure = true;

require __DIR__."/inc/init.php";

print_json([
  'name' => 'Bible Reading Challenge',
  'short_name' => $site->data('short_name'),
  'start_url' => './',
  'display' => 'standalone',
  'background_color' => '#fefefe',
  'theme_color' => $site->data('color_primary'),
  'description' => 'A group Bible reading applciation for '.$site->data('site_name').'.',
  'icons' => [
     [
      'src' => 'img/static/logo_'.$site->ID.'_120x120.png?t='.time(),
      'sizes' => '120x120',
      'type' => 'image/png',
    ],
     [
      'src' => 'img/static/logo_'.$site->ID.'_180x180.png?t='.time(),
      'sizes' => '180x180',
      'type' => 'image/png',
    ],
     [
      'src' => 'img/static/logo_'.$site->ID.'_192x192.png?t='.time(),
      'sizes' => '192x192',
      'type' => 'image/png',
    ],
     [
      'src' => 'img/static/logo_'.$site->ID.'_512x512.png?t='.time(),
      'sizes' => '512x512',
      'type' => 'image/png',
    ],
  ],
  'prefer_related_application' => false
]);