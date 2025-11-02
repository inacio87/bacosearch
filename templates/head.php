<?php
// Minimal head template
$title = isset($page_title) ? $page_title : 'BacoSearch';
$desc  = isset($meta_description) ? $meta_description : (SEO_CONFIG['meta_description'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <?php if (!empty($desc)): ?>
    <meta name="description" content="<?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
  <style>
    body{font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;margin:0;color:#1b1b1b;background:#fafafa}
    header,footer{background:#111;color:#fff;padding:12px 16px}
    .container{max-width:980px;margin:0 auto;padding:16px}
    .search-container{display:flex;gap:8px;margin-top:16px}
    .search-container input{flex:1;padding:10px;border:1px solid #ccc;border-radius:6px}
    .search-container .search-button{padding:10px 14px;border:0;border-radius:6px;background:#111;color:#fff;cursor:pointer}
    .banners-homepage img{max-width:100%;height:auto;display:block}
    .logo img{height:48px}
  </style>
</head>
<body>
<?php /* head.php ends here; header.php should follow */ ?>
