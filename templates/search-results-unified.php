<?php
/**
 * /templates/search-results-unified.php
 * Template de Busca com Layout 2 Colunas (Resultados + Sidebar)
 */

if (!defined('TEMPLATE_PATH')) { exit; }

$e = static function (?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$language_code  = $language_code ?? ($_SESSION['language'] ?? 'pt-br');
$term           = isset($term) ? (string)$term : '';
$totalResults   = isset($totalResults) ? (int)$totalResults : 0;
$currentPage    = isset($currentPage) ? max(1, (int)$currentPage) : 1;
$totalPages     = isset($totalPages) ? max(1, (int)$totalPages) : 1;
$activeTab      = isset($activeTab) ? (string)$activeTab : 'all';

$totalProviders = isset($totalProviders) ? (int)$totalProviders : 0;
$totalCompanies = isset($totalCompanies) ? (int)$totalCompanies : 0;
$totalClubs = isset($totalClubs) ? (int)$totalClubs : 0;
$totalServices = isset($totalServices) ? (int)$totalServices : 0;

$providersResults = (isset($providersResults) && is_array($providersResults)) ? $providersResults : [];
$companiesResults = (isset($companiesResults) && is_array($companiesResults)) ? $companiesResults : [];
$clubsResults = (isset($clubsResults) && is_array($clubsResults)) ? $clubsResults : [];
$servicesResults = (isset($servicesResults) && is_array($servicesResults)) ? $servicesResults : [];

$t_placeholder = $translations['search_placeholder'] ?? 'Pesquisar...';
$t_no_results  = $translations['no_results'] ?? 'Nenhum resultado encontrado.';

$searchAction = rtrim(SITE_URL ?? '', '/') . '/search.php';

$snippet = static function (?string $text, int $max = 150): string {
  $text = (string)($text ?? '');
  if ($text === '') return '';
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max, 'UTF-8') . '…' : $text;
  }
  return (strlen($text) > $max) ? substr($text, 0, $max) . '…' : $text;
};
?>

