<?php
/**
 * /modules/providers/security.php - Módulo de Segurança e Consentimentos
 *
 * RESPONSABILIDADES:
 * - Exibe os campos de consentimento legal e termos de serviço.
 * - Garante que a estrutura HTML está correta para o layout em grelha.
 * - Todos os textos estáticos são traduzidos.
 *
 * ÚLTIMA ATUALIZAÇÃO: 15/07/2025 13:40 - Adicionado fallback para tradução de module_title_security.
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Garante que a conexão com a base de dados está disponível neste escopo.
$db = getDBConnection();

// Contextos de tradução
$security_form_context = 'provider_security_form'; // Contexto principal para este módulo
$common_options_context = 'common_options'; // Para opções de radio Sim/Não
$validation_errors_context = 'validation_errors'; // Para mensagens de validação
$legal_texts_context = 'legal_texts'; // Para textos de links de termos/políticas

// --- LÓGICA DE PRÉ-PREENCHIMENTO ---
$current_accept_terms = isset($form_data['accept_terms']) ? (bool)$form_data['accept_terms'] : (bool)($provider_data['accept_terms'] ?? 0);
$current_gdpr_consent = isset($form_data['gdpr_consent']) ? (bool)$form_data['gdpr_consent'] : (bool)($provider_data['gdpr_consent'] ?? 0);
$current_legal_declaration = isset($form_data['legal_declaration']) ? (bool)$form_data['legal_declaration'] : (bool)($provider_data['legal_declaration'] ?? 0);
$current_allow_reviews = $form_data['allow_reviews'] ?? $provider_data['allow_reviews'] ?? '1';

// Tradução do título com fallback
$translations['module_title_security'] = getTranslation('module_title_security', $languageCode, $security_form_context) ?? 'Segurança e Consentimentos';

?>

<fieldset class="form-module">
    <legend><?php echo htmlspecialchars($translations['module_title_security']); ?></legend>

    <div class="form-grid">
        <div class="form-group checkbox-group legal-consent full-width">
            <label class="checkbox-label" for="accept_terms">
                <input type="checkbox" id="accept_terms" name="accept_terms" value="1" <?php echo $current_accept_terms ? 'checked' : ''; ?> required>
                <span class="control-indicator"></span>
                <?php 
                    $terms_link_text = htmlspecialchars(getTranslation('link_text_terms_conditions', $languageCode, $legal_texts_context));
                    $terms_label = htmlspecialchars(getTranslation('label_accept_terms', $languageCode, $security_form_context));
                    // Usa printf para inserir o link traduzido no texto traduzido
                    printf($terms_label, '<a href="/terms" target="_blank">' . $terms_link_text . '</a>');
                ?>
            </label>
            <div class="invalid-feedback"><?php echo htmlspecialchars(getTranslation('feedback_required_field', $languageCode, $validation_errors_context)); ?></div>
        </div>

        <div class="form-group checkbox-group legal-consent full-width">
            <label class="checkbox-label" for="gdpr_consent">
                <input type="checkbox" id="gdpr_consent" name="gdpr_consent" value="1" <?php echo $current_gdpr_consent ? 'checked' : ''; ?> required>
                <span class="control-indicator"></span>
                <?php 
                    $privacy_link_text = htmlspecialchars(getTranslation('link_text_privacy_policy', $languageCode, $legal_texts_context));
                    $gdpr_label = htmlspecialchars(getTranslation('label_gdpr_consent', $languageCode, $security_form_context));
                    // Usa printf para inserir o link traduzido no texto traduzido
                    printf($gdpr_label, '<a href="/privacy" target="_blank">' . $privacy_link_text . '</a>');
                ?>
            </label>
            <div class="invalid-feedback"><?php echo htmlspecialchars(getTranslation('feedback_required_field', $languageCode, $validation_errors_context)); ?></div>
        </div>

        <div class="form-group checkbox-group legal-consent full-width">
            <label class="checkbox-label" for="legal_declaration">
                <input type="checkbox" id="legal_declaration" name="legal_declaration" value="1" <?php echo $current_legal_declaration ? 'checked' : ''; ?> required>
                <span class="control-indicator"></span>
                <?php echo htmlspecialchars(getTranslation('label_legal_declaration', $languageCode, $security_form_context)); ?>
            </label>
            <div class="invalid-feedback"><?php echo htmlspecialchars(getTranslation('feedback_required_field', $languageCode, $validation_errors_context)); ?></div>
        </div>
        
        <div class="form-group">
            <label class="group-title"><i class="fas fa-star"></i> <?php echo htmlspecialchars(getTranslation('label_allow_reviews', $languageCode, $security_form_context)); ?></label>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="allow_reviews" value="1" <?php echo $current_allow_reviews == '1' ? 'checked' : ''; ?>>
                    <span class="control-indicator"></span> <?php echo htmlspecialchars(getTranslation('option_yes', $languageCode, $common_options_context)); ?>
                </label>
                <label class="radio-label">
                    <input type="radio" name="allow_reviews" value="0" <?php echo $current_allow_reviews == '0' ? 'checked' : ''; ?>>
                    <span class="control-indicator"></span> <?php echo htmlspecialchars(getTranslation('option_no', $languageCode, $common_options_context)); ?>
                </label>
            </div>
        </div>
    </div>
</fieldset>