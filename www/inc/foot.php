  <?php if ($me && !$insecure): ?>
    <hr>
    <?= navigation() ?>
  <?php endif; ?>
  <?= $add_to_foot ?>

  <!-- Request took <?= number_format(1_000*(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']), 1) ?>ms -->
  </body>
</html>