<?php
/**
 * /modules/dashboard/admin/providers.php - Módulo para listar e gerenciar providers
 *
 * RESPONSABILIDADES:
 * 1. Lista providers com todos os status (active, rejected, suspended, pending).
 * 2. Permite filtragem por status.
 * 3. Permite ações de aprovar, rejeitar ou suspender cadastros.
 * 4. Atualiza status para controlar visibilidade no site.
 *
 * ÚLTIMA ATUALIZAÇÃO: 15/07/2025
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso negado.');
}

// Carrega traduções específicas
$title_main = getTranslation('providers_title', $languageCode, 'admin_dashboard');
$no_providers_text = getTranslation('no_providers_found', $languageCode, 'admin_dashboard');
$error_loading_text = getTranslation('error_loading_providers', $languageCode, 'admin_dashboard');
$approve_text = getTranslation('action_approve', $languageCode, 'admin_users');
$reject_text = getTranslation('action_reject', $languageCode, 'admin_users');
$suspend_text = getTranslation('action_suspend', $languageCode, 'admin_users');
$success_approve_text = getTranslation('provider_approved_success', $languageCode, 'admin_dashboard');
$success_reject_text = getTranslation('provider_rejected_success', $languageCode, 'admin_dashboard');
$success_suspend_text = getTranslation('provider_suspended_success', $languageCode, 'admin_dashboard');
$filter_all = getTranslation('filter_all_providers', $languageCode, 'admin_dashboard');
$filter_active = getTranslation('filter_active_providers', $languageCode, 'admin_dashboard');
$filter_pending = getTranslation('filter_pending_providers', $languageCode, 'admin_dashboard');
$filter_rejected = getTranslation('filter_rejected_providers', $languageCode, 'admin_dashboard');
$filter_suspended = getTranslation('filter_suspended_providers', $languageCode, 'admin_dashboard');
$table_header_name = getTranslation('table_header_name', $languageCode, 'admin_users');
$table_header_email = getTranslation('table_header_email', $languageCode, 'admin_users');
$table_header_phone = getTranslation('table_header_phone', $languageCode, 'admin_users');
$table_header_ad_title = getTranslation('table_header_ad_title', $languageCode, 'admin_dashboard');
$table_header_location = getTranslation('table_header_location', $languageCode, 'admin_dashboard');
$table_header_status = getTranslation('table_header_status', $languageCode, 'admin_users');
$table_header_registered = getTranslation('table_header_registered', $languageCode, 'admin_users');
$table_header_actions = getTranslation('table_header_actions', $languageCode, 'admin_users');

try {
    // Conexão com o banco de dados
    $pdo = getDBConnection();

    // Processar ações de aprovação/rejeição/suspensão
    $success_message = null;
    $error_message = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $provider_id = (int) ($_POST['provider_id'] ?? 0);

        if ($action === 'approve' && $provider_id) {
            // Ao aprovar, define o status como 'active' e is_active como 1
            $stmt = $pdo->prepare("UPDATE providers SET status = 'active', is_active = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$provider_id]);
            $success_message = $success_approve_text;
        } elseif ($action === 'reject' && $provider_id) {
            $stmt = $pdo->prepare("UPDATE providers SET status = 'rejected', is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$provider_id]);
            $success_message = $success_reject_text;
        } elseif ($action === 'suspend' && $provider_id) {
            $stmt = $pdo->prepare("UPDATE providers SET status = 'suspended', is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$provider_id]);
            $success_message = $success_suspend_text;
        }

        // Redirecionar para evitar reenvio do formulário
        header('Location: ' . SITE_URL . '/admin/dashboard.php?module=providers&status=' . ($_GET['status'] ?? 'all'));
        exit;
    }

    // Filtro de status
    $status_filter = $_GET['status'] ?? 'all';
    $status_condition = '';
    if (in_array($status_filter, ['active', 'pending', 'rejected', 'suspended'])) {
        $status_condition = "p.status = :status";
    }

    // Query para listar providers (AJUSTADA PARA O NOVO BANCO)
    $query = "
        SELECT 
            p.id AS provider_id,
            p.account_id,
            p.display_name,
            p.ad_title,
            p.status,
            a.email,
            a.full_name,
            a.phone_number,
            pl.ad_city,
            pl.ad_state,
            pl.ad_country,
            a.created_at AS registration_date
        FROM 
            providers p
            INNER JOIN accounts a ON p.account_id = a.id
            LEFT JOIN provider_logistics pl ON p.id = pl.provider_id
    ";
    if ($status_condition) {
        $query .= " WHERE $status_condition";
    }
    
    $query .= " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($query);
    
    if ($status_filter !== 'all') {
        $stmt->execute([':status' => $status_filter]);
    } else {
        $stmt->execute();
    }
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao listar providers: " . $e->getMessage());
    $error_message = $error_loading_text;
}
?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= htmlspecialchars($title_main) ?></h1>
    </div>

    <div id="providers-error" class="alert alert-danger" style="display: <?php echo isset($error_message) ? 'block' : 'none'; ?>;">
        <?php echo isset($error_message) ? htmlspecialchars($error_message) : ''; ?>
    </div>

    <div id="providers-success" class="alert alert-success" style="display: <?php echo isset($success_message) ? 'block' : 'none'; ?>;">
        <?php echo isset($success_message) ? htmlspecialchars($success_message) : ''; ?>
    </div>

    <div class="time-filters">
        <a href="?module=providers&status=all" class="btn time-filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>"><?= htmlspecialchars($filter_all) ?></a>
        <a href="?module=providers&status=active" class="btn time-filter-btn <?php echo $status_filter === 'active' ? 'active' : ''; ?>"><?= htmlspecialchars($filter_active) ?></a>
        <a href="?module=providers&status=pending" class="btn time-filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>"><?= htmlspecialchars($filter_pending) ?></a>
        <a href="?module=providers&status=rejected" class="btn time-filter-btn <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>"><?= htmlspecialchars($filter_rejected) ?></a>
        <a href="?module=providers&status=suspended" class="btn time-filter-btn <?php echo $status_filter === 'suspended' ? 'active' : ''; ?>"><?= htmlspecialchars($filter_suspended) ?></a>
    </div>

    <?php if (empty($providers)): ?>
        <p><?= htmlspecialchars($no_providers_text) ?></p>
    <?php else: ?>
        <div class="table-container">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($table_header_name) ?></th>
                        <th><?= htmlspecialchars($table_header_email) ?></th>
                        <th><?= htmlspecialchars($table_header_phone) ?></th>
                        <th><?= htmlspecialchars($table_header_ad_title) ?></th>
                        <th><?= htmlspecialchars($table_header_location) ?></th>
                        <th><?= htmlspecialchars($table_header_status) ?></th>
                        <th>
                            <span class="tooltip-wrapper">
                                <?= htmlspecialchars($table_header_registered) ?>
                                <span class="tooltip">Data de registro da conta</span>
                            </span>
                        </th>
                        <th><?= htmlspecialchars($table_header_actions) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $provider): ?>
                        <tr>
                            <td><?= htmlspecialchars($provider['display_name']) ?></td>
                            <td><?= htmlspecialchars($provider['email']) ?></td>
                            <td><?= htmlspecialchars($provider['phone_number']) ?></td>
                            <td><?= htmlspecialchars($provider['ad_title']) ?></td>
                            <td><?= htmlspecialchars($provider['ad_city'] . ', ' . $provider['ad_state']) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($provider['status']) ?>">
                                    <?= htmlspecialchars($provider['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($provider['registration_date']))) ?></td>
                            <td>
                                <form method="POST" class="action-form" onsubmit="return confirm('Tem certeza que deseja executar esta ação?');">
                                    <input type="hidden" name="provider_id" value="<?= $provider['provider_id'] ?>">
                                    <?php if ($provider['status'] !== 'active'): ?>
                                        <button type="submit" name="action" value="approve" 
                                            class="btn btn-sm btn-success">
                                            <?= htmlspecialchars($approve_text) ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($provider['status'] !== 'rejected'): ?>
                                        <button type="submit" name="action" value="reject" 
                                            class="btn btn-sm btn-danger">
                                            <?= htmlspecialchars($reject_text) ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($provider['status'] !== 'suspended' && $provider['status'] === 'active'): ?>
                                        <button type="submit" name="action" value="suspend" 
                                            class="btn btn-sm btn-warning">
                                            <?= htmlspecialchars($suspend_text) ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>