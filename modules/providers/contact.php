<?php
/**
 * /modules/providers/contact.php - Módulo de Contacto (VERSÃO AJUSTADA E PADRONIZADA)
 *
 * RESPONSABILIDADES:
 * - Exibe os campos de contacto para o anúncio com a mesma regra de DDI e bandeiras de register.php.
 *
 * ÚLTIMA ATUALIZAÇÃO: 13/07/2025 - Padronizado o seletor de DDI.
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Conexões e traduções
$db = getDBConnection();
$contact_form_context = 'provider_contact_form';
$common_form_context = 'common_form';

// --- LÓGICA DE PRÉ-PREENCHIMENTO ---
$current_advertised_phone_code = $form_data['advertised_phone_code'] ?? ($provider_data['details']['advertised_phone_code'] ?? '');
$current_advertised_phone_number = $form_data['advertised_phone_number'] ?? ($provider_data['details']['advertised_phone_number'] ?? '');
$current_show_on_ad_sms = $form_data['show_on_ad_sms'] ?? ($provider_data['details']['show_on_ad_sms'] ?? 0);
$current_show_on_ad_call = $form_data['show_on_ad_call'] ?? ($provider_data['details']['show_on_ad_call'] ?? 0);
$current_show_on_ad_whatsapp = $form_data['show_on_ad_whatsapp'] ?? ($provider_data['details']['show_on_ad_whatsapp'] ?? 0);
$current_show_on_ad_telegram = $form_data['show_on_ad_telegram'] ?? ($provider_data['details']['show_on_ad_telegram'] ?? 0);

// Carrega os códigos de telefone (DDI)
$ddi_options = [];
try {
    $stmt_ddi = $db->query("SELECT name, calling_code, iso_code FROM countries WHERE calling_code IS NOT NULL AND calling_code != '' ORDER BY name ASC");
    $countries_for_ddi = $stmt_ddi->fetchAll(PDO::FETCH_ASSOC);

    foreach ($countries_for_ddi as $country) {
        if (!empty($country['calling_code'])) {
            $ddi_options[] = [
                'calling_code' => $country['calling_code'],
                'name' => $country['name'],
                'iso_code' => strtolower($country['iso_code']),
                // AJUSTE: Padronizado para usar .png, como em register.php
                'flag_url' => SITE_URL . '/assets/images/flags/' . strtolower($country['iso_code']) . '.png'
            ];
        }
    }
} catch (Exception $e) {
    log_system_error('CONTACT_MODULE_ERROR: Falha ao carregar DDIs. Erro: ' . $e->getMessage(), 'error', 'contact_ddi_load');
}

// Lógica para definir o DDI padrão
if (empty($current_advertised_phone_code)) {
    // Tenta usar o país da sessão
    $session_country_code = $_SESSION['country_code'] ?? '';
    $found_ddi = false;
    if ($session_country_code) {
        foreach ($ddi_options as $option) {
            if (strtoupper($option['iso_code']) === strtoupper($session_country_code)) {
                $current_advertised_phone_code = $option['calling_code'];
                $found_ddi = true;
                break;
            }
        }
    }
    // Se não encontrar, usa o fallback
    if (!$found_ddi) {
        $current_advertised_phone_code = '+351'; // Fallback para Portugal
    }
}

// Traduções do módulo
$translations = [
    'module_title_contact' => getTranslation('module_title_contact', $languageCode, $contact_form_context),
    'contact_module_description' => getTranslation('contact_module_description', $languageCode, $contact_form_context),
    'label_advertised_phone_code' => getTranslation('label_advertised_phone_code', $languageCode, $contact_form_context),
    'label_advertised_phone_number' => getTranslation('label_advertised_phone_number', $languageCode, $contact_form_context),
    'placeholder_phone_number' => getTranslation('placeholder_phone_number', $languageCode, $common_form_context),
    'label_show_on_ad_sms' => getTranslation('label_show_on_ad_sms', $languageCode, $contact_form_context),
    'label_show_on_ad_call' => getTranslation('label_show_on_ad_call', $languageCode, $contact_form_context),
    'label_show_on_ad_whatsapp' => getTranslation('label_show_on_ad_whatsapp', $languageCode, $contact_form_context),
    'label_show_on_ad_telegram' => getTranslation('label_show_on_ad_telegram', $languageCode, $contact_form_context),
];
?>

<fieldset class="form-module" id="contact-module">
    <legend><?php echo htmlspecialchars($translations['module_title_contact']); ?></legend>
    <p class="module-description"><?php echo htmlspecialchars($translations['contact_module_description']); ?></p>

    <div class="form-grid">
        <div class="form-group">
            <label for="advertised_phone_code"><?php echo htmlspecialchars($translations['label_advertised_phone_code']); ?></label>
            <select id="advertised_phone_code" name="advertised_phone_code" class="form-control">
                <?php foreach ($ddi_options as $option): ?>
                    <option value="<?php echo htmlspecialchars($option['calling_code']); ?>"
                            data-flag="<?php echo htmlspecialchars($option['flag_url']); ?>"
                            <?php echo ($option['calling_code'] === $current_advertised_phone_code) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($option['calling_code'] . ' (' . $option['name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="advertised_phone_number"><?php echo htmlspecialchars($translations['label_advertised_phone_number']); ?></label>
            <input type="tel" id="advertised_phone_number" name="advertised_phone_number" class="form-control"
                   value="<?php echo htmlspecialchars($current_advertised_phone_number); ?>"
                   placeholder="<?php echo htmlspecialchars($translations['placeholder_phone_number']); ?>" maxlength="20">
        </div>

        <div class="form-group checkbox-group full-width">
            <?php
            $checkboxes = [
                'show_on_ad_sms' => ['label' => $translations['label_show_on_ad_sms'], 'current' => $current_show_on_ad_sms],
                'show_on_ad_call' => ['label' => $translations['label_show_on_ad_call'], 'current' => $current_show_on_ad_call],
                'show_on_ad_whatsapp' => ['label' => $translations['label_show_on_ad_whatsapp'], 'current' => $current_show_on_ad_whatsapp],
                'show_on_ad_telegram' => ['label' => $translations['label_show_on_ad_telegram'], 'current' => $current_show_on_ad_telegram],
            ];
            ?>
            <?php foreach ($checkboxes as $name => $details): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="<?php echo $name; ?>" value="1" <?php echo $details['current'] ? 'checked' : ''; ?>>
                    <span class="control-indicator"></span>
                    <?php echo htmlspecialchars($details['label']); ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</fieldset>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const phoneCodeSelect = document.getElementById('advertised_phone_code');

    function updatePhoneFlag(selectElement) {
        if (!selectElement) return;
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        // AJUSTE: Garante que a propriedade dataset.flag existe
        const flagUrl = selectedOption?.dataset.flag || '';
        
        if (flagUrl) {
            selectElement.style.backgroundImage = `url(${flagUrl})`;
            selectElement.style.backgroundRepeat = 'no-repeat';
            selectElement.style.backgroundPosition = '10px center';
            selectElement.style.backgroundSize = '24px auto';
            selectElement.style.paddingLeft = '45px'; // Espaço para a bandeira
        } else {
            selectElement.style.backgroundImage = 'none';
            selectElement.style.paddingLeft = '14px';
        }
    }

    if (phoneCodeSelect) {
        // Aplica a bandeira ao carregar a página
        updatePhoneFlag(phoneCodeSelect);
        // Adiciona o listener para mudanças
        phoneCodeSelect.addEventListener('change', () => updatePhoneFlag(phoneCodeSelect));
    }
});
</script>