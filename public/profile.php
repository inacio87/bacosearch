<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Bacosearch - Perfil</title>
  <link rel="icon" type="image/png" href="/favicon.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/base.css" />
  <link rel="stylesheet" href="https://unpkg.com/glightbox/dist/css/glightbox.min.css" />
</head>
<body class="bg-bg-primary text-text-primary font-sans">
<header class="border-b border-border flex items-center justify-between px-4 h-16 bg-bg-header">
  <a href="/index.php" class="text-xl font-semibold text-accent">Bacosearch</a>
  <nav class="hidden md:flex gap-4 text-sm">
    <a href="/public/index.php" class="hover:text-white">Início</a>
    <a href="#" class="hover:text-white">Explorar</a>
  </nav>
</header>

<main class="max-w-6xl mx-auto px-4 py-8">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <section>
      <div class="grid grid-cols-3 gap-3" id="gallery">
        <a href="https://picsum.photos/id/1011/1000/1400" class="glightbox" data-gallery="perfil"><img src="https://picsum.photos/id/1011/300/420" class="rounded border border-border" /></a>
        <a href="https://picsum.photos/id/1015/1000/1400" class="glightbox" data-gallery="perfil"><img src="https://picsum.photos/id/1015/300/420" class="rounded border border-border" /></a>
        <a href="https://picsum.photos/id/1024/1000/1400" class="glightbox" data-gallery="perfil"><img src="https://picsum.photos/id/1024/300/420" class="rounded border border-border" /></a>
        <a href="https://picsum.photos/id/1021/1000/1400" class="glightbox" data-gallery="perfil"><img src="https://picsum.photos/id/1021/300/420" class="rounded border border-border" /></a>
        <a href="https://picsum.photos/id/1027/1000/1400" class="glightbox" data-gallery="perfil"><img src="https://picsum.photos/id/1027/300/420" class="rounded border border-border" /></a>
        <a href="https://picsum.photos/id/1031/1000/1400" class="glightbox" data-gallery="perfil"><img src="https://picsum.photos/id/1031/300/420" class="rounded border border-border" /></a>
      </div>
    </section>
    <section>
      <h1 class="text-2xl font-semibold mb-2">Perfil Exemplo</h1>
      <p class="text-sm text-text-muted mb-4">Descrição resumida do perfil. Este é um template de galeria usando GLightbox.</p>
      <ul class="text-sm space-y-1">
        <li><strong>Bairro:</strong> Pinheiros</li>
        <li><strong>Categoria:</strong> Morenas</li>
        <li><strong>Telefone:</strong> (11) 99999-9999</li>
      </ul>
    </section>
  </div>
</main>

<footer class="mt-12 border-t border-border py-6 text-center text-xs text-text-muted">
  © <?php echo date('Y'); ?> Bacosearch. Perfil de exemplo.
</footer>

<script src="https://unpkg.com/glightbox/dist/js/glightbox.min.js"></script>
<script src="/assets/js/profile.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
