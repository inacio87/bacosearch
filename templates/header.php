<?php
$siteName = defined('APP_NAME') ? APP_NAME : (env('APP_NAME', 'BacoSearch'));
?>
<header>
  <div class="container" style="display:flex;align-items:center;gap:12px">
    <div class="logo">
      <img src="<?= htmlspecialchars(SITE_URL . '/assets/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
    </div>
    <strong><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></strong>
  </div>
</header>
