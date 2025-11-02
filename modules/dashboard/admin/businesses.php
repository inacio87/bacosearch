<?php
/**
 * /modules/dashboard/admin/translations.php - Módulo de Gerenciamento de Traduções (Simplificado)
 *
 * RESPONSABILIDADES:
 * 1. Exibir uma mensagem simples "Em Breve" em vez de dados dinâmicos.
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// Carrega as traduções necessárias para este módulo
$translations = [];
$keys_to_translate = [
    'translations_title', 'coming_soon_message' // Nova chave para a mensagem "Em Breve"
];
foreach ($keys_to_translate as $key) {
    $translations[$key] = getTranslation($key, $languageCode, 'admin_dashboard');
}
?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= htmlspecialchars($translations['translations_title'] ?? 'Traduções') ?></h1>
    </div>

    <div class="translations-info-section">
        <p class="coming-soon-text">
            <?= htmlspecialchars($translations['coming_soon_message'] ?? 'Em Breve') ?>
        </p>
    </div>
</div>

<style>
/* CSS simples para centralizar a mensagem "Em Breve" */
.translations-info-section {
    background-color: var(--bs-bg-white, #fff);
    padding: var(--spacing-lg, 1.5rem);
    border-radius: var(--bs-border-radius-lg, 12px);
    border: 1px solid var(--bs-border-color, #e0e0e0);
    box-shadow: var(--bs-shadow-sm, 0 2px 4px rgba(0,0,0,0.07));
    text-align: center;
    min-height: 200px; /* Altura mínima para a seção */
    display: flex;
    justify-content: center;
    align-items: center;
}

.coming-soon-text {
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--bs-text-secondary, #6c757d);
}
</style>

<script>
// Não há JavaScript complexo aqui, pois não há dados para buscar ou renderizar.
// Apenas um script vazio para manter a estrutura se necessário no futuro.
document.addEventListener('DOMContentLoaded', () => {
    // console.log('Módulo de Traduções: Apenas exibindo mensagem "Em Breve".');
});
</script>
