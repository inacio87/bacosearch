<?php
// head.php - Cabeçalho HTML genérico
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo isset($page_title) ? e($page_title) : 'BacoSearch'; ?></title>
  <meta name="description" content="BacoSearch - Encontre modelos verificadas" />
  <link rel="icon" type="image/png" href="/favicon.png" />
  <link rel="stylesheet" href="/assets/css/baco-legacy.css?v=1.0" />
  <!-- GLightbox CSS via CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" />
</head>
<body>
