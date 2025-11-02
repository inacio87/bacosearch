<?php
/**
 * /modules/providers/body.php - Módulo de Características Físicas (VERSÃO FINAL E AJUSTADA)
 *
 * ÚLTIMA ATUALIZAÇÃO: 13/07/2025 - Refatorado para robustez, tradução completa e boas práticas.
 * AJUSTE: O título do módulo agora é passado do arquivo pai (register_providers.php).
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Lógica de pré-preenchimento segura para todos os campos
$provider_data = $form_data ?? $user_data ?? []; // Lógica de fallback mais clara

// Carrega os valores atuais ou define padrões
$current_height = $provider_data['height'] ?? '165';
$current_weight = $provider_data['weight'] ?? '60';
$current_foot_size = $provider_data['foot_size'] ?? '38';
$current_eye_color = $provider_data['eye_color'] ?? '';
$current_hair_color = $provider_data['hair_color'] ?? '';
$current_hair_length = $provider_data['hair_length'] ?? '';
$current_pubic_hair = $provider_data['pubic_hair'] ?? '';
$current_body_type = $provider_data['body_type'] ?? '';
$current_ethnicity = $provider_data['ethnicity'] ?? '';
$current_bust_size = $provider_data['bust_size'] ?? '';
$current_sexual_orientation = $provider_data['sexual_orientation'] ?? '';

// AJUSTE: Simplificado para trabalhar com 1 e 0 diretamente
$current_has_silicone = $provider_data['has_silicone'] ?? 0;
$current_is_smoker = $provider_data['is_smoker'] ?? 'not_specified'; // Mantém 3 estados
$current_has_piercings = $provider_data['has_piercings'] ?? 0;
$current_has_tattoos = $provider_data['has_tattoos'] ?? 0;

// Contextos de tradução - REVERTIDO PARA O ORIGINAL para os labels
$body_form_context = 'provider_body_form'; // MANTEM ESTE CONTEXTO PARA OS LABELS DO MÓDULO
$common_form_context = 'common_form';
$common_options_context = 'common_options';

// Arrays de opções com as chaves de tradução
$eye_colors = ['castanho' => 'eye_color_brown', 'azul' => 'eye_color_blue', 'verde' => 'eye_color_green', 'mel' => 'eye_color_honey', 'preto' => 'eye_color_black'];
$hair_colors = ['preto' => 'hair_color_black', 'castanho' => 'hair_color_brown', 'loiro' => 'hair_color_blonde', 'ruivo' => 'hair_color_red', 'outro' => 'hair_color_other'];
$hair_lengths = ['curto' => 'hair_length_short', 'medio' => 'hair_length_medium', 'longo' => 'hair_length_long', 'muito_longo' => 'hair_length_very_long'];
$pubic_hair_styles = ['depilado' => 'pubic_hair_shaved', 'aparado' => 'pubic_hair_trimmed', 'natural' => 'pubic_hair_natural'];
$body_types = ['magro' => 'body_type_thin', 'atlético' => 'body_type_athletic', 'normal' => 'body_type_normal', 'curvilíneo' => 'body_type_curvy', 'robusto' => 'body_type_robust'];
$ethnicities = ['caucasiano' => 'ethnicity_caucasian', 'negro' => 'ethnicity_black', 'asiático' => 'ethnicity_asian', 'hispânico' => 'ethnicity_hispanic', 'misto' => 'ethnicity_mixed'];
$sexual_orientations = ['heterossexual' => 'orientation_heterosexual', 'bissexual' => 'orientation_bisexual', 'homossexual' => 'orientation_homosexual', 'pansexual' => 'orientation_pansexual', 'outra' => 'orientation_other'];
// AJUSTE: 'bust_size' agora segue o padrão de tradução
$bust_sizes = ['small' => 'bust_size_small', 'medium' => 'bust_size_medium', 'large' => 'bust_size_large', 'extra_large' => 'bust_size_extra_large'];

/**
 * FUNÇÃO AUXILIAR: Gera um grupo de botões de rádio para reduzir a repetição de código.
 * @param string $name - O atributo 'name' para os inputs de rádio.
 * @param string $label_key - A chave de tradução para o label do grupo.
 * @param string $icon_class - A classe do ícone Font Awesome.
 * @param mixed $current_value - O valor atualmente selecionado.
 * @param string $languageCode - O código do idioma atual.
 * @param string $body_form_context - O contexto de tradução do formulário.
 * @param string $common_options_context - O contexto de tradução para 'sim' e 'não'.
 */
