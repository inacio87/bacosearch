<?php
/**
 * /modules/providers/values.php - Módulo de Preços e Valores
 *
 * RESPONSABILIDADES:
 * - Permite definir uma única moeda principal para o anúncio.
 * - Todos os campos de preço usam dinamicamente o símbolo da moeda selecionada.
 * - Moedas carregadas da tabela `countries` do banco de dados.
 *
 * ÚLTIMA ATUALIZAÇÃO: 07/07/2025 - Ajustado para compatibilidade com o esquema de DB fornecido.
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

$db = getDBConnection();

// Contextos de tradução
$values_form_context = 'provider_form'; // Ajustado para o contexto real no banco de dados
$common_form_context = 'common_form';
$validation_errors_context = 'validation_errors';

// Lógica de pré-preenchimento
// $provider_data é a fonte principal de dados (do DB ou de $_SESSION['form_data_provider_form'])
// Ele já deve vir do arquivo pai (register_providers.php) com o campo 'details' decodificado.
$current_currency = $form_data['currency'] ?? ($provider_data['currency'] ?? '');
$current_base_hourly_rate = $form_data['base_hourly_rate'] ?? ($provider_data['base_hourly_rate'] ?? '');
$price_15_min = $form_data['price_15_min'] ?? ($provider_data['details']['price_15_min'] ?? '');
$price_30_min = $form_data['price_30_min'] ?? ($provider_data['details']['price_30_min'] ?? '');
$price_2_hr = $form_data['price_2_hr'] ?? ($provider_data['details']['price_2_hr'] ?? '');
$price_overnight = $form_data['price_overnight'] ?? ($provider_data['details']['price_overnight'] ?? '');

// --- Carregar Moedas do Banco de Dados (usando as colunas 'currencies' e 'currencies_icon') ---
$available_currencies = [];
try {
    // Seleciona as colunas 'currencies' como 'name' e 'currencies_icon' como 'icon'
    $stmt = $db->query("SELECT DISTINCT currencies AS name, currencies_icon AS icon FROM countries WHERE currencies IS NOT NULL AND currencies != '' ORDER BY currencies ASC");
    $available_currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    log_system_error('VALUES_MODULE_ERROR: Falha ao carregar moedas da tabela countries. Erro: ' . $e->getMessage(), 'error');
    // Fallback para moedas hardcoded se o DB falhar
    $available_currencies = [
        ['name' => 'Euro', 'icon' => '€'],
        ['name' => 'Dólar Americano', 'icon' => '$'],
        ['name' => 'Real Brasileiro', 'icon' => 'R$']
    ];
}

// Encontra o ícone da moeda selecionada para usar como padrão inicial
$initial_currency_icon = '€'; // Fallback
if (!empty($current_currency)) {
    foreach ($available_currencies as $currency_item) {
        if ($currency_item['name'] === $current_currency) {
            $initial_currency_icon = $currency_item['icon'];
            break;
        }
    }
}

?>

<fieldset class="form-module">
    <legend><?php echo htmlspecialchars(getTranslation('module_title_values', $languageCode, $values_form_context)); ?></legend>

    <div class="form-group full-width">
        <label for="currency" class="group-title"><?php echo htmlspecialchars(getTranslation('label_main_currency', $languageCode, $values_form_context) ?: 'Moeda Principal do Anúncio'); ?></label>
        <select id="currency" name="currency" class="form-control" required>
            <option value="" data-icon="€"><?php echo htmlspecialchars(getTranslation('option_select_default', $languageCode, $common_form_context)); ?></option>
            <?php foreach ($available_currencies as $currency_item): ?>
                <option value="<?php echo htmlspecialchars($currency_item['name']); ?>" 
                        data-icon="<?php echo htmlspecialchars($currency_item['icon']); ?>"
                        <?php echo ($current_currency === $currency_item['name']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($currency_item['name']) . ' (' . htmlspecialchars($currency_item['icon']) . ')'; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="invalid-feedback"><?php echo htmlspecialchars(getTranslation('feedback_required_field', $languageCode, $validation_errors_context)); ?></div>
    </div>

    <div class="form-grid price-grid">
        <div class="form-group">
            <label for="base_hourly_rate"><?php echo htmlspecialchars(getTranslation('label_base_hourly_rate', $languageCode, $values_form_context)); ?></label>
            <div class="input-group">
                <span class="input-group-text currency-symbol"><?php echo htmlspecialchars($initial_currency_icon); ?></span>
                <input type="number" id="base_hourly_rate" name="base_hourly_rate" class="form-control" placeholder="100" value="<?php echo htmlspecialchars($current_base_hourly_rate); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="price_15_min"><?php echo htmlspecialchars(getTranslation('label_price_15_min', $languageCode, $values_form_context)); ?></label>
             <div class="input-group">
                <span class="input-group-text currency-symbol"><?php echo htmlspecialchars($initial_currency_icon); ?></span>
                <input type="number" id="price_15_min" name="price_15_min" class="form-control" placeholder="<?php echo htmlspecialchars(getTranslation('placeholder_optional', $languageCode, $common_form_context)); ?>" value="<?php echo htmlspecialchars($price_15_min); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="price_30_min"><?php echo htmlspecialchars(getTranslation('label_price_30_min', $languageCode, $values_form_context)); ?></label>
             <div class="input-group">
                <span class="input-group-text currency-symbol"><?php echo htmlspecialchars($initial_currency_icon); ?></span>
                <input type="number" id="price_30_min" name="price_30_min" class="form-control" placeholder="<?php echo htmlspecialchars(getTranslation('placeholder_optional', $languageCode, $common_form_context)); ?>" value="<?php echo htmlspecialchars($price_30_min); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label for="price_2_hr"><?php echo htmlspecialchars(getTranslation('label_price_2_hr', $languageCode, $values_form_context)); ?></label>
             <div class="input-group">
                <span class="input-group-text currency-symbol"><?php echo htmlspecialchars($initial_currency_icon); ?></span>
                <input type="number" id="price_2_hr" name="price_2_hr" class="form-control" placeholder="<?php echo htmlspecialchars(getTranslation('placeholder_optional', $languageCode, $common_form_context)); ?>" value="<?php echo htmlspecialchars($price_2_hr); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="price_overnight"><?php echo htmlspecialchars(getTranslation('label_price_overnight', $languageCode, $values_form_context)); ?></label>
             <div class="input-group">
                <span class="input-group-text currency-symbol"><?php echo htmlspecialchars($initial_currency_icon); ?></span>
                <input type="number" id="price_overnight" name="price_overnight" class="form-control" placeholder="<?php echo htmlspecialchars(getTranslation('placeholder_optional', $languageCode, $common_form_context)); ?>" value="<?php echo htmlspecialchars($price_overnight); ?>">
            </div>
        </div>
    </div>

    </fieldset>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySelector = document.getElementById('currency');
    const currencySymbols = document.querySelectorAll('.currency-symbol');

    function updateCurrencySymbols() {
        if (!currencySelector) return;
        const selectedOption = currencySelector.options[currencySelector.selectedIndex];
        const icon = selectedOption.dataset.icon || '€'; // Usa '€' como fallback se nenhum for selecionado
        currencySymbols.forEach(symbol => {
            symbol.textContent = icon;
        });
    }

    // Adiciona o listener para o seletor de moeda
    if (currencySelector) {
        currencySelector.addEventListener('change', updateCurrencySymbols);
        updateCurrencySymbols(); // Chama na carga da página para definir o símbolo inicial
    }
});
</script>