<main class="main-content search-page-layout">
  
  <!-- Barra de Busca -->
  <div class="search-bar-section">
    <div class="container">
      <form method="GET" action="<?php echo $e($searchAction); ?>" class="search-form">
        <div class="search-input-wrapper">
          <input type="text" name="term" value="<?php echo $e($term); ?>" 
                 placeholder="<?php echo $e($t_placeholder); ?>" class="search-input" autocomplete="on">
          <button type="submit" class="search-btn">Buscar</button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!empty($term)): ?>
    
    <!-- Abas de Navegação -->
    <div class="search-tabs-section">
      <div class="container">
        <nav class="category-tabs">
          <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=all'); ?>" 
             class="tab <?php echo $activeTab === 'all' ? 'active' : ''; ?>">
            Todos <span class="count">(<?php echo $totalResults; ?>)</span>
          </a>
          <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=providers'); ?>" 
             class="tab <?php echo $activeTab === 'providers' ? 'active' : ''; ?>">
            Acompanhantes <span class="count">(<?php echo $totalProviders; ?>)</span>
          </a>
          <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=clubs'); ?>" 
             class="tab <?php echo $activeTab === 'clubs' ? 'active' : ''; ?>">
            Clubes <span class="count">(<?php echo $totalClubs; ?>)</span>
          </a>
          <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=companies'); ?>" 
             class="tab <?php echo $activeTab === 'companies' ? 'active' : ''; ?>">
            Empresas <span class="count">(<?php echo $totalCompanies; ?>)</span>
          </a>
          <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=services'); ?>" 
             class="tab <?php echo $activeTab === 'services' ? 'active' : ''; ?>">
            Serviços <span class="count">(<?php echo $totalServices; ?>)</span>
          </a>
        </nav>
      </div>
    </div>

    <!-- Layout Principal: 2 Colunas -->
    <div class="search-content-section">
      <div class="container">
        <div class="search-grid-layout">
          
          <!-- COLUNA ESQUERDA: Lista de Resultados -->
          <div class="results-column">
            
            <?php if ($totalResults > 0): ?>
              
              <!-- Resultados PROVIDERS -->
              <?php if (!empty($providersResults)): ?>
                <div class="results-group">
                  <?php if ($activeTab === 'all'): ?>
                    <h2 class="group-title">
                      Acompanhantes
                      <?php if ($totalProviders > count($providersResults)): ?>
                        <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=providers'); ?>" class="view-all">
                          Ver todos (<?php echo $totalProviders; ?>)
                        </a>
                      <?php endif; ?>
                    </h2>
                  <?php endif; ?>
                  
                  <?php foreach ($providersResults as $provider): 
                    $id = $provider['id'] ?? '';
                    $name = $provider['display_name'] ?? '';
                    $gender = $provider['gender'] ?? '';
                    $country = $provider['country'] ?? '';
                    $url = rtrim(SITE_URL, '/') . '/provider_profile.php?id=' . $id;
                  ?>
                    <div class="result-item">
                      <div class="result-icon">
                        
                      </div>
                      <div class="result-content">
                        <h3 class="result-title">
                          <a href="<?php echo $e($url); ?>"><?php echo $e($name); ?></a>
                        </h3>
                        <div class="result-meta">
                          <?php if ($gender): ?>
                            <span class="meta-item"><?php echo $e(ucfirst($gender)); ?></span>
                          <?php endif; ?>
                          <?php if ($country): ?>
                            <span class="meta-item"><?php echo $e($country); ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="result-action">
                        <a href="<?php echo $e($url); ?>" class="btn-view">Ver Perfil</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Resultados CLUBS -->
              <?php if (!empty($clubsResults)): ?>
                <div class="results-group">
                  <?php if ($activeTab === 'all'): ?>
                    <h2 class="group-title">
                      Clubes
                      <?php if ($totalClubs > count($clubsResults)): ?>
                        <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=clubs'); ?>" class="view-all">
                          Ver todos (<?php echo $totalClubs; ?>)
                        </a>
                      <?php endif; ?>
                    </h2>
                  <?php endif; ?>
                  
                  <?php foreach ($clubsResults as $club): 
                    $id = $club['id'] ?? '';
                    $name = $club['display_name'] ?? '';
                    $desc = $snippet($club['description'] ?? '', 100);
                    $url = rtrim(SITE_URL, '/') . '/club_profile.php?id=' . $id;
                  ?>
                    <div class="result-item">
                      <div class="result-icon">
                        
                      </div>
                      <div class="result-content">
                        <h3 class="result-title">
                          <a href="<?php echo $e($url); ?>"><?php echo $e($name); ?></a>
                        </h3>
                        <?php if ($desc): ?>
                          <p class="result-description"><?php echo $e($desc); ?></p>
                        <?php endif; ?>
                      </div>
                      <div class="result-action">
                        <a href="<?php echo $e($url); ?>" class="btn-view">Ver Detalhes</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Resultados COMPANIES -->
              <?php if (!empty($companiesResults)): ?>
                <div class="results-group">
                  <?php if ($activeTab === 'all'): ?>
                    <h2 class="group-title">
                      Empresas
                      <?php if ($totalCompanies > count($companiesResults)): ?>
                        <a href="<?php echo $e($searchAction . '?term=' . urlencode($term) . '&tab=companies'); ?>" class="view-all">
                          Ver todos (<?php echo $totalCompanies; ?>)
                        </a>
                      <?php endif; ?>
                    </h2>
                  <?php endif; ?>
                  
                  <?php foreach ($companiesResults as $company): 
                    $id = $company['id'] ?? '';
                    $name = $company['display_name'] ?? '';
                    $desc = $snippet($company['description'] ?? '', 100);
                    $url = rtrim(SITE_URL, '/') . '/company_profile.php?id=' . $id;
                  ?>
                    <div class="result-item">
                      <div class="result-icon">
                        
                      </div>
                      <div class="result-content">
                        <h3 class="result-title">
                          <a href="<?php echo $e($url); ?>"><?php echo $e($name); ?></a>
                        </h3>
                        <?php if ($desc): ?>
                          <p class="result-description"><?php echo $e($desc); ?></p>
                        <?php endif; ?>
                      </div>
                      <div class="result-action">
                        <a href="<?php echo $e($url); ?>" class="btn-view">Ver Empresa</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

            <?php else: ?>
              
              <!-- Sem Resultados -->
              <div class="no-results">
                
                <h3><?php echo $e($t_no_results); ?></h3>
                <p>Tente usar palavras-chave diferentes ou mais genéricas</p>
              </div>
              
            <?php endif; ?>
            
          </div>

          <!-- COLUNA DIREITA: Sidebar -->
          <aside class="sidebar-column">
            
            <!-- Filtros -->
            <div class="sidebar-widget">
              <h3 class="widget-title">
                Filtros
              </h3>
              <div class="widget-content">
                <p class="text-muted">Em breve: filtros avançados</p>
              </div>
            </div>

            <!-- Clubes Destacados -->
            <div class="sidebar-widget">
              <h3 class="widget-title">
                Clubes
              </h3>
              <div class="widget-content">
                <div class="sidebar-card">
                  <div class="card-placeholder">
                    
                  </div>
                  <p class="card-text">Anúncios em breve</p>
                </div>
              </div>
            </div>

            <!-- Anúncios -->
            <div class="sidebar-widget">
              <h3 class="widget-title">
                Anúncios
              </h3>
              <div class="widget-content">
                <div class="sidebar-card">
                  <div class="card-placeholder">
                    
                  </div>
                  <p class="card-text">Espaço publicitário</p>
                </div>
              </div>
            </div>

            <!-- Empresas Destacadas -->
            <div class="sidebar-widget">
              <h3 class="widget-title">
                Empresas
              </h3>
              <div class="widget-content">
                <div class="sidebar-card">
                  <div class="card-placeholder">
                    
                  </div>
                  <p class="card-text">Empresas em destaque</p>
                </div>
              </div>
            </div>
            
          </aside>
          
        </div>
      </div>
    </div>

  <?php endif; ?>
  
</main>
