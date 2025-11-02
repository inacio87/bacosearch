<?php
/**
 * /modules/providers/profile.php - Módulo de Perfil (VERSÃO AJUSTADA E SIMPLIFICADA)
 *
 * ÚLTIMA ATUALIZAÇÃO: 27/08/2025 - Ajustado para puxar idade a partir de accounts.birth_date (JOIN).
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Conexão
$db = getDBConnection();

// Carrega os dados necessários para os selects do formulário
try {
    $all_languages = $db->query("SELECT id, name, code FROM languages ORDER BY name ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    $all_countries = $db->query("SELECT id, nationality, iso_code FROM countries WHERE nationality IS NOT NULL ORDER BY nationality ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    $all_categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    log_system_error('PROFILE_MODULE_ERROR: ' . $e->getMessage(), 'error', 'profile_load_data');
    $all_languages = [];
    $all_countries = [];
    $all_categories = [];
}

// ----------------------------------------------------------------------
// Lógica de pré-preenchimento
// ----------------------------------------------------------------------

$current_display_name  = $form_data['artistic_name'] ?? ($provider_data['display_name'] ?? '');
$current_ad_title      = $form_data['ad_title'] ?? ($provider_data['ad_title'] ?? '');
$current_description   = $form_data['description'] ?? ($provider_data['description'] ?? '');
$current_gender        = $form_data['gender'] ?? ($provider_data['gender'] ?? '');
$current_provider_type = $form_data['provider_type'] ?? ($provider_data['provider_type'] ?? 'independente');
$current_languages     = $form_data['languages'] ?? ($provider_data['details']['languages_spoken'] ?? []);
$current_nationality_id= $form_data['nationality_id'] ?? ($provider_data['nationality_id'] ?? null);
$current_category_id   = $form_data['category_id'] ?? ($provider_data['category_id'] ?? null);

// ----------------------------------------------------------------------
// Idade (corrigido para usar accounts.birth_date vindo no $provider_data)
// ----------------------------------------------------------------------

$age = 'N/A';
$birthDate = $form_data['birth_date'] ?? ($provider_data['birth_date'] ?? null);

if (!empty($birthDate)) {
    try {
        $age = (new DateTime())->diff(new DateTime($birthDate))->y;
    } catch (Exception $e) {
        // silencioso
    }
}
?>

<style>
    .char-count {
        font-size: 0.8rem;
        margin-top: 5px;
        display: block;
        text-align: right;
    }
    .char-count.is-invalid { color: #dc3545; }
    .char-count.is-valid { color: #28a745; }
</style>

<fieldset class="form-module" id="profile-module">
    <legend><?php echo htmlspecialchars($translations['module_title_profile']); ?></legend>
    <p class="module-description"><?php echo htmlspecialchars($translations['profile_module_description']); ?></p>

    <div class="form-grid">
        <div class="form-group full-width">
            <label for="artistic_name"><?php echo htmlspecialchars($translations['label_artistic_name']); ?></label>
            <input type="text" id="artistic_name" name="artistic_name" class="form-control"
                   placeholder="<?php echo htmlspecialchars($translations['placeholder_artistic_name']); ?>"
                   value="<?php echo htmlspecialchars($current_display_name); ?>" maxlength="50" required>
            <div class="invalid-feedback"><?php echo htmlspecialchars($translations['feedback_required_field']); ?></div>
        </div>

        <div class="form-group full-width">
            <label for="ad_title"><?php echo htmlspecialchars($translations['label_ad_title']); ?></label>
            <input type="text" id="ad_title" name="ad_title" class="form-control"
                   placeholder="<?php echo htmlspecialchars($translations['placeholder_ad_title']); ?>"
                   value="<?php echo htmlspecialchars($current_ad_title); ?>"
                   minlength="20" maxlength="80" required>
            <div class="invalid-feedback"><?php echo htmlspecialchars(sprintf($translations['min_chars_feedback'], 20)); ?></div>
            <span class="char-count" id="char-count-title"></span>
        </div>

        <div class="form-group full-width">
            <label for="description"><?php echo htmlspecialchars($translations['label_description']); ?></label>
            <textarea id="description" name="description" class="form-control"
                      placeholder="<?php echo htmlspecialchars($translations['placeholder_description']); ?>"
                      minlength="300" maxlength="500" required><?php echo htmlspecialchars($current_description); ?></textarea>
            <div class="invalid-feedback"><?php echo htmlspecialchars(sprintf($translations['min_chars_feedback'], 300)); ?></div>
            <span class="char-count" id="char-count-desc"></span>
        </div>

        <div class="form-group">
            <label for="gender"><?php echo htmlspecialchars($translations['label_gender']); ?></label>
            <select id="gender" name="gender" class="form-control" required>
                <option value=""><?php echo htmlspecialchars($translations['select_option_default']); ?></option>
                <option value="female" <?php echo ($current_gender === 'female') ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['option_female']); ?></option>
                <option value="male" <?php echo ($current_gender === 'male') ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['option_male']); ?></option>
                <option value="trans" <?php echo ($current_gender === 'trans') ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['option_trans']); ?></option>
                <option value="couple" <?php echo ($current_gender === 'couple') ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['option_couple']); ?></option>
            </select>
            <div class="invalid-feedback"><?php echo htmlspecialchars($translations['feedback_required_field']); ?></div>
        </div>

        <div class="form-group">
            <label for="provider_type"><?php echo htmlspecialchars($translations['label_provider_type']); ?></label>
            <select id="provider_type" name="provider_type" class="form-control" required>
                <option value=""><?php echo htmlspecialchars($translations['select_option_default']); ?></option>
                <option value="independente" <?php echo ($current_provider_type === 'independente') ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['option_independent']); ?></option>
                <option value="agency" <?php echo ($current_provider_type === 'agency') ? 'selected' : ''; ?>><?php echo htmlspecialchars($translations['option_agency']); ?></option>
            </select>
            <div class="invalid-feedback"><?php echo htmlspecialchars($translations['feedback_required_field']); ?></div>
        </div>

        <div class="form-group">
            <label for="category_id"><?php echo htmlspecialchars($translations['label_category'] ?? 'Categoria Principal *'); ?></label>
            <select id="category_id" name="category_id" class="form-control" required>
                <option value=""><?php echo htmlspecialchars($translations['select_option_default'] ?? 'Selecione uma opção...'); ?></option>
                <?php foreach ($all_categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>"
                            <?php echo ((int)$category['id'] === (int)$current_category_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?php echo htmlspecialchars($translations['feedback_required_field'] ?? 'Campo obrigatório.'); ?></div>
        </div>

        <div class="form-group">
            <label for="nationality_id"><?php echo htmlspecialchars($translations['label_nationality']); ?></label>
            <select id="nationality_id" name="nationality_id" class="form-control" required>
                <option value=""><?php echo htmlspecialchars($translations['select_option_default']); ?></option>
                <?php foreach ($all_countries as $country_opt): ?>
                    <option value="<?php echo htmlspecialchars($country_opt['id']); ?>"
                            <?php echo ((int)$country_opt['id'] === (int)$current_nationality_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($country_opt['nationality']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?php echo htmlspecialchars($translations['feedback_required_field']); ?></div>
        </div>

        <div class="form-group">
            <label for="age-display"><?php echo htmlspecialchars($translations['label_age']); ?></label>
            <input type="text" id="age-display" class="form-control" value="<?php echo htmlspecialchars($age); ?>" readonly>
        </div>

        <div class="form-group full-width">
            <label><?php echo htmlspecialchars($translations['label_languages']); ?></label>
            <div class="language-columns">
                <?php foreach ($all_languages as $lang): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="languages[]" value="<?php echo htmlspecialchars($lang['code']); ?>"
                               <?php echo in_array($lang['code'], $current_languages) ? 'checked' : ''; ?>>
                        <span class="control-indicator"></span>
                        <?php echo htmlspecialchars($lang['name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</fieldset>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function sprintf(formatString, ...args) {
        let i = 0;
        return formatString.replace(/%(?:(\d+)\$)?([sd])/g, function(match, index, type) {
            index = index ? parseInt(index) - 1 : i++;
            const arg = args[index];
            if (type === 's') return String(arg);
            if (type === 'd') return parseInt(arg, 10);
            return match;
        });
    }

    function setupCharacterCounter(inputId, counterId, rules) {
        const inputElement = document.getElementById(inputId);
        const counterElement = document.getElementById(counterId);
        if (!inputElement || !counterElement) return;

        const translations = window.appConfig.translations || {};
        const formatString = translations['char_count_format'] || '{current}/{max} caracteres';
        const minCharsFeedback = translations['min_chars_feedback'] || 'Mínimo de {min} caracteres.';
        const maxCharsFeedback = translations['max_chars_feedback'] || 'Máximo de {max} caracteres.';

        const updateCounter = () => {
            const currentLength = inputElement.value.length;
            const minLength = rules.min || 0;
            const maxLength = rules.max;
            counterElement.textContent = sprintf(formatString, currentLength, maxLength);

            const invalidFeedbackElement = inputElement.nextElementSibling;
            let isValid = currentLength >= minLength && currentLength <= maxLength;

            counterElement.classList.toggle('is-invalid', !isValid);
            counterElement.classList.toggle('is-valid', isValid);

            if (invalidFeedbackElement) {
                if (!isValid) {
                    invalidFeedbackElement.textContent = sprintf(currentLength < minLength ? minCharsFeedback : maxCharsFeedback, currentLength < minLength ? minLength : maxLength);
                    invalidFeedbackElement.style.display = 'block';
                } else {
                    invalidFeedbackElement.style.display = 'none';
                }
            }
        };

        inputElement.addEventListener('input', updateCounter);
        updateCounter();
    }

    setupCharacterCounter('ad_title', 'char-count-title', { min: 20, max: 80 });
    setupCharacterCounter('description', 'char-count-desc', { min: 300, max: 500 });
});
</script>
