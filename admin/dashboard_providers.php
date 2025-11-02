<?php
/**
 * /dashboard_providers.php - Dashboard do Provedor (VERSÃO FINAL E COMPATÍVEL)
 */

require_once __DIR__ . '/core/bootstrap.php';

$page_name = 'dashboard_provider';

// Autenticação e Autorização
if (!isset($_SESSION['account_id']) || $_SESSION['account_id'] === null) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

$account_id = $_SESSION['account_id'];
$user_role = isset($_SESSION['user_data']['role']) ? $_SESSION['user_data']['role'] : null;
$provider_profile_id = null;

$languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');

try {
    $db = getDBConnection();

    if ($user_role !== 'provider' && $user_role !== 'admin') {
        header('Location: ' . SITE_URL . '/index.php?status=unauthorized_access');
        exit();
    }

    $stmt_provider_id = $db->prepare("SELECT id FROM providers WHERE account_id = :account_id LIMIT 1");
    $stmt_provider_id->execute([':account_id' => $account_id]);
    $provider_profile_id = $stmt_provider_id->fetchColumn();

    if (!$provider_profile_id) {
        header('Location: ' . SITE_URL . '/pages/register_providers.php?status=create_profile&account_id=' . $account_id);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $profile_id_to_act_on = isset($_POST['profile_id']) ? $_POST['profile_id'] : null;
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($profile_id_to_act_on && is_numeric($profile_id_to_act_on)) {
            $stmt_check_owner = $db->prepare("SELECT id, profile_status FROM providers WHERE id = :profile_id AND account_id = :account_id LIMIT 1");
            $stmt_check_owner->execute([':profile_id' => $profile_id_to_act_on, ':account_id' => $account_id]);
            $profile_info = $stmt_check_owner->fetch(PDO::FETCH_ASSOC);

            if ($profile_info) {
                if ($action === 'toggle_status') {
                    $new_status = ($profile_info['profile_status'] === 'active') ? 'paused' : 'active';
                    $stmt_update_status = $db->prepare("UPDATE providers SET profile_status = :new_status WHERE id = :profile_id");
                    $stmt_update_status->execute([':new_status' => $new_status, ':profile_id' => $profile_id_to_act_on]);

                    if ($new_status === 'active') {
                        $stmt_update_provider_active_time = $db->prepare("UPDATE providers SET last_active_at = NOW() WHERE id = :profile_id");
                        $stmt_update_provider_active_time->execute([':profile_id' => $profile_id_to_act_on]);
                    }
                    $_SESSION['dashboard_message'] = getTranslation('ad_status_updated_success', $languageCode, 'ui_messages');
                }
            } else {
                $_SESSION['dashboard_error'] = getTranslation('ad_not_found_or_unauthorized', $languageCode, 'ui_messages');
            }
        } else {
            $_SESSION['dashboard_error'] = getTranslation('invalid_ad_id', $languageCode, 'ui_messages');
        }
        header('Location: ' . SITE_URL . '/dashboard_providers.php');
        exit();
    }

    $stmt_profiles = $db->prepare("SELECT id, display_name, profile_status FROM providers WHERE account_id = :account_id ORDER BY created_at DESC");
    $stmt_profiles->execute([':account_id' => $account_id]);
    $provider_profiles_list = $stmt_profiles->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    log_system_error("Erro no dashboard do provedor para account_id: {$account_id}: " . $e->getMessage(), 'CRITICAL', 'dashboard_provider_error');
    $_SESSION['dashboard_error'] = getTranslation('general_dashboard_error', $languageCode, 'ui_messages');
    $provider_profiles_list = [];
}

$dashboard_message = isset($_SESSION['dashboard_message']) ? $_SESSION['dashboard_message'] : null;
$dashboard_error = isset($_SESSION['dashboard_error']) ? $_SESSION['dashboard_error'] : null;
unset($_SESSION['dashboard_message'], $_SESSION['dashboard_error']);

$translations = [];
$keys_to_translate = [
    'dashboard_title', 'dashboard_welcome', 'dashboard_no_ads', 'dashboard_create_ad_button', 'ad_name_column', 'ad_link_column', 'ad_status_column', 'ad_actions_column',
    'status_active', 'status_paused', 'status_pending', 'status_rejected', 'status_suspended', 'button_toggle_status_pause', 'button_toggle_status_activate', 'button_edit', 'button_view_public', 'dashboard_my_ads_title',
    'logo_alt', 'header_ads', 'header_login', 'header_menu', 'about_us', 'header_licenses', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us',
    'footer_providers', 'footer_companies','footer_clubs', 'footer_streets', 'footer_services', 'detecting_location'
];

foreach ($keys_to_translate as $key) {
    $context = 'dashboard';
    if (strpos($key, 'header_') === 0 || in_array($key, ['logo_alt', 'about_us', 'terms_of_service', 'privacy_policy', 'cookie_policy', 'contact_us', 'header_licenses'])) {
        $context = 'header';
    } elseif (strpos($key, 'footer_') === 0) {
        $context = 'footer';
    } elseif ($key === 'detecting_location') {
        $context = 'ui_messages';
    }
    $translations[$key] = getTranslation($key, $languageCode, $context);
}

