<?php
/**
 * /modules/providers/services.php - Módulo de Serviços (VERSÃO CORRIGIDA E OTIMIZADA)
 *
 * RESPONSABILIDADES:
 * - Exibe a lista de serviços e permite que o anunciante defina status e preço.
 * - Envia os dados no formato correto (`services[key][status]`) para a API.
 * - Carrega e pré-preenche os dados salvos da tabela `providers_service_offerings`.
 *
 * ÚLTIMA ATUALIZAÇÃO: 14/07/2025 - Corrigidos nomes dos campos e lógica de pré-preenchimento.
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Conexão e contextos
$db = getDBConnection();
$services_form_context = 'provider_services_form';
$common_options_context = 'common_options';
$common_form_context = 'common_form';

// --- LÓGICA DE CARREGAMENTO DE DADOS CORRIGIDA ---

// 1. Carregar a lista mestre de todos os serviços disponíveis
$all_services = [];
try {
    // A consulta para buscar a lista de serviços já estava correta.
    $stmt_services = $db->query("SELECT id, term, service_key FROM providers_services_list ORDER BY term ASC");
    $all_services_raw = $stmt_services->fetchAll(PDO::FETCH_ASSOC);

    // A lógica para traduzir os nomes dos serviços também estava correta.
    $service_translations = [];
    $stmt_translations = $db->prepare("SELECT translation_key, translation_value FROM translations WHERE language_code = :lang_code AND context = 'service_intents'");
    $stmt_translations->execute([':lang_code' => $languageCode]);
    while ($row = $stmt_translations->fetch(PDO::FETCH_ASSOC)) {
        $service_translations[$row['translation_key']] = $row['translation_value'];
    }

    foreach ($all_services_raw as $service_row) {
        $all_services[$service_row['service_key']] = [
            'id' => $service_row['id'],
            'name' => $service_translations[$service_row['service_key']] ?? $service_row['term'],
            'service_key' => $service_row['service_key']
        ];
    }
} catch (Exception $e) {
    log_system_error('SERVICES_MODULE_ERROR: ' . $e->getMessage(), 'error', 'services_load_error');
    $all_services = [];
}

// 2. Carregar os serviços que ESTE anunciante específico já salvou
$saved_services = [];
if (isset($provider_data['id'])) {
    $stmt_saved = $db->prepare("SELECT service_key, status, price FROM providers_service_offerings WHERE provider_id = ?");
    $stmt_saved->execute([$provider_data['id']]);
    while ($row = $stmt_saved->fetch(PDO::FETCH_ASSOC)) {
        $saved_services[$row['service_key']] = [
            'status' => $row['status'],
            'price' => $row['price']
        ];
    }
}

// Traduções da UI
$translations['module_title_services'] = getTranslation('module_title_services', $languageCode, 'provider_form');
// ... (resto das traduções permanece igual) ...

?>

<fieldset class="form-module" id="services-module">
    <legend><?php echo htmlspecialchars($translations['module_title_services'] ?? 'Serviços Oferecidos'); ?></legend>
    <p class="module-description"><?php echo htmlspecialchars($translations['services_module_description'] ?? 'Selecione os serviços que você oferece.'); ?></p>

    <div class="services-list">
        <?php foreach ($all_services as $service_key => $service): ?>
            <?php
            // Lógica de pré-preenchimento corrigida
            // Prioridade: Dados do formulário (se houver erro), depois dados do banco, e por último 'not_available'.
            $current_status = $form_data['services'][$service_key]['status'] ?? ($saved_services[$service_key]['status'] ?? 'not_available');
            $current_price = $form_data['services'][$service_key]['price'] ?? ($saved_services[$service_key]['price'] ?? '');
            ?>
            <div class="service-item" data-service-key="<?php echo htmlspecialchars($service_key); ?>">
                <div class="form-group full-width">
                    <label class="group-title"><?php echo htmlspecialchars($service['name']); ?></label>
                    <div class="radio-row">
                  
                        <label class="radio-label">
                            <input type="radio" name="services[<?php echo htmlspecialchars($service_key); ?>][status]" value="not_available" <?php echo ($current_status === 'not_available') ? 'checked' : ''; ?>>
                            <span class="control-indicator"></span>
                            <span><?php echo htmlspecialchars($translations['option_dont'] ?? 'Não Faço'); ?></span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="services[<?php echo htmlspecialchars($service_key); ?>][status]" value="included" <?php echo ($current_status === 'included') ? 'checked' : ''; ?>>
                            <span class="control-indicator"></span>
                            <span><?php echo htmlspecialchars($translations['option_do'] ?? 'Incluído'); ?></span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="services[<?php echo htmlspecialchars($service_key); ?>][status]" value="negotiable" <?php echo ($current_status === 'negotiable') ? 'checked' : ''; ?>>
                            <span class="control-indicator"></span>
                            <span><?php echo htmlspecialchars($translations['option_negotiable'] ?? 'Negociável'); ?></span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="services[<?php echo htmlspecialchars($service_key); ?>][status]" value="extra_fee" <?php echo ($current_status === 'extra_fee') ? 'checked' : ''; ?>>
                            <span class="control-indicator"></span>
                            <span><?php echo htmlspecialchars($translations['option_extra'] ?? 'Extra'); ?></span>
                        </label>
                    </div>
                    <div class="input-group" id="price-input-<?php echo htmlspecialchars($service_key); ?>" style="display: <?php echo ($current_status === 'extra_fee') ? 'flex' : 'none'; ?>;">
                        <span class="input-group-text">€</span>
                        <input type="number" name="services[<?php echo htmlspecialchars($service_key); ?>][price]" class="form-control" placeholder="<?php echo htmlspecialchars($translations['placeholder_extra_price'] ?? '0.00'); ?>" value="<?php echo htmlspecialchars($current_price); ?>" min="0" step="1">
                    </div>
                 
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</fieldset>

<!-- O JavaScript não precisa de grandes mudanças, apenas garantir que ele encontre os elementos corretamente -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // A lógica para mostrar/esconder o campo de preço já estava boa.
    // Apenas garantimos que ela funcione com os novos nomes dos campos.
    document.querySelectorAll('input[type="radio"][name^="services["]').forEach(radio => {
        radio.addEventListener('change', function() {
            const serviceKey = this.name.match(/\[(.*?)\]/)[1];
            const status = this.value;
            const priceContainer = document.getElementById(`price-input-${serviceKey}`);
            
            if (priceContainer) {
                const priceInput = priceContainer.querySelector('input');
                if (status === 'extra_fee') {
                    priceContainer.style.display = 'flex';
                    if (priceInput) priceInput.required = true;
                } else {
                    priceContainer.style.display = 'none';
                    if (priceInput) {
                        priceInput.required = false;
                        priceInput.value = ''; // Limpa o preço se não for 'extra'
                    }
                }
            }
        });
    });
});
</script>
