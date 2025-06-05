<?php global $add_to_foot, $page_title, $me, $insecure, $use_template; ?>
  <?php if (($me && !$insecure) || $use_template): ?>
    <hr>
    <?= navigation() ?>
  <?php endif; ?>
  <?= $add_to_foot ?>
  <script data-goatcounter="https://abe-brc.goatcounter.com/count" async src="//gc.zgo.at/count.js"></script>
  <noscript>
    <?php
      // https://www.goatcounter.com/help/pixel
      echo "<img src='https://abe-brc.goatcounter.com/count?".
        "p=".parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH).
        "&t=".$page_title.
        "&r=".$_SERVER['HTTP_REFERER']
        ."'>";
    ?>
  </noscript>
  <!-- Request took <?= number_format(1_000*(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']), 1) ?>ms -->
  </body>
</html>