$page_title = !empty($translations['dashboard_title']) ? $translations['dashboard_title'] : 'dashboard_title';
$meta_description = 'Gerencie seus anúncios de provedor no BacoSearch.';
$meta_keywords = 'dashboard, provedor, anúncios, gerenciar';
$meta_author = 'BacoSearch';
$page_specific_styles = [SITE_URL . '/assets/css/dashboard.css'];

require_once TEMPLATE_PATH . 'head.php';
require_once TEMPLATE_PATH . 'header.php';

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$user_display_name = isset($_SESSION['user_data']['display_name']) ? $_SESSION['user_data']['display_name'] : (isset($_SESSION['user_data']['email']) ? $_SESSION['user_data']['email'] : 'Provedor');
?>

<main class="dashboard-main">
    <div class="dashboard-container">
        <h1><?= $e(isset($translations['dashboard_title']) ? $translations['dashboard_title'] : ''); ?></h1>
        <p><?= $e(sprintf(isset($translations['dashboard_welcome']) ? $translations['dashboard_welcome'] : '', $user_display_name)); ?></p>

        <?php if ($dashboard_message): ?>
            <div class="alert alert-success"><?= $e($dashboard_message); ?></div>
        <?php endif; ?>
        <?php if ($dashboard_error): ?>
            <div class="alert alert-danger"><?= $e($dashboard_error); ?></div>
        <?php endif; ?>

        <div class="dashboard-actions">
            <a href="<?= SITE_URL; ?>/pages/register_providers.php?account_id=<?= $e($account_id); ?>&token=<?= $e(isset($_SESSION['registration_continue_token_' . $account_id]) ? $_SESSION['registration_continue_token_' . $account_id] : ''); ?>" class="btn btn-primary">
                <?= $e(isset($translations['dashboard_create_ad_button']) ? $translations['dashboard_create_ad_button'] : ''); ?>
            </a>
        </div>

        <div class="ad-list-section">
            <h2><?= $e(isset($translations['dashboard_my_ads_title']) ? $translations['dashboard_my_ads_title'] : ''); ?></h2>
            <?php if (empty($provider_profiles_list)): ?>
                <p><?= $e(isset($translations['dashboard_no_ads']) ? $translations['dashboard_no_ads'] : ''); ?></p>
            <?php else: ?>
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th><?= $e(isset($translations['ad_name_column']) ? $translations['ad_name_column'] : ''); ?></th>
                            <th><?= $e(isset($translations['ad_link_column']) ? $translations['ad_link_column'] : ''); ?></th>
                            <th><?= $e(isset($translations['ad_status_column']) ? $translations['ad_status_column'] : ''); ?></th>
                            <th><?= $e(isset($translations['ad_actions_column']) ? $translations['ad_actions_column'] : ''); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($provider_profiles_list as $profile): ?>
                            <?php
                            $profile_slug = $profile['id'] . '-' . create_slug($profile['display_name']);
                            $public_profile_url = SITE_URL . '/profile/' . $e($profile_slug);
                            ?>
                            <tr>
                                <td><?= $e($profile['display_name']); ?></td>
                                <td><a href="<?= $public_profile_url; ?>" target="_blank"><?= $e($profile_slug); ?></a></td>
                                <td>
                                    <span class="status-badge status-<?= $e($profile['profile_status']); ?>">
                                        <?= $e(isset($translations['status_' . $profile['profile_status']]) ? $translations['status_' . $profile['profile_status']] : $profile['profile_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form action="" method="POST" style="display:inline-block;">
                                        <input type="hidden" name="profile_id" value="<?= $e($profile['id']); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <button type="submit" class="btn btn-sm btn-<?= ($profile['profile_status'] === 'active' ? 'warning' : 'success'); ?>">
                                            <?= $e(isset($translations[($profile['profile_status'] === 'active' ? 'button_toggle_status_pause' : 'button_toggle_status_activate')]) ? $translations[($profile['profile_status'] === 'active' ? 'button_toggle_status_pause' : 'button_toggle_status_activate')] : ''); ?>
                                        </button>
                                    </form>
                                    <a href="<?= SITE_URL; ?>/pages/register_providers.php?account_id=<?= $e($account_id); ?>&token=<?= $e(isset($_SESSION['registration_continue_token_' . $account_id]) ? $_SESSION['registration_continue_token_' . $account_id] : ''); ?>&profile_id=<?= $e($profile['id']); ?>" class="btn btn-sm btn-info">
                                        <?= $e(isset($translations['button_edit']) ? $translations['button_edit'] : ''); ?>
                                    </a>
                                    <a href="<?= $public_profile_url; ?>" target="_blank" class="btn btn-sm btn-secondary">
                                        <?= $e(isset($translations['button_view_public']) ? $translations['button_view_public'] : ''); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
require_once TEMPLATE_PATH . 'footer.php';
?>