<?php
/**
 * /modules/dashboard/admin/translations.php
 * Módulo de Gestão de Traduções (Versão Compatível)
 * - Utiliza a coluna 'context' existente para agrupar as traduções.
 * - Permite editar e adicionar traduções para chaves específicas.
 * - Exibe um relatório de chaves com traduções faltando, incluindo o contexto.
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// --- Configuração dos Idiomas ---
define('LANGUAGES', [
    'pt-br' => 'pt-br',
    'en-us' => 'en-us',
    'es-es' => 'es-es',
    'fr-fr' => 'fr-fr',
    'de-de' => 'de-de',
    'it-it' => 'it-it',
    'ja-jp' => 'ja-jp',
    'ru-ru' => 'ru-ru',
    'zh-cn' => 'zh-cn',
    'ar-sa' => 'ar-sa',
    'pl-pl' => 'pl-pl',
    'nl-nl' => 'nl-nl'
]);

// Carrega as traduções do módulo
$title_main              = getTranslation('admin_translations_title', $languageCode, 'admin_translations');
$search_placeholder      = getTranslation('search_translation_key', $languageCode, 'admin_translations');
$edit_section_title      = getTranslation('edit_translations', $languageCode, 'admin_translations');
$missing_section_title   = getTranslation('missing_translations_report', $languageCode, 'admin_translations');
$save_button             = getTranslation('save_changes', $languageCode, 'admin_translations');
$context_header          = getTranslation('context_header', $languageCode, 'admin_translations'); // Ex: "Contexto"
$key_not_found_message   = getTranslation('key_not_found', $languageCode, 'admin_translations'); // Ex: "Chave '%s' não encontrada."
$no_missing_keys_message = getTranslation('no_missing_keys', $languageCode, 'admin_translations'); // Ex: "Nenhuma tradução faltando."

$db = getDBConnection();
$search_key = filter_input(INPUT_GET, 'search_key', FILTER_SANITIZE_STRING) ?? '';
$translations = [];
$missing_translations = [];
$current_context = '';

// Lógica para buscar as traduções de uma chave
if ($search_key) {
    $stmt = $db->prepare("
        SELECT language_code, translation_value, context
        FROM translations
        WHERE translation_key = :key
    ");
    $stmt->execute([':key' => $search_key]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $temp_translations = [];
    if ($results) {
        $current_context = $results[0]['context']; // Pega o contexto do primeiro resultado
        foreach ($results as $row) {
            $temp_translations[$row['language_code']] = $row['translation_value'];
        }
    }

    foreach (LANGUAGES as $code => $name) {
        $translations[$code] = [
            'name' => $name,
            'value' => $temp_translations[$code] ?? ''
        ];
    }
}

// Lógica para buscar chaves com traduções faltando (usando a coluna 'context')
$stmt_missing = $db->query("
    SELECT
        translation_key,
        context,
        COUNT(DISTINCT language_code) as lang_count
    FROM translations
    GROUP BY translation_key, context
    HAVING lang_count < " . count(LANGUAGES)
);
$missing_keys_data = $stmt_missing->fetchAll(PDO::FETCH_ASSOC);

if ($missing_keys_data) {
    foreach ($missing_keys_data as $row) {
        $stmt_langs = $db->prepare("SELECT language_code FROM translations WHERE translation_key = :key AND context = :context");
        $stmt_langs->execute([':key' => $row['translation_key'], ':context' => $row['context']]);
        $available_langs_codes = $stmt_langs->fetchAll(PDO::FETCH_COLUMN);
        
        $missing_langs = array_diff_key(LANGUAGES, array_flip($available_langs_codes));

        $missing_translations[] = [
            'key' => $row['translation_key'],
            'context' => $row['context'],
            'missing' => $missing_langs
        ];
    }
}

?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= htmlspecialchars($title_main) ?></h1>
    </div>

    <div class="tabs">
        <button class="tab-link active" onclick="openTab(event, 'Edit')"><?= htmlspecialchars($edit_section_title) ?></button>
        <button class="tab-link" onclick="openTab(event, 'Missing')"><?= htmlspecialchars($missing_section_title) ?></button>
    </div>

    <div id="Edit" class="tab-content" style="display: block;">
        <h2><?= htmlspecialchars($edit_section_title) ?></h2>
        <form method="GET" action="">
            <input type="hidden" name="module" value="translations">
            <input type="text" name="search_key" placeholder="<?= htmlspecialchars($search_placeholder) ?>" value="<?= htmlspecialchars($search_key) ?>" class="form-control" style="width: 300px; display: inline-block; margin-right: 10px;">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>

        <?php if ($search_key && !empty($translations) && $translations['pt-br']['name']): // Checagem simples para ver se a busca retornou algo ?>
            <form id="edit-translation-form" method="POST" action="<?= SITE_URL ?>/api/update_translations.php">
                <input type="hidden" name="translation_key" value="<?= htmlspecialchars($search_key) ?>">
                <input type="hidden" name="context" value="<?= htmlspecialchars($current_context) ?>">
                <p style="margin-top:15px;"><strong><?= htmlspecialchars($context_header) ?>:</strong> <?= htmlspecialchars($current_context ?: 'N/A') ?></p>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Idioma</th>
                            <th>Tradução</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($translations as $code => $data): ?>
                            <tr>
                                <td><?= htmlspecialchars($data['name']) ?></td>
                                <td>
                                    <input type="text" name="translations[<?= $code ?>]" value="<?= htmlspecialchars($data['value']) ?>" class="form-control" placeholder="Inserir tradução para <?= htmlspecialchars($data['name']) ?>...">
                                    <?php if (empty($data['value'])): ?>
                                        <small style="color: #dc3545; font-weight: bold;">Tradução faltando.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-success" style="margin-top: 15px;"><?= htmlspecialchars($save_button) ?></button>
            </form>
        <?php elseif ($search_key): ?>
             <p class="no-results-message" style="margin-top: 20px;"><?= htmlspecialchars(sprintf($key_not_found_message ?: "Chave de tradução '%s' não encontrada.", $search_key)) ?></p>
        <?php endif; ?>
    </div>

    <div id="Missing" class="tab-content" style="display: none;">
        <h2><?= htmlspecialchars($missing_section_title) ?></h2>
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th>Chave de Tradução</th>
                    <th><?= htmlspecialchars($context_header) ?></th>
                    <th>Idiomas Faltando</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($missing_translations)): ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 20px;"><?= htmlspecialchars($no_missing_keys_message ?: "Parabéns! Nenhuma tradução faltando encontrada.") ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($missing_translations as $item): ?>
                        <tr>
                            <td><a href="?module=translations&search_key=<?= htmlspecialchars($item['key']) ?>"><?= htmlspecialchars($item['key']) ?></a></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($item['context'] ?: 'Sem Contexto') ?></span></td>
                            <td>
                                <?php
                                    $missing_names = array_map('htmlspecialchars', array_values($item['missing']));
                                    echo implode(', ', $missing_names);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    let i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    if (evt) {
        evt.currentTarget.className += " active";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const hasSearchKey = urlParams.has('search_key') && urlParams.get('search_key');
    const initialTab = hasSearchKey ? 'Edit' : 'Missing';

    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.getElementById(initialTab).style.display = 'block';
    
    document.querySelectorAll('.tab-link').forEach(btn => btn.classList.remove('active'));
    const activeButton = document.querySelector(`.tab-link[onclick*="'${initialTab}'"]`);
    if(activeButton) {
        activeButton.classList.add('active');
    }

    const form = document.getElementById('edit-translation-form');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = 'Salvando...';
            submitButton.disabled = true;

            const formData = new FormData(form);

            // *** URL CORRIGIDA PARA CORRESPONDER À REGRA DO .HTACCESS ***
            const apiUrl = '<?= SITE_URL ?>/api/update_translations.php';

            fetch(apiUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Lança um erro se a resposta não for OK (ex: 403, 500)
                    throw new Error(`Erro de rede: ${response.status} - ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                const notification = document.createElement('div');
                notification.className = data.success ? 'alert alert-success' : 'alert alert-danger';
                notification.textContent = data.message;
                notification.style.marginTop = '15px';
                
                // Remove qualquer notificação antiga antes de adicionar uma nova
                const oldNotification = form.nextElementSibling;
                if (oldNotification && oldNotification.classList.contains('alert')) {
                    oldNotification.remove();
                }
                
                form.insertAdjacentElement('afterend', notification);

                setTimeout(() => {
                    notification.remove();
                }, 5000);
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Ocorreu um erro de comunicação. Verifique o console do navegador.');
            })
            .finally(() => {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            });
        });
    }
});
</script>
<style>
    .tabs { overflow: hidden; border-bottom: 1px solid #dee2e6; margin-bottom: 20px; }
    .tab-link { background-color: #f8f9fa; float: left; border: 1px solid transparent; border-bottom: none; outline: none; cursor: pointer; padding: 14px 16px; transition: 0.3s; font-size: 16px; border-top-left-radius: .25rem; border-top-right-radius: .25rem; }
    .tab-link:hover { background-color: #e9ecef; }
    .tab-link.active { background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; }
    .tab-content { display: none; padding: 20px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 .25rem .25rem; }
    .badge { display: inline-block; padding: .35em .65em; font-size: .75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }
    .badge-secondary { color: #fff; background-color: #6c757d; }
</style>