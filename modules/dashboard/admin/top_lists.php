<?php
/**
 * /modules/dashboard/admin/top_lists.php - Módulo "Top 10"
 * - 7 painéis: Countries, Cities, Pages, Referrers, Devices, Searches, Exits
 * - Usa /assets/css/dashboard.css e garante quebra de linha em nomes longos
 * - Chama /api/api_top10.php por métrica (GET): action=top10&metric=...&period=...
 */

if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// Idioma
$languageCode = $languageCode ?? ($_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us'));

// Traduções necessárias (contexto admin_dashboard)
$keys = [
  'top_10_title','countries','cities','pages','referrers','devices','searches','exits',
  'loading_data','no_data_found','network_error','api_error',
  'filter_today','filter_7_days','filter_30_days','filter_360_days'
];
$T = [];
foreach ($keys as $k) {
  $tk = ($k === 'searches') ? 'keywords' : $k;
  $T[$k] = getTranslation($tk, $languageCode, 'admin_dashboard');
}
?>
<!-- CSS principal do dashboard (conforme pedido) -->
<link rel="stylesheet" href="<?= htmlspecialchars(SITE_URL . '/assets/css/dashboard.css', ENT_QUOTES, 'UTF-8'); ?>"/>

<div class="dashboard-module-wrapper">
  <div class="module-header">
    <h1><?= htmlspecialchars($T['top_10_title'] ?: 'Top 10'); ?></h1>
  </div>

  <div class="time-filters">
    <button class="btn time-filter-btn active" data-period="today"><?= htmlspecialchars($T['filter_today'] ?: 'Hoje'); ?></button>
    <button class="btn time-filter-btn" data-period="7d"><?= htmlspecialchars($T['filter_7_days'] ?: '7 Dias'); ?></button>
    <button class="btn time-filter-btn" data-period="30d"><?= htmlspecialchars($T['filter_30_days'] ?: '30 Dias'); ?></button>
    <button class="btn time-filter-btn" data-period="360d"><?= htmlspecialchars($T['filter_360_days'] ?: '360 Dias'); ?></button>
  </div>

  <div id="top-lists-grid" class="top-lists-grid grid-3">
    <?php
      $blocks = [
        ['id'=>'countries', 'title'=>$T['countries'] ?: 'Countries'],
        ['id'=>'cities',    'title'=>$T['cities']    ?: 'Cities'],
        ['id'=>'pages',     'title'=>$T['pages']     ?: 'Pages'],
        ['id'=>'referrers', 'title'=>$T['referrers'] ?: 'Referrers'],
        ['id'=>'devices',   'title'=>$T['devices']   ?: 'Devices'],
        ['id'=>'searches',  'title'=>$T['searches']  ?: 'Searches'],
        ['id'=>'exits',     'title'=>$T['exits']     ?: 'Exits'],
      ];
      foreach ($blocks as $b):
    ?>
      <div class="top-list-container panel" id="panel-<?= htmlspecialchars($b['id']); ?>">
        <div class="panel-header">
          <h3><?= htmlspecialchars($b['title']); ?></h3>
        </div>
        <div class="panel-body">
          <div class="state state-loading"><?= htmlspecialchars($T['loading_data'] ?: 'A carregar dados...'); ?></div>
          <div class="state state-empty" style="display:none;"><?= htmlspecialchars($T['no_data_found'] ?: 'Nenhum dado encontrado.'); ?></div>
          <div class="state state-error" style="display:none;"><?= htmlspecialchars($T['network_error'] ?: 'Erro de rede.'); ?></div>
          <ol class="list" style="display:none;"></ol>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function(){
  const SITE_URL = "<?= rtrim(SITE_URL, '/'); ?>";
  const API = SITE_URL + "/api/api_top10.php";
  const i18n = <?= json_encode($T, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const metrics = ['countries','cities','pages','referrers','devices','searches','exits'];
  let currentPeriod = 'today';

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text == null ? '' : String(text)));
    return div.innerHTML;
  }

  function setState(panel, state){
    panel.querySelector('.state-loading').style.display = (state==='loading')?'':'none';
    panel.querySelector('.state-empty').style.display   = (state==='empty')  ?'':'none';
    panel.querySelector('.state-error').style.display   = (state==='error')  ?'':'none';
    panel.querySelector('.list').style.display          = (state==='list')   ?'':'none';
  }

  async function fetchMetric(metric, period){
    const url = `${API}?action=top10&metric=${encodeURIComponent(metric)}&period=${encodeURIComponent(period)}`;
    const resp = await fetch(url, { method: 'GET' });
    if (!resp.ok) {
      const txt = await resp.text().catch(()=> '');
      throw new Error(`HTTP ${resp.status} :: ${txt}`);
    }
    const json = await resp.json();
    if (!json || json.success !== true) {
      throw new Error(json?.message || (i18n.api_error || 'Erro na API'));
    }
    const arr = Array.isArray(json.data) ? json.data : [];
    // Normaliza para {label, count}; API já manda label=nome completo do país no countries
    return arr.map(r => ({ label: (r.label ?? '—'), count: (r.count ?? r.cnt ?? 0) }));
  }

  function renderList(panel, items, metric){
    const list = panel.querySelector('.list');
    list.innerHTML = '';

    if (!items || !items.length) {
      setState(panel, 'empty');
      return;
    }

    const isExits = (metric === 'exits');
    const MAX_LEN = 70;

    list.innerHTML = items.slice(0, 10).map((row, i) => {
      const raw = String(row.label ?? '—');
      const cnt = row.count ?? 0;

      if (isExits && /^https?:\/\//i.test(raw)) {
        // Encurta visualmente URL no texto, mantendo href original
        let disp = raw;
        if (disp.length > MAX_LEN) {
          const start = disp.slice(0, Math.floor(MAX_LEN/2) - 2);
          const end   = disp.slice(-Math.floor(MAX_LEN/2) + 2);
          disp = `${start}...${end}`;
        }
        return `<li class="list-item">
          <span class="rank">${i+1}.</span>
          <span class="item-name"><a href="${escapeHtml(raw)}" target="_blank" rel="noopener noreferrer">${escapeHtml(disp)}</a></span>
          <span class="item-count">${cnt}</span>
        </li>`;
      }

      // Demais métricas: só texto (países virão com nome completo)
      return `<li class="list-item">
        <span class="rank">${i+1}.</span>
        <span class="item-name">${escapeHtml(raw)}</span>
        <span class="item-count">${cnt}</span>
      </li>`;
    }).join('');

    setState(panel, 'list');
  }

  async function loadBlock(metric){
    const panel = document.getElementById('panel-' + metric);
    if (!panel) return;
    setState(panel, 'loading');
    try {
      const data = await fetchMetric(metric, currentPeriod);
      renderList(panel, data, metric);
    } catch (err) {
      console.error('Top10 Error ['+metric+']:', err);
      setState(panel, 'error');
      const errDiv = panel.querySelector('.state-error');
      if (errDiv) errDiv.textContent = i18n.network_error || 'Erro de rede.';
    }
  }

  async function refreshAll(){
    await Promise.all(metrics.map(loadBlock));
  }

  document.querySelectorAll('.time-filter-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('.time-filter-btn').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      currentPeriod = btn.getAttribute('data-period') || 'today';
      refreshAll();
    });
  });

  // Inicial
  refreshAll();
})();
</script>

<!-- Fallback leve para garantir quebra de linha em nomes grandes (mantém teu dashboard.css como principal) -->
<style>
  .top-list-container .item-name {
    white-space: normal;
    word-break: break-word;
  }
  .top-list-container .list-item {
    display:flex; align-items:center; gap:.5rem; padding:.25rem 0;
    border-bottom:1px dashed rgba(255,255,255,.06);
  }
  .top-list-container .list-item:last-child { border-bottom:0; }
  .top-list-container .rank { width:2.2ch; opacity:.65; }
  .top-list-container .item-count { font-variant-numeric: tabular-nums; opacity:.9; }
</style>