function render_radio_group($name, $label_key, $icon_class, $current_value, $languageCode, $body_form_context, $common_options_context) {
    $yes_text = htmlspecialchars(getTranslation('option_yes', $languageCode, $common_options_context));
    $no_text = htmlspecialchars(getTranslation('option_no', $languageCode, $common_options_context));
    $label_text = htmlspecialchars(getTranslation($label_key, $languageCode, $body_form_context));

    $yes_checked = ($current_value == 1) ? 'checked' : '';
    $no_checked = ($current_value == 0) ? 'checked' : '';

    echo "
    <div class='form-group'>
        <label class='group-title'><i class='fas {$icon_class}'></i> {$label_text}</label>
        <div class='radio-group horizontal'>
            <label class='radio-label'>
                <input type='radio' name='{$name}' value='1' {$yes_checked}>
                <span class='control-indicator'></span> {$yes_text}
            </label>
            <label class='radio-label'>
                <input type='radio' name='{$name}' value='0' {$no_checked}>
                <span class='control-indicator'></span> {$no_text}
            </label>
        </div>
    </div>";
}
?>

<style>
    /* Estilos específicos para este módulo, se necessário */
    .three-column-slider-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-lg, 2rem);
        align-items: start;
        margin-bottom: var(--spacing-lg, 2rem);
    }
    @media (max-width: 768px) {
        .three-column-slider-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<fieldset class="form-module">
    <legend><?php echo htmlspecialchars($module_current_translations['module_title_body'] ?? getTranslation('module_title_body', $languageCode, 'provider_form')); ?></legend>

    <div class="three-column-slider-grid full-width">
        <div class="form-group slider-group">
            <label class="group-title" for="height"><i class="fas fa-ruler-vertical"></i> <?php echo htmlspecialchars(getTranslation('label_height', $languageCode, $body_form_context)); ?></label>
            <input type="range" id="height" name="height" step="1" min="140" max="210" value="<?php echo htmlspecialchars($current_height); ?>" class="form-control-range">
            <div class="slider-value"><span id="height-display-metric"></span> / <span id="height-display-imperial"></span></div>
        </div>
        <div class="form-group slider-group">
            <label class="group-title" for="weight"><i class="fas fa-weight-hanging"></i> <?php echo htmlspecialchars(getTranslation('label_weight', $languageCode, $body_form_context)); ?></label>
            <input type="range" id="weight" name="weight" step="1" min="40" max="150" value="<?php echo htmlspecialchars($current_weight); ?>" class="form-control-range">
            <div class="slider-value"><span id="weight-display-metric"></span> / <span id="weight-display-imperial"></span></div>
        </div>
        <div class="form-group slider-group">
            <label class="group-title" for="foot_size"><i class="fas fa-shoe-prints"></i> <?php echo htmlspecialchars(getTranslation('label_foot_size', $languageCode, $body_form_context)); ?></label>
            <input type="range" id="foot_size" name="foot_size" step="1" min="30" max="48" value="<?php echo htmlspecialchars($current_foot_size); ?>" class="form-control-range">
            <div class="slider-value"><span id="foot-size-display"></span></div>
        </div>
    </div>

    <div class="form-grid">
        <?php
        $dropdowns = [
            'sexual_orientation' => ['label_key' => 'label_sexual_orientation', 'icon' => 'fa-heart', 'options' => $sexual_orientations, 'current' => $current_sexual_orientation],
            'eye_color' => ['label_key' => 'label_eye_color', 'icon' => 'fa-eye', 'options' => $eye_colors, 'current' => $current_eye_color],
            'hair_color' => ['label_key' => 'label_hair_color', 'icon' => 'fa-user', 'options' => $hair_colors, 'current' => $current_hair_color],
            'hair_length' => ['label_key' => 'label_hair_length', 'icon' => 'fa-cut', 'options' => $hair_lengths, 'current' => $current_hair_length],
            'pubic_hair' => ['label_key' => 'label_pubic_hair', 'icon' => 'fa-venus-mars', 'options' => $pubic_hair_styles, 'current' => $current_pubic_hair],
            'body_type' => ['label_key' => 'label_body_type', 'icon' => 'fa-child', 'options' => $body_types, 'current' => $current_body_type],
            'ethnicity' => ['label_key' => 'label_ethnicity', 'icon' => 'fa-globe-americas', 'options' => $ethnicities, 'current' => $current_ethnicity],
            'bust_size' => ['label_key' => 'label_bust_size', 'icon' => 'fa-female', 'options' => $bust_sizes, 'current' => $current_bust_size]
        ];
        ?>
        <?php foreach ($dropdowns as $name => $details): ?>
            <div class="form-group">
                <label class="group-title" for="<?php echo $name; ?>"><i class="fas <?php echo $details['icon']; ?>"></i> <?php echo htmlspecialchars(getTranslation($details['label_key'], $languageCode, $body_form_context)); ?></label>
                <select id="<?php echo $name; ?>" name="<?php echo $name; ?>" class="form-control">
                    <option value=""><?php echo htmlspecialchars(getTranslation('option_select_default', $languageCode, $common_form_context)); ?></option>
                    <?php foreach ($details['options'] as $value => $key): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $details['current'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars(getTranslation($key, $languageCode, $common_options_context)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>

        <?php render_radio_group('has_silicone', 'label_has_silicone', 'fa-plus-circle', $current_has_silicone, $languageCode, $body_form_context, $common_options_context); ?>
        <?php render_radio_group('has_piercings', 'label_has_piercings', 'fa-ring', $current_has_piercings, $languageCode, $body_form_context, $common_options_context); ?>
        <?php render_radio_group('has_tattoos', 'label_has_tattoos', 'fa-pen-fancy', $current_has_tattoos, $languageCode, $body_form_context, $common_options_context); ?>

        <div class="form-group">
            <label class="group-title"><i class="fas fa-smoking"></i> <?php echo htmlspecialchars(getTranslation('label_is_smoker', $languageCode, $body_form_context)); ?></label>
            <div class="radio-group horizontal">
                <label class="radio-label">
                    <input type="radio" name="is_smoker" value="yes" <?php echo $current_is_smoker === 'yes' ? 'checked' : ''; ?>>
                    <span class="control-indicator"></span> <?php echo htmlspecialchars(getTranslation('option_yes', $languageCode, $common_options_context)); ?>
                </label>
                <label class="radio-label">
                    <input type="radio" name="is_smoker" value="no" <?php echo $current_is_smoker === 'no' ? 'checked' : ''; ?>>
                    <span class="control-indicator"></span> <?php echo htmlspecialchars(getTranslation('option_no', $languageCode, $common_options_context)); ?>
                </label>
                <label class="radio-label">
                    <input type="radio" name="is_smoker" value="not_specified" <?php echo $current_is_smoker === 'not_specified' ? 'checked' : ''; ?>>
                    <span class="control-indicator"></span> <?php echo htmlspecialchars(getTranslation('option_not_specified', $languageCode, $common_options_context)); ?>
                </label>
            </div>
        </div>
    </div>
</fieldset>

<script>
document.addEventListener('DOMContentLoaded', function() {
    /**
     * Configura um controle deslizante (slider) com atualizações de display.
     * @param {string} sliderId - O ID do elemento input range.
     * @param {function} updateFn - A função para chamar e atualizar o display.
     */
    function initializeSlider(sliderId, updateFn) {
        const slider = document.getElementById(sliderId);
        if (slider) {
            slider.addEventListener('input', updateFn);
            updateFn(); // Chama uma vez para definir o valor inicial
        }
    }

    // Funções de atualização de display
    const updateHeightDisplay = () => {
        const cm = document.getElementById('height').value;
        const totalInches = cm / 2.54;
        document.getElementById('height-display-metric').textContent = `${cm} cm`;
        document.getElementById('height-display-imperial').textContent = `${Math.floor(totalInches / 12)}'${Math.round(totalInches % 12)}"`;
    };

    const updateWeightDisplay = () => {
        const kg = document.getElementById('weight').value;
        document.getElementById('weight-display-metric').textContent = `${kg} kg`;
        document.getElementById('weight-display-imperial').textContent = `${Math.round(kg * 2.20462)} lbs`;
    };

    const updateFootSizeDisplay = () => {
        document.getElementById('foot-size-display').textContent = document.getElementById('foot_size').value;
    };

    // Inicialização dos sliders
    initializeSlider('height', updateHeightDisplay);
    initializeSlider('weight', updateWeightDisplay);
    initializeSlider('foot_size', updateFootSizeDisplay);
});
</script>