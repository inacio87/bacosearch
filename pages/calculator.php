<?php
/**
 * /pages/calculator.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$page_name = 'license_calculator';
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');
$countryCode = isset($_SESSION['country_code']) ? $_SESSION['country_code'] : 'PT';

$lead_data = [
    'name' => '', 'email' => '', 'phone_code' => '+351', 'phone_number' => '',
    'country_of_interest' => '', 'state_of_interest' => '', 'digital_experience' => '',
    'how_did_you_hear' => '', 'message' => ''
];

$initial_lead_data_from_token = [];
$access_granted = false;
$current_access_token = null;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $current_access_token = htmlspecialchars($_GET['token']);
    try {
        $db = getDBConnection();
        $stmt = $db->prepare(
            "SELECT id, name, email, lead_token, token_status, token_expires_at, details FROM leads WHERE lead_token = :token LIMIT 1"
        );
        $stmt->execute([':token' => $current_access_token]);
        $token_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token_info) {
            $now = new DateTime();
            $expiry_date = new DateTime($token_info['token_expires_at']);
            $lead_details = is_string($token_info['details']) ? json_decode($token_info['details'], true) : (isset($token_info['details']) ? $token_info['details'] : []);

            if ($token_info['token_status'] === 'active' && $now < $expiry_date) {
                $access_granted = true;
                $initial_lead_data_from_token = [
                    'name' => $token_info['name'],
                    'email' => $token_info['email'],
                    'phone_code' => isset($lead_details['phone_code']) ? $lead_details['phone_code'] : null,
                    'phone_number' => isset($lead_details['phone_number']) ? $lead_details['phone_number'] : null,
                    'country_of_interest' => isset($lead_details['country_of_interest']) ? $lead_details['country_of_interest'] : null,
                    'state_of_interest' => isset($lead_details['state_of_interest']) ? $lead_details['state_of_interest'] : null,
                    'digital_experience' => isset($lead_details['digital_experience']) ? $lead_details['digital_experience'] : null,
                    'how_did_you_hear' => isset($lead_details['how_did_you_hear']) ? $lead_details['how_did_you_hear'] : null,
                    'message' => isset($lead_details['message']) ? $lead_details['message'] : null,
                ];
                $_SESSION['current_calculator_token'] = $current_access_token;
            } else {
                $_SESSION['errors_calculator_access'] = getTranslation('error_token_invalid_or_expired', $languageCode, 'ui_messages');
            }
        } else {
            $_SESSION['errors_calculator_access'] = getTranslation('error_token_not_found', $languageCode, 'ui_messages');
        }
    } catch (Exception $e) {
        log_system_error("Calculadora: Erro ao validar token: " . $e->getMessage(), 'CRITICAL', 'calculator_token_validation_exception');
        $_SESSION['errors_calculator_access'] = getTranslation('error_general_access', $languageCode, 'ui_messages');
    }
} else {
    $_SESSION['errors_calculator_access'] = getTranslation('error_access_no_token', $languageCode, 'ui_messages');
}

if (!$access_granted) {
    header('Location: ' . SITE_URL . '/pages/license.php?status=calculator_access_denied');
    exit;
}

$session_form_data = isset($_SESSION['form_data_calculator']) ? $_SESSION['form_data_calculator'] : [];
$lead_data = array_merge($lead_data, $session_form_data, $initial_lead_data_from_token);

$form_feedback_errors = isset($_SESSION['form_feedback_errors']) ? $_SESSION['form_feedback_errors'] : [];
$form_feedback_success = isset($_SESSION['form_feedback_success']) ? $_SESSION['form_feedback_success'] : false;
unset($_SESSION['form_feedback_errors'], $_SESSION['form_feedback_success'], $_SESSION['form_data_calculator']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$calculator_configs_db = [];
try {
    $db = getDBConnection();
    $stmt_configs = $db->query("SELECT key_name, value_numeric FROM license_configs");
    $raw_configs = $stmt_configs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw_configs as $config) {
        $calculator_configs_db[$config['key_name']] = (float)$config['value_numeric'];
    }
} catch (Exception $e) {
    log_system_error("Calculadora: Erro ao carregar configs do DB: " . $e->getMessage(), 'CRITICAL', 'calculator_configs_db_load_failure');
    $calculator_configs_db = [
        'product_price_individuals' => 14.90, 'product_price_locals' => 19.90, 'product_price_services' => 9.90,
        'stripe_fee_percent' => 0.035, 'taxes_percent' => 0.15, 'system_fee_percent' => 0.03, 'marketing_fee_percent' => 0.035,
        'licensee_share_percent' => 0.80, 'initial_investment_cost' => 1500.00
    ];
}

$product_prices = [
    'individuals' => $calculator_configs_db['product_price_individuals'],
    'locals' => $calculator_configs_db['product_price_locals'],
    'services' => $calculator_configs_db['product_price_services']
];
$fees_and_taxes = [
    'stripe_fee_percent' => $calculator_configs_db['stripe_fee_percent'],
    'taxes_percent' => $calculator_configs_db['taxes_percent'],
    'system_fee_percent' => $calculator_configs_db['system_fee_percent'],
    'marketing_fee_percent' => $calculator_configs_db['marketing_fee_percent']
];
$license_share_percent = $calculator_configs_db['licensee_share_percent'];
$initial_investment_cost = $calculator_configs_db['initial_investment_cost'];

$countries_list = [];
$phoneCodes = [];
try {
    $db = getDBConnection();
    $stmt_countries_ddi = $db->query("SELECT id, name, iso_code, calling_code FROM countries WHERE calling_code IS NOT NULL AND calling_code != '' ORDER BY name ASC");
    $countries_data = $stmt_countries_ddi->fetchAll(PDO::FETCH_ASSOC);
    foreach ($countries_data as $country) {
        $countries_list[] = ['id' => $country['id'], 'iso_code' => $country['iso_code'], 'country' => $country['name']];
        $phoneCodes[] = ['calling_code' => $country['calling_code'], 'iso_code' => strtolower($country['iso_code']), 'name' => $country['name'], 'flag_url' => SITE_URL . '/assets/images/flags/' . strtolower($country['iso_code']) . '.svg'];
    }
} catch (Exception $e) {
    log_system_error("Calculator Page: Erro ao carregar países: " . $e->getMessage(), 'ERROR', 'calculator_countries_load_failure');
}

$selectedPhoneCodeValue = isset($lead_data['phone_code']) ? $lead_data['phone_code'] : null;
if (empty($selectedPhoneCodeValue)) {
    try {
        $db = getDBConnection();
        $countryOfInterest = isset($lead_data['country_of_interest']) ? $lead_data['country_of_interest'] : (isset($_SESSION['country_code']) ? $_SESSION['country_code'] : 'PT');
        $currentCountryCodeForDDI = strtoupper($countryOfInterest);
        $stmt_default_ddi = $db->prepare("SELECT calling_code FROM countries WHERE iso_code = :countryCode LIMIT 1");
        $stmt_default_ddi->execute([':countryCode' => $currentCountryCodeForDDI]);
        $result = $stmt_default_ddi->fetchColumn();
        $selectedPhoneCodeValue = $result ? $result : '+351';
    } catch (Exception $e) {
        $selectedPhoneCodeValue = '+351';
    }
}

$initial_individuals_count = isset($lead_data['individuals_count']) ? $lead_data['individuals_count'] : 10;
$initial_locals_count = isset($lead_data['locals_count']) ? $lead_data['locals_count'] : 5;
$initial_services_count = isset($lead_data['services_count']) ? $lead_data['services_count'] : 15;

function calculateFinancialResults($individuals_count, $locals_count, $services_count, $product_prices, $fees_and_taxes, $license_share_percent, $initial_investment_cost) {
    $monthly_gross_revenue = ($individuals_count * $product_prices['individuals']) + ($locals_count * $product_prices['locals']) + ($services_count * $product_prices['services']);
    $stripe_fee = $monthly_gross_revenue * $fees_and_taxes['stripe_fee_percent'];
    $taxes = $monthly_gross_revenue * $fees_and_taxes['taxes_percent'];
    $system_fee = $monthly_gross_revenue * $fees_and_taxes['system_fee_percent'];
    $marketing_fee = $monthly_gross_revenue * $fees_and_taxes['marketing_fee_percent'];
    $total_discounts = $stripe_fee + $taxes + $system_fee + $marketing_fee;
    $monthly_net_revenue = $monthly_gross_revenue - $total_discounts;
    $licensee_monthly_share = $monthly_net_revenue * $license_share_percent;
    $annual_licensee_gross_profit = $licensee_monthly_share * 12;
    $annual_net_profit_after_investment = $annual_licensee_gross_profit - $initial_investment_cost;
    return [
        'monthly_gross_revenue' => number_format($monthly_gross_revenue, 2, ',', '.'), 'stripe_fee' => number_format($stripe_fee, 2, ',', '.'), 'taxes' => number_format($taxes, 2, ',', '.'),
        'system_fee' => number_format($system_fee, 2, ',', '.'), 'marketing_fee' => number_format($marketing_fee, 2, ',', '.'), 'total_discounts' => number_format($total_discounts, 2, ',', '.'),
        'monthly_net_revenue' => number_format($monthly_net_revenue, 2, ',', '.'), 'licensee_monthly_share' => number_format($licensee_monthly_share, 2, ',', '.'),
        'annual_licensee_gross_profit' => number_format($annual_licensee_gross_profit, 2, ',', '.'), 'initial_investment_cost' => number_format($initial_investment_cost, 2, ',', '.'),
        'annual_net_profit_after_investment' => number_format($annual_net_profit_after_investment, 2, ',', '.'),
        'roi' => ($initial_investment_cost > 0) ? number_format(($annual_net_profit_after_investment / $initial_investment_cost) * 100, 2, ',', '.') : 'N/A'
    ];
}

$financial_results = calculateFinancialResults($initial_individuals_count, $initial_locals_count, $initial_services_count, $product_prices, $fees_and_taxes, $license_share_percent, $initial_investment_cost);

$calculator_configs = ['product_prices' => $product_prices, 'fees_and_taxes' => $fees_and_taxes, 'license_share_percent' => $license_share_percent, 'initial_investment_cost' => $initial_investment_cost];
$initial_input_values = ['individuals' => $initial_individuals_count, 'locals' => $initial_locals_count, 'services' => $initial_services_count];

$translations = [];
$keys_to_translate = [
    'license_calculator_page_title', 'license_calculator_page_description', 'license_calculator_intro_title', 'license_calculator_intro_subtitle', 'license_calculator_intro_paragraph',
    'license_calculator_section_title_projection', 'label_calc_individuals', 'label_calc_locals', 'label_calc_services', 'license_calculator_section_title_results', 'label_monthly_breakdown', 'label_monthly_gross_revenue', 'label_discounts',
    'label_stripe_fee', 'label_taxes', 'label_system_fee', 'label_marketing_fee', 'label_total_discounts', 'label_net_monthly', 'label_licensee_share', 'label_annual_breakdown', 'label_annual_licensee_result', 'label_initial_investment_cost', 'label_your_annual_net_profit', 'label_estimated_roi',
    'license_feedback_form_title', 'license_feedback_form_subtitle', 'label_name', 'label_email', 'label_phone', 'label_country_of_interest', 'label_state_of_interest', 'label_digital_experience',
    'option_digital_yes', 'option_digital_no', 'option_digital_some', 'label_how_did_you_hear', 'option_how_digital_ads', 'option_how_social_media', 'option_how_friend', 'option_how_event', 'option_how_other', 'label_message', 'button_send_feedback', 'select_option',
    'error_form_correction', 'js_saving_text', 'js_error_prefix', 'js_comm_error',
    'logo_alt', 'header_ads', 'header_login', 'header_menu'
];

foreach ($keys_to_translate as $key) {
    $context = 'calculator_page';
    if (strpos($key, 'label_') === 0 || strpos($key, 'option_') === 0 || strpos($key, 'button_') === 0 || $key === 'select_option') {
        $context = 'calculator_form';
    } elseif (strpos($key, 'error_') === 0) {
        $context = 'validation_errors';
    } elseif (strpos($key, 'js_') === 0) {
        $context = 'ui_messages';
    } elseif (in_array($key, ['logo_alt', 'header_ads', 'header_login', 'header_menu'])) {
        $context = 'header';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$page_title = !empty($translations['license_calculator_page_title']) ? $translations['license_calculator_page_title'] : 'license_calculator_page_title';
$meta_description = !empty($translations['license_calculator_page_description']) ? $translations['license_calculator_page_description'] : '';

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<main>
    <div class="calculator-page-container">
        <section class="intro-section">
            <h1><?= $e(isset($translations['license_calculator_intro_title']) ? $translations['license_calculator_intro_title'] : ''); ?></h1>
            <p class="subtitle"><?= $e(isset($translations['license_calculator_intro_subtitle']) ? $translations['license_calculator_intro_subtitle'] : ''); ?></p>
            <p><?= $e(isset($translations['license_calculator_intro_paragraph']) ? $translations['license_calculator_intro_paragraph'] : ''); ?></p>
        </section>
        <section class="calc-section">
            <h2><?= $e(isset($translations['license_calculator_section_title_projection']) ? $translations['license_calculator_section_title_projection'] : ''); ?></h2>
            <div class="input-group">
                <label for="calc-individuals"><?= $e(isset($translations['label_calc_individuals']) ? $translations['label_calc_individuals'] : ''); ?></label>
                <input type="number" id="calc-individuals" min="0" value="<?= $e($initial_individuals_count); ?>">
            </div>
            <div class="input-group">
                <label for="calc-locals"><?= $e(isset($translations['label_calc_locals']) ? $translations['label_calc_locals'] : ''); ?></label>
                <input type="number" id="calc-locals" min="0" value="<?= $e($initial_locals_count); ?>">
            </div>
            <div class="input-group">
                <label for="calc-services"><?= $e(isset($translations['label_calc_services']) ? $translations['label_calc_services'] : ''); ?></label>
                <input type="number" id="calc-services" min="0" value="<?= $e($initial_services_count); ?>">
            </div>
        </section>
        <section class="calc-section results-section">
            <h2><i class="fas fa-calculator"></i> <?= $e(isset($translations['license_calculator_section_title_results']) ? $translations['license_calculator_section_title_results'] : ''); ?></h2>
            <div class="results-container">
                <div class="results-column monthly-breakdown">
                    <h3><?= $e(isset($translations['label_monthly_breakdown']) ? $translations['label_monthly_breakdown'] : ''); ?></h3>
                    <div class="result-group">
                        <div class="result-item total-gross-revenue">
                            <strong><?= $e(isset($translations['label_monthly_gross_revenue']) ? $translations['label_monthly_gross_revenue'] : ''); ?></strong>
                            <span id="res-receita-mensal" class="currency-value">€ <?= $e($financial_results['monthly_gross_revenue']); ?></span>
                        </div>
                    </div>
                    <div class="result-group discounts-section">
                        <strong><?= $e(isset($translations['label_discounts']) ? $translations['label_discounts'] : ''); ?></strong>
                        <ul>
                            <li><?= $e(isset($translations['label_stripe_fee']) ? $translations['label_stripe_fee'] : ''); ?> <span id="res-stripe" class="currency-value">€ <?= $e($financial_results['stripe_fee']); ?></span></li>
                            <li><?= $e(isset($translations['label_taxes']) ? $translations['label_taxes'] : ''); ?> <span id="res-imposto" class="currency-value">€ <?= $e($financial_results['taxes']); ?></span></li>
                            <li><?= $e(isset($translations['label_system_fee']) ? $translations['label_system_fee'] : ''); ?> <span id="res-sistema" class="currency-value">€ <?= $e($financial_results['system_fee']); ?></span></li>
                            <li><?= $e(isset($translations['label_marketing_fee']) ? $translations['label_marketing_fee'] : ''); ?> <span id="res-marketing" class="currency-value">€ <?= $e($financial_results['marketing_fee']); ?></span></li>
                        </ul>
                    </div>
                    <div class="result-group">
                        <div class="result-item total-discounts">
                            <strong><?= $e(isset($translations['label_total_discounts']) ? $translations['label_total_discounts'] : ''); ?></strong>
                            <span id="res-total-descontos" class="currency-value">€ <?= $e($financial_results['total_discounts']); ?></span>
                        </div>
                        <div class="result-item net-monthly-revenue">
                            <strong><?= $e(isset($translations['label_net_monthly']) ? $translations['label_net_monthly'] : ''); ?></strong>
                            <span id="res-receita-liquida" class="currency-value">€ <?= $e($financial_results['monthly_net_revenue']); ?></span>
                        </div>
                    </div>
                    <div class="result-group final-shares">
                        <div class="result-item licensee-monthly-share">
                            <strong><?= $e(isset($translations['label_licensee_share']) ? $translations['label_licensee_share'] : ''); ?></strong>
                            <span id="res-licenciado-share" class="currency-value">€ <?= $e($financial_results['licensee_monthly_share']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="results-column annual-breakdown">
                    <h3><?= $e(isset($translations['label_annual_breakdown']) ? $translations['label_annual_breakdown'] : ''); ?></h3>
                    <div class="result-group">
                        <div class="result-item annual-licensee-gross">
                            <strong><?= $e(isset($translations['label_annual_licensee_result']) ? $translations['label_annual_licensee_result'] : ''); ?></strong>
                            <span id="res-resultado-licenciado" class="currency-value">€ <?= $e($financial_results['annual_licensee_gross_profit']); ?></span>
                        </div>
                        <div class="result-item initial-investment">
                            <strong><?= $e(isset($translations['label_initial_investment_cost']) ? $translations['label_initial_investment_cost'] : ''); ?></strong>
                            <span id="res-investimento" class="currency-value">€ <?= $e($financial_results['initial_investment_cost']); ?></span>
                        </div>
                    </div>
                    <div class="result-item highlight final-annual-profit">
                        <strong><?= $e(isset($translations['label_your_annual_net_profit']) ? $translations['label_your_annual_net_profit'] : ''); ?></strong>
                        <span id="res-lucro-liquido" class="currency-value">€ <?= $e($financial_results['annual_net_profit_after_investment']); ?></span>
                        <?php if ($financial_results['roi'] !== 'N/A'): ?>
                            <small id="res-roi-after-investment" class="roi-text"><?= $e(isset($translations['label_estimated_roi']) ? $translations['label_estimated_roi'] : ''); ?> <?= $e($financial_results['roi']); ?>%</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
        <section class="feedback-form-container">
            <?php if (isset($form_feedback_success) && $form_feedback_success): ?>
                <div class="success-message">
                    <p><?= $e(isset($translations['license_form_feedback_success_message']) ? $translations['license_form_feedback_success_message'] : ''); ?></p>
                </div>
            <?php else: ?>
                <h2><?= $e(isset($translations['license_feedback_form_title']) ? $translations['license_feedback_form_title'] : ''); ?></h2>
                <p><?= $e(isset($translations['license_feedback_form_subtitle']) ? $translations['license_feedback_form_subtitle'] : ''); ?></p>
                <?php if (!empty($form_feedback_errors)): ?>
                    <div class="error-message show">
                        <strong><?= $e(isset($translations['error_form_correction']) ? $translations['error_form_correction'] : ''); ?></strong>
                        <ul>
                            <?php foreach ($form_feedback_errors as $error): ?>
                                <li><?= $e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form id="feedback-form" action="<?= $e(SITE_URL . '/api/process_calculator_data.php'); ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $e(isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''); ?>">
                    <input type="hidden" name="form_token_hidden" value="<?= $e($current_access_token); ?>">
                    <div class="form-group">
                        <label for="feedback_name"><?= $e(isset($translations['label_name']) ? $translations['label_name'] : ''); ?></label>
                        <input type="text" id="feedback_name" name="name" value="<?= $e(isset($lead_data['name']) ? $lead_data['name'] : ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="feedback_email"><?= $e(isset($translations['label_email']) ? $translations['label_email'] : ''); ?></label>
                        <input type="email" id="feedback_email" name="email" value="<?= $e(isset($lead_data['email']) ? $lead_data['email'] : ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number"><?= $e(isset($translations['label_phone']) ? $translations['label_phone'] : ''); ?></label>
                        <div class="phone-input-group">
                            <select id="phone_code" name="phone_code" required class="form-control phone-code">
                                <?php foreach ($phoneCodes as $code): ?>
                                    <option value="<?= $e($code['calling_code']); ?>" data-flag="<?= $e($code['flag_url']); ?>" <?= ($selectedPhoneCodeValue == $code['calling_code']) ? 'selected' : ''; ?>>
                                        <?= $e($code['calling_code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="tel" id="phone_number" name="phone_number" value="<?= $e(isset($lead_data['phone_number']) ? $lead_data['phone_number'] : ''); ?>" required class="form-control phone-number">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="country_of_interest"><?= $e(isset($translations['label_country_of_interest']) ? $translations['label_country_of_interest'] : ''); ?></label>
                        <select id="country_of_interest" name="country_of_interest" class="form-control">
                            <option value=""><?= $e(isset($translations['select_option']) ? $translations['select_option'] : ''); ?></option>
                            <?php foreach (($countries_list ?? []) as $country): ?>
                                <option value="<?= $e($country['iso_code']); ?>" <?= ((isset($lead_data['country_of_interest']) ? $lead_data['country_of_interest'] : '') === $country['iso_code']) ? 'selected' : ''; ?>>
                                    <?= $e($country['country']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="state_of_interest"><?= $e(isset($translations['label_state_of_interest']) ? $translations['label_state_of_interest'] : ''); ?></label>
                        <input type="text" id="state_of_interest" name="state_of_interest" value="<?= $e(isset($lead_data['state_of_interest']) ? $lead_data['state_of_interest'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="digital_experience"><?= $e(isset($translations['label_digital_experience']) ? $translations['label_digital_experience'] : ''); ?></label>
                        <select id="digital_experience" name="digital_experience" class="form-control">
                            <option value=""><?= $e(isset($translations['select_option']) ? $translations['select_option'] : ''); ?></option>
                            <option value="yes" <?= ((isset($lead_data['digital_experience']) ? $lead_data['digital_experience'] : '') === 'yes') ? 'selected' : ''; ?>><?= $e(isset($translations['option_digital_yes']) ? $translations['option_digital_yes'] : ''); ?></option>
                            <option value="no" <?= ((isset($lead_data['digital_experience']) ? $lead_data['digital_experience'] : '') === 'no') ? 'selected' : ''; ?>><?= $e(isset($translations['option_digital_no']) ? $translations['option_digital_no'] : ''); ?></option>
                            <option value="some" <?= ((isset($lead_data['digital_experience']) ? $lead_data['digital_experience'] : '') === 'some') ? 'selected' : ''; ?>><?= $e(isset($translations['option_digital_some']) ? $translations['option_digital_some'] : ''); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="how_did_you_hear"><?= $e(isset($translations['label_how_did_you_hear']) ? $translations['label_how_did_you_hear'] : ''); ?></label>
                        <select id="how_did_you_hear" name="how_did_you_hear" class="form-control">
                            <option value=""><?= $e(isset($translations['select_option']) ? $translations['select_option'] : ''); ?></option>
                            <option value="digital_ads" <?= ((isset($lead_data['how_did_you_hear']) ? $lead_data['how_did_you_hear'] : '') === 'digital_ads') ? 'selected' : ''; ?>><?= $e(isset($translations['option_how_digital_ads']) ? $translations['option_how_digital_ads'] : ''); ?></option>
                            <option value="social_media" <?= ((isset($lead_data['how_did_you_hear']) ? $lead_data['how_did_you_hear'] : '') === 'social_media') ? 'selected' : ''; ?>><?= $e(isset($translations['option_how_social_media']) ? $translations['option_how_social_media'] : ''); ?></option>
                            <option value="friend" <?= ((isset($lead_data['how_did_you_hear']) ? $lead_data['how_did_you_hear'] : '') === 'friend') ? 'selected' : ''; ?>><?= $e(isset($translations['option_how_friend']) ? $translations['option_how_friend'] : ''); ?></option>
                            <option value="event" <?= ((isset($lead_data['how_did_you_hear']) ? $lead_data['how_did_you_hear'] : '') === 'event') ? 'selected' : ''; ?>><?= $e(isset($translations['option_how_event']) ? $translations['option_how_event'] : ''); ?></option>
                            <option value="other" <?= ((isset($lead_data['how_did_you_hear']) ? $lead_data['how_did_you_hear'] : '') === 'other') ? 'selected' : ''; ?>><?= $e(isset($translations['option_how_other']) ? $translations['option_how_other'] : ''); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message"><?= $e(isset($translations['label_message']) ? $translations['label_message'] : ''); ?></label>
                        <textarea id="message" name="message" rows="4"><?= $e(isset($lead_data['message']) ? $lead_data['message'] : ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn-primary"><?= $e(isset($translations['button_send_feedback']) ? $translations['button_send_feedback'] : ''); ?></button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
    const calculatorConfigs = JSON.parse(atob('<?= base64_encode(json_encode($calculator_configs)); ?>'));
    const initialInputValues = JSON.parse(atob('<?= base64_encode(json_encode($initial_input_values)); ?>'));
    const jsTranslations = {
        roi: "<?= $e(isset($translations['label_estimated_roi']) ? $translations['label_estimated_roi'] : ''); ?>",
        phonePlaceholder: "<?= $e(isset($translations['label_phone']) ? $translations['label_phone'] : ''); ?>",
        saving: "<?= $e(isset($translations['js_saving_text']) ? $translations['js_saving_text'] : ''); ?>",
        errorPrefix: "<?= $e(isset($translations['js_error_prefix']) ? $translations['js_error_prefix'] : ''); ?>",
        commError: "<?= $e(isset($translations['js_comm_error']) ? $translations['js_comm_error'] : ''); ?>"
    };
    const selectedPhoneCodeFromPHP = '<?= $e($selectedPhoneCodeValue); ?>';
</script>
<script src="<?= $e(SITE_URL . '/assets/js/license-calculator.js'); ?>"></script>

<?php require_once TEMPLATE_PATH . '/footer.php'; ?>
</body>
</html>