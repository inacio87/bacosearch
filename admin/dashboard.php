<?php
/**
 * /admin/dashboard.php - Painel de Controle Principal (VERSÃO FINAL E COMPATÍVEL)
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . '/');
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$page_name = 'dashboard_admin';
$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'pt-br');

$page_specific_styles = [SITE_URL . '/assets/css/dashboard.css'];

$translations_map = [
    'dashboard_page_title' => 'admin_dashboard', 'welcome_message' => 'admin_dashboard',
    'sidebar_stats' => 'admin_dashboard', 'sidebar_top_lists' => 'admin_dashboard', 'sidebar_users' => 'admin_dashboard', 'sidebar_providers' => 'admin_dashboard',
    'sidebar_businesses' => 'admin_dashboard', 'sidebar_ads' => 'admin_dashboard', 'sidebar_services' => 'sidebar', 'sidebar_translations' => 'admin_dashboard',
    'sidebar_logs' => 'admin_dashboard', 'logout_button' => 'admin_dashboard', 'logo_alt' => 'header', 'header_ads' => 'header', 'header_login' => 'header',
    'header_logout' => 'header', 'header_menu' => 'header', 'about_us' => 'header', 'terms_of_service' => 'header', 'privacy_policy' => 'header',
    'cookie_policy' => 'header', 'contact_us' => 'header', 'header_licenses' => 'header', 'footer_providers' => 'footer', 'footer_companies' => 'footer',
    'footer_streets' => 'footer', 'footer_clubs' => 'footer', 'footer_services' => 'footer', 'footer_explore' => 'footer', 'detecting_location' => 'header',
    'admin_users_title' => 'admin_users', 'filter_all_users' => 'admin_users', 'filter_pending_email' => 'admin_users', 'filter_pending_admin' => 'admin_users',
    'filter_active_users' => 'admin_users', 'filter_suspended_users' => 'admin_users', 'table_header_name' => 'admin_users', 'table_header_email' => 'admin_users',
    'table_header_phone' => 'admin_users', 'table_header_type' => 'admin_users', 'table_header_nationality' => 'admin_users', 'table_header_status' => 'admin_users',
    'table_header_registered' => 'admin_users', 'table_header_actions' => 'admin_users', 'action_approve' => 'admin_users', 'action_reject' => 'admin_users',
    'action_view_details' => 'admin_users', 'action_awaiting_email_verify' => 'admin_users', 'action_resend_email' => 'admin_users', 'no_users_found' => 'admin_users',
    'error_loading_users' => 'admin_users', 'status_pending_email_verification' => 'admin_users', 'status_pending_admin_approval' => 'admin_users',
    'status_active' => 'admin_users', 'status_suspended' => 'admin_users', 'status_rejected' => 'admin_users', 'confirm_approve_user' => 'admin_users',
    'confirm_reject_user' => 'admin_users', 'confirm_resend_email' => 'admin_users', 'user_approved_success' => 'admin_users',
    'user_rejected_success' => 'admin_users', 'email_resend_success' => 'admin_users', 'action_success_fallback' => 'admin_users', 'action_error_fallback' => 'admin_users',
    'error_general_action' => 'admin_users', 'invalid_user_id' => 'admin_users', 'user_not_found_or_already_approved' => 'admin_users',
    'user_not_found_or_status_not_rejectable' => 'admin_users', 'email_not_provided' => 'admin_users', 'user_not_found_or_status_not_resendable' => 'admin_users',
    'invalid_action' => 'admin_users', 'create_new_admin_button' => 'admin_users', 'back_to_users_list' => 'admin_users', 'create_admin_title' => 'admin_users',
    'label_full_name' => 'admin_users', 'label_email' => 'admin_users', 'label_password' => 'admin_users', 'label_confirm_password' => 'admin_users',
    'label_role_level' => 'admin_users', 'button_create_admin' => 'admin_users', 'success_message_create_admin' => 'admin_users',
    'error_message_create_admin' => 'admin_users', 'error_password_mismatch' => 'admin_users', 'error_validation' => 'admin_users', 'error_general_api' => 'admin_users',
    'option_superadmin' => 'admin_users', 'invalid_email_format' => 'admin_users', 'password_too_short' => 'admin_users', 'admin_role_not_found' => 'admin_users',
    'not_authorized' => 'admin_users', 'permission_denied' => 'admin_users', 'action_suspend' => 'admin_users', 'provider_suspended_success' => 'admin_dashboard',
    'filter_all_providers' => 'admin_dashboard', 'filter_active_providers' => 'admin_dashboard', 'filter_pending_providers' => 'admin_dashboard',
    'filter_rejected_providers' => 'admin_dashboard', 'filter_suspended_providers' => 'admin_dashboard'
];

$translations = [];
foreach ($translations_map as $key => $context) {
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$langNameMap = isset(LANGUAGE_CONFIG['name_map']) ? LANGUAGE_CONFIG['name_map'] : [];
$currentLangName = isset($langNameMap[$languageCode]) ? $langNameMap[$languageCode] : getTranslation('language_label', $languageCode, 'default');
$translations['languageOptionsForDisplay'] = $langNameMap;
$translations['current_language_display_name'] = $currentLangName;

$city_from_translations = isset($translations['detecting_location']) ? $translations['detecting_location'] : 'Detectando...';
$city = isset($_SESSION['city']) ? $_SESSION['city'] : $city_from_translations;
$page_title = !empty($translations['dashboard_page_title']) ? $translations['dashboard_page_title'] : 'Painel de Controle';
$meta_description = isset(SEO_CONFIG['meta_description']) ? SEO_CONFIG['meta_description'] : 'Área administrativa do BacoSearch.';

$admin_name = 'Admin';
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = getDBConnection()->prepare("SELECT name FROM admins WHERE account_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($result['name'])) {
                $admin_name = explode(' ', trim($result['name']))[0];
            }
        }
    } catch (Exception $e) {
        // Silencioso
    }
}

$allowed_modules = ['stats', 'users', 'providers', 'businesses', 'ads_management', 'translations', 'system_logs', 'top_lists', 'user_details', 'services', 'create_admin', 'clubs', 'streets'];
$module_name = isset($_GET['module']) ? filter_input(INPUT_GET, 'module', FILTER_DEFAULT) : 'stats';
if (!in_array($module_name, $allowed_modules)) {
    $module_name = 'stats';
}
$module_path = dirname(__DIR__) . "/modules/dashboard/admin/{$module_name}.php";

if (!file_exists($module_path)) {
    header('Location: ' . SITE_URL . '/error.php');
    exit;
}

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';
?>

<main>
    <div class="global-content-wrapper dashboard-page-wrapper">
        <?php require_once TEMPLATE_PATH . 'admin_sidebar.php'; ?>
        <div class="dashboard-main-content">
            <?php require_once $module_path; ?>
        </div>
    </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
?>