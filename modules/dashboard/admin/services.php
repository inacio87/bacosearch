<?php
/**
 * /modules/dashboard/admin/services.php - Admin de Serviços (services_listings)
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

$title = getTranslation('services_title', $languageCode, 'admin_dashboard');
$approve_text  = getTranslation('action_approve', $languageCode, 'admin_users');
$reject_text   = getTranslation('action_reject', $languageCode, 'admin_users');
$suspend_text  = getTranslation('action_suspend', $languageCode, 'admin_users');
$filter_all    = getTranslation('filter_all_items', $languageCode, 'admin_dashboard');
$filter_active = getTranslation('filter_active_items', $languageCode, 'admin_dashboard');
$filter_pending= getTranslation('filter_pending_items', $languageCode, 'admin_dashboard');
$filter_rejected= getTranslation('filter_rejected_items', $languageCode, 'admin_dashboard');
$filter_suspended= getTranslation('filter_suspended_items', $languageCode, 'admin_dashboard');

$pdo = getDBConnection();
$success_message = null; $error_message=null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            if ($action==='approve') {
                $pdo->prepare("UPDATE services_listings SET status='active', is_active=1, updated_at=NOW() WHERE id=?")->execute([$id]);
                $success_message = getTranslation('item_approved_success', $languageCode, 'admin_dashboard');
            } elseif ($action==='reject') {
                $pdo->prepare("UPDATE services_listings SET status='rejected', is_active=0, updated_at=NOW() WHERE id=?")->execute([$id]);
                $success_message = getTranslation('item_rejected_success', $languageCode, 'admin_dashboard');
            } elseif ($action==='suspend') {
                $pdo->prepare("UPDATE services_listings SET status='suspended', is_active=0, updated_at=NOW() WHERE id=?")->execute([$id]);
                $success_message = getTranslation('item_suspended_success', $languageCode, 'admin_dashboard');
            }
        }
        header('Location: '.SITE_URL.'/admin/dashboard.php?module=services&status='.(isset($_GET['status'])? $_GET['status'] : 'all')); exit;
    }

    $status_filter = $_GET['status'] ?? 'all';
    $cond = ''; $params=[];
    if (in_array($status_filter, ['active','pending','rejected','suspended'], true)) { $cond='WHERE s.status=:s'; $params[':s']=$status_filter; }
    $sql = "SELECT s.id, s.service_title, s.email, s.phone_number, s.ad_city, s.ad_state, s.status, s.created_at
                    FROM services_listings s $cond ORDER BY s.created_at DESC";
    $st = $pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $error_message = getTranslation('error_loading_items', $languageCode, 'admin_dashboard'); }
?>

<div class="dashboard-module-wrapper">
    <div class="module-header"><h1><?= htmlspecialchars($title ?: 'Serviços'); ?></h1></div>

    <div class="time-filters">
        <a href="?module=services&status=all" class="btn time-filter-btn <?= ($status_filter==='all')?'active':''; ?>"><?= htmlspecialchars($filter_all); ?></a>
        <a href="?module=services&status=active" class="btn time-filter-btn <?= ($status_filter==='active')?'active':''; ?>"><?= htmlspecialchars($filter_active); ?></a>
        <a href="?module=services&status=pending" class="btn time-filter-btn <?= ($status_filter==='pending')?'active':''; ?>"><?= htmlspecialchars($filter_pending); ?></a>
        <a href="?module=services&status=rejected" class="btn time-filter-btn <?= ($status_filter==='rejected')?'active':''; ?>"><?= htmlspecialchars($filter_rejected); ?></a>
        <a href="?module=services&status=suspended" class="btn time-filter-btn <?= ($status_filter==='suspended')?'active':''; ?>"><?= htmlspecialchars($filter_suspended); ?></a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="dashboard-table">
            <thead>
                <tr>
                    <th><?= htmlspecialchars(getTranslation('table_header_name', $languageCode, 'admin_users')); ?></th>
                    <th><?= htmlspecialchars(getTranslation('table_header_email', $languageCode, 'admin_users')); ?></th>
                    <th><?= htmlspecialchars(getTranslation('table_header_phone', $languageCode, 'admin_users')); ?></th>
                    <th><?= htmlspecialchars(getTranslation('table_header_location', $languageCode, 'admin_dashboard')); ?></th>
                    <th><?= htmlspecialchars(getTranslation('table_header_status', $languageCode, 'admin_users')); ?></th>
                    <th><?= htmlspecialchars(getTranslation('table_header_actions', $languageCode, 'admin_users')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6"><?= htmlspecialchars(getTranslation('no_items_found', $languageCode, 'admin_dashboard') ?: 'Nenhum registro.'); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['service_title']); ?></td>
                        <td><?= htmlspecialchars($r['email']); ?></td>
                        <td><?= htmlspecialchars($r['phone_number']); ?></td>
                        <td><?= htmlspecialchars(($r['ad_city']??'') . ', ' . ($r['ad_state']??'')); ?></td>
                        <td><span class="status-badge status-<?= htmlspecialchars($r['status']); ?>"><?= htmlspecialchars($r['status']); ?></span></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Confirmar ação?');">
                                <input type="hidden" name="id" value="<?= (int)$r['id']; ?>">
                                <?php if ($r['status']!=='active'): ?><button class="btn btn-sm btn-success" name="action" value="approve"><?= htmlspecialchars($approve_text); ?></button><?php endif; ?>
                                <?php if ($r['status']!=='rejected'): ?><button class="btn btn-sm btn-danger" name="action" value="reject"><?= htmlspecialchars($reject_text); ?></button><?php endif; ?>
                                <?php if ($r['status']==='active'): ?><button class="btn btn-sm btn-warning" name="action" value="suspend"><?= htmlspecialchars($suspend_text); ?></button><?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
