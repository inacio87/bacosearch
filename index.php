<?php
// Home – estética SPLove + paleta antiga Baco (fundo branco)
$page_name = 'home';
$page_title = 'BacoSearch - Home';
require_once __DIR__ . '/core/bootstrap.php';

// Exemplo: você pode trocar por uma query real (ex.: getFeaturedModels())
$models = [
  [
    'name' => 'Clara Meier',
    'slug' => 'clara-meier',
    'thumb' => '/storage/imgthumb/ee043df9bfbd1b2a03e1ca638dfa4643.jpg',
    'full'  => '/storage/img/ee043df9bfbd1b2a03e1ca638dfa4643.jpg',
    'neighborhood' => 'Moema',
    'price' => 1000,
    'period' => '1h',
    'whatsapp' => '5551998989919'
  ],
  // ...popule via DB
];

include __DIR__ . '/templates/head.php';
?>

<?php include __DIR__ . '/templates/header.php'; ?>

<main class="baco-container">

  <!-- HERO + BUSCA RÁPIDA -->
  <section class="hero">
    <div class="hero__inner">
      <h1 class="hero__title">BacoSearch</h1>
      <p class="hero__subtitle">Encontre modelos verificadas, filtros por cidade, bairro e categorias.</p>

      <form class="filterbar" method="get" action="/explore">
        <input class="filterbar__input" type="search" name="q" placeholder="Buscar modelo, bairro, categoria..." />
        <select class="filterbar__select" name="city">
          <option value="">Cidade</option>
          <option>Maceió</option>
          <option>São Paulo</option>
          <option>Rio de Janeiro</option>
        </select>
        <select class="filterbar__select" name="category">
          <option value="">Categoria</option>
          <option>Loiras</option>
          <option>Morenas</option>
          <option>Massagistas</option>
          <option>Cam Girls</option>
        </select>
        <button class="btn btn-primary" type="submit">Explorar</button>
      </form>
    </div>
  </section>

  <!-- GRADE DE MODELOS (estética SPLove, mas clean no branco) -->
  <section class="gridwrap">
    <div class="grid">
      <?php foreach ($models as $m): ?>
        <article class="card">
          <a class="card__thumb glightbox" href="<?= e($m['full']) ?>" data-gallery="home">
            <img src="<?= e($m['thumb']) ?>" alt="<?= e($m['name']) ?>" loading="lazy">
          </a>

          <div class="card__body">
            <h3 class="card__title">
              <a href="/<?= e($m['slug']) ?>"><?= e($m['name']) ?></a>
            </h3>
            <div class="card__meta">
              <span class="chip"><?= e($m['neighborhood']) ?></span>
              <?php if (!empty($m['price'])): ?>
                <span class="chip chip--alt">R$ <?= number_format($m['price'],0,',','.') ?>/<?= e($m['period']) ?></span>
              <?php endif; ?>
            </div>
            <div class="card__actions">
              <a class="btn btn-outline" href="/<?= e($m['slug']) ?>">Ver perfil</a>
              <?php if (!empty($m['whatsapp'])): ?>
                <a class="btn btn-whats"
                   target="_blank"
                   href="https://wa.me/<?= preg_replace('/\D/','',$m['whatsapp']) ?>?text=Ol%C3%A1%2C%20te%20vi%20no%20BacoSearch!">
                  WhatsApp
                </a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- CHAMADA PARA CRIAR CONTA / ANUNCIAR -->
  <section class="cta">
    <div class="cta__box">
      <h2>Quer anunciar no BacoSearch?</h2>
      <p>Fotos com verificação, stories, vídeos, link direto para WhatsApp e filtros por região.</p>
      <a class="btn btn-primary" href="/register">Criar conta</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/templates/footer.php'; ?>
