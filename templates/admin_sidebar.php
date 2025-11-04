<?php
/**
 * /templates/admin_sidebar.php
 * Barra de Navegação Lateral do Painel Admin (produção, 100% traduzido)
 * Última atualização: 15/08/2025
 */

if (!defined('SITE_URL')) { exit; }

$languageCode  = $_SESSION['language'] ?? 'pt-br';
$currentModule = isset($module_name) ? (string)$module_name : '';

/** Helper de escape */
$e = static function (?string $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};

/** Traduções com fallback mínimo */
$titleAdmin        = getTranslation('admin_title', $languageCode, 'admin_sidebar') ?? 'Admin';
$welcomeTemplate   = getTranslation('welcome_message', $languageCode, 'admin_sidebar') ?? 'Bem-vindo, %s';
$adminNameFallback = $titleAdmin;
$adminDisplayName  = isset($admin_name) && $admin_name !== '' ? (string)$admin_name : (string)$adminNameFallback;
/* Importante: aplicamos sprintf no template cru e só depois escapamos o resultado */
$welcomeFinal      = sprintf($welcomeTemplate, $adminDisplayName);

/** Utilitário para classe ativa e aria-current */
$isActive = static function (string $module) use ($currentModule): bool {
  return $currentModule === $module;
};
$activeClass = static function (bool $active): string {
  return $active ? 'active' : '';
};
$ariaCurrent = static function (bool $active): string {
  return $active ? ' aria-current="page"' : '';
};

?>
<aside class="dashboard-sidebar">
  <div class="sidebar-header">
    <h2 class="sidebar-title"><?php echo $e($titleAdmin); ?></h2>
    <p class="sidebar-welcome"><?php echo $e($welcomeFinal); ?></p>
  </div>

  <nav class="sidebar-nav" role="navigation" aria-label="<?php echo $e($titleAdmin); ?>">
    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=stats"
       class="<?php echo $e($activeClass($isActive('stats'))); ?>"<?php echo $ariaCurrent($isActive('stats')); ?>>
      <i class="fas fa-chart-line" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_stats', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=top_lists"
       class="<?php echo $e($activeClass($isActive('top_lists'))); ?>"<?php echo $ariaCurrent($isActive('top_lists')); ?>>
      <i class="fas fa-trophy" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_top_lists', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=users"
       class="<?php echo $e($activeClass($isActive('users'))); ?>"<?php echo $ariaCurrent($isActive('users')); ?>>
      <i class="fas fa-users" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_users', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=providers"
       class="<?php echo $e($activeClass($isActive('providers'))); ?>"<?php echo $ariaCurrent($isActive('providers')); ?>>
      <i class="fas fa-user-tie" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_providers', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=businesses"
       class="<?php echo $e($activeClass($isActive('businesses'))); ?>"<?php echo $ariaCurrent($isActive('businesses')); ?>>
      <i class="fas fa-building" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('footer_companies', $languageCode, 'footer')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=clubs"
       class="<?php echo $e($activeClass($isActive('clubs'))); ?>"<?php echo $ariaCurrent($isActive('clubs')); ?>>
      <i class="fas fa-glass-cheers" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('footer_clubs', $languageCode, 'footer')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=ads_management"
       class="<?php echo $e($activeClass($isActive('ads_management'))); ?>"<?php echo $ariaCurrent($isActive('ads_management')); ?>>
      <i class="fas fa-ad" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_ads', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=services"
       class="<?php echo $e($activeClass($isActive('services'))); ?>"<?php echo $ariaCurrent($isActive('services')); ?>>
      <i class="fas fa-concierge-bell" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('footer_services', $languageCode, 'footer')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=streets"
       class="<?php echo $e($activeClass($isActive('streets'))); ?>"<?php echo $ariaCurrent($isActive('streets')); ?>>
      <i class="fas fa-road" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('footer_streets', $languageCode, 'footer')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=translations"
       class="<?php echo $e($activeClass($isActive('translations'))); ?>"<?php echo $ariaCurrent($isActive('translations')); ?>>
      <i class="fas fa-language" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_translations', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/dashboard.php?module=system_logs"
       class="<?php echo $e($activeClass($isActive('system_logs'))); ?>"<?php echo $ariaCurrent($isActive('system_logs')); ?>>
      <i class="fas fa-file-alt" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_logs', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <hr>
    <h3 class="sidebar-subtitle"><?php echo $e(getTranslation('sidebar_dev_tools', $languageCode, 'admin_sidebar')); ?></h3>

    <a href="<?php echo $e(SITE_URL); ?>/admin/docs/analyze_orphans.php" target="_blank" rel="noopener noreferrer">
      <i class="fas fa-link" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_dev_orphans', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/docs/list_no_extension_files.php" target="_blank" rel="noopener noreferrer">
      <i class="fas fa-file-excel" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_dev_no_ext', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/docs/all_geo_options_apis.php" target="_blank" rel="noopener noreferrer">
      <i class="fas fa-map-marked-alt" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_dev_geo_test', $languageCode, 'admin_sidebar')); ?></span>
    </a>

    <a href="<?php echo $e(SITE_URL); ?>/admin/docs/generate_structure.php" target="_blank" rel="noopener noreferrer">
      <i class="fas fa-sitemap" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('sidebar_dev_structure', $languageCode, 'admin_sidebar')); ?></span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?php echo $e(SITE_URL); ?>/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
      <span class="nav-text"><?php echo $e(getTranslation('header_logout', $languageCode, 'header')); ?></span>
    </a>
  </div>
</aside>
