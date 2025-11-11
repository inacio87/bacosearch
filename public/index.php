<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Bacosearch - Início</title>
  <meta name="description" content="Bacosearch - Plataforma de descoberta de perfis." />
  <link rel="icon" type="image/png" href="/favicon.png" />
  <!-- Tailwind CDN para protótipo (pode ser substituído por build local depois) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="/assets/css/base.css" />
  <link rel="stylesheet" href="https://unpkg.com/swiper@9/swiper-bundle.min.css" />
</head>
<body class="bg-bg-primary text-text-primary font-sans">
<header class="border-b border-border flex items-center justify-between px-4 h-16 bg-bg-header">
  <div class="flex items-center gap-6">
    <a href="/" class="text-xl font-semibold text-accent">Bacosearch</a>
    <nav class="hidden md:flex gap-4 text-sm">
      <button data-modal="search" class="hover:text-white text-text-muted">Buscar</button>
      <div class="relative group">
        <button class="hover:text-white">Bairros ▾</button>
        <div class="absolute left-0 top-full mt-2 w-48 bg-bg-tertiary border border-border rounded shadow-lg opacity-0 group-hover:opacity-100 transition p-2 z-20">
          <ul class="space-y-1 text-sm">
            <li><a href="#" class="dropdown-link">Moema</a></li>
            <li><a href="#" class="dropdown-link">Jardins</a></li>
            <li><a href="#" class="dropdown-link">Itaim Bibi</a></li>
            <li><a href="#" class="dropdown-link">Pinheiros</a></li>
          </ul>
        </div>
      </div>
      <div class="relative group">
        <button class="hover:text-white">Categorias ▾</button>
        <div class="absolute left-0 top-full mt-2 w-48 bg-bg-tertiary border border-border rounded shadow-lg opacity-0 group-hover:opacity-100 transition p-2 z-20">
          <ul class="space-y-1 text-sm">
            <li><a href="#" class="dropdown-link">Loiras</a></li>
            <li><a href="#" class="dropdown-link">Morenas</a></li>
            <li><a href="#" class="dropdown-link">Orientais</a></li>
            <li><a href="#" class="dropdown-link">Tatuadas</a></li>
          </ul>
        </div>
      </div>
      <a href="#" class="hover:text-white">Filtro</a>
      <a href="#" class="hover:text-white">Contato</a>
    </nav>
  </div>
  <div class="flex items-center gap-4">
    <button data-modal="login" class="text-sm px-3 py-2 rounded bg-bg-tertiary border border-border hover:border-accent hover:text-white">Login</button>
    <button class="md:hidden" id="btn-mobile" aria-label="Menu mobile">
      <span class="block w-6 h-px bg-text-primary mb-1"></span>
      <span class="block w-6 h-px bg-text-primary mb-1"></span>
      <span class="block w-6 h-px bg-text-primary"></span>
    </button>
  </div>
</header>

<!-- Menu Mobile -->
<div id="mobile-menu" class="hidden flex-col bg-bg-secondary border-b border-border px-4 py-3 space-y-2 md:hidden">
  <a href="#" class="mobile-link">Bairros</a>
  <a href="#" class="mobile-link">Categorias</a>
  <a href="#" class="mobile-link">Filtro</a>
  <a href="#" class="mobile-link">Contato</a>
</div>

<main class="max-w-7xl mx-auto px-4 py-8">
  <h1 class="text-2xl font-semibold mb-6">Novidades</h1>

  <!-- Swiper placeholder -->
  <div class="swiper mySwiper bg-bg-tertiary rounded border border-border">
    <div class="swiper-wrapper">
      <div class="swiper-slide p-4 flex flex-col items-center">
        <div class="w-40 h-56 bg-bg-secondary border border-border rounded mb-2 flex items-center justify-center text-text-muted text-xs">Foto</div>
        <span class="text-sm">Perfil Exemplo</span>
      </div>
      <div class="swiper-slide p-4 flex flex-col items-center">
        <div class="w-40 h-56 bg-bg-secondary border border-border rounded mb-2 flex items-center justify-center text-text-muted text-xs">Foto</div>
        <span class="text-sm">Outro Perfil</span>
      </div>
      <div class="swiper-slide p-4 flex flex-col items-center">
        <div class="w-40 h-56 bg-bg-secondary border border-border rounded mb-2 flex items-center justify-center text-text-muted text-xs">Foto</div>
        <span class="text-sm">Mais Um</span>
      </div>
    </div>
    <div class="swiper-pagination"></div>
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
  </div>

  <section class="mt-10">
    <h2 class="text-xl mb-4">Grid de Perfis (placeholder)</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
      <?php for($i=1;$i<=8;$i++): ?>
        <article class="bg-bg-tertiary border border-border rounded p-3 flex flex-col">
          <div class="h-40 bg-bg-secondary border border-border rounded mb-2 flex items-center justify-center text-text-muted text-xs">Imagem</div>
          <h3 class="text-sm font-medium mb-1">Perfil <?php echo $i; ?></h3>
          <p class="text-xs text-text-muted">Resumo curto do perfil.</p>
      <a href="/profile.php?perfil=<?php echo $i; ?>" class="mt-auto text-accent text-xs hover:underline">Ver perfil</a>
        </article>
      <?php endfor; ?>
    </div>
  </section>
</main>

<!-- Modais simples -->
<div id="modal-login" class="modal hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4">
  <div class="bg-bg-tertiary w-full max-w-sm p-6 rounded border border-border">
    <h2 class="text-lg font-semibold mb-4">Login</h2>
    <form>
      <label class="block text-sm mb-2">Email
        <input type="email" class="input" placeholder="voce@exemplo.com" />
      </label>
      <label class="block text-sm mb-4">Senha
        <input type="password" class="input" placeholder="••••••" />
      </label>
      <button type="submit" class="w-full py-2 bg-accent text-white rounded text-sm font-medium">Entrar</button>
    </form>
    <button class="mt-4 text-xs text-text-muted hover:text-white" data-close="login">Fechar</button>
  </div>
</div>
<div id="modal-search" class="modal hidden fixed inset-0 bg-black/70 flex items-center justify-center p-4">
  <div class="bg-bg-tertiary w-full max-w-md p-6 rounded border border-border">
    <h2 class="text-lg font-semibold mb-4">Buscar Perfil</h2>
    <input type="text" class="input w-full mb-4" placeholder="Digite o nome..." />
    <button class="text-xs text-text-muted hover:text-white" data-close="search">Fechar</button>
  </div>
</div>

<footer class="mt-12 border-t border-border py-6 text-center text-xs text-text-muted">
  © <?php echo date('Y'); ?> Bacosearch. Conteúdo de exemplo e estrutura inicial. Em evolução.
</footer>

<script src="https://unpkg.com/swiper@9/swiper-bundle.min.js"></script>
<script src="/assets/js/home.js"></script>
<script src="/assets/js/main.js"></script>
</body>
</html>
