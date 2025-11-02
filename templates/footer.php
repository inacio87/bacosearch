<?php
/**
 * /templates/footer.php
 * Rodapé (dinâmico, sem textos literais)
 * Última atualização: 15/08/2025
 */

// Garante helpers de i18n do layout
if (!function_exists('loadFooterTranslations')) {
    require_once dirname(__DIR__) . '/core/i18n_layout.php';
}

$languageCode = $_SESSION['language'] ?? (LANGUAGE_CONFIG['default'] ?? 'en-us');
$languageCode = strtolower(str_replace('_','-',$languageCode));

// Se a página não passou traduções de footer, carrega agora
$footerTx = loadFooterTranslations($languageCode);

if (!isset($translations) || !is_array($translations)) {
    $translations = [];
}
$translations = array_merge($footerTx, $translations);

// Rotas do menu do footer
$footerItems = [
    [
        'href' => SITE_URL . '/pages/results_providers.php',
        'icon' => SITE_URL . '/assets/icons/icon-ads.svg',
        'label_key' => 'footer_providers',
    ],
    [
        'href' => SITE_URL . '/pages/results_clubs.php',
        'icon' => SITE_URL . '/assets/icons/icon-clubs.svg',
        'label_key' => 'footer_clubs',
    ],
    [
        'href' => SITE_URL . '/pages/results_business.php',
        'icon' => SITE_URL . '/assets/icons/icon-companies.svg',
        'label_key' => 'footer_companies',
    ],
    [
        'href' => SITE_URL . '/pages/results_services.php',
        'icon' => SITE_URL . '/assets/icons/icon-services.svg',
        'label_key' => 'footer_services',
    ],
    [
        'href' => SITE_URL . '/pages/results_streets.php',
        'icon' => SITE_URL . '/assets/icons/icon-streets.svg',
        'label_key' => 'footer_streets',
    ],
];
?>
<footer>
    <nav class="footer-nav">
        <?php foreach ($footerItems as $item): 
            $label = $translations[$item['label_key']] ?? $item['label_key'];
        ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="footer-item" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                <img src="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="footer-icon" aria-hidden="true">
                <span class="footer-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</footer>

</body>
</html>
