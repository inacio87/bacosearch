<?php
/**
 * /modules/dashboard/admin/users.php
 * Módulo de Gestão de Utilizadores do Admin (versão completa e ajustada)
 * - Exibe uma lista de contas de utilizadores.
 * - Permite filtrar por status.
 * - Fornece ações para aprovar, reprovar, suspender, etc.
 * - Adicionado botão para criar novos administradores.
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// Carrega as traduções específicas para este módulo
$title_main             = getTranslation('admin_users_title', $languageCode, 'admin_users');
$filter_all             = getTranslation('filter_all_users', $languageCode, 'admin_users');
$filter_pending_email   = getTranslation('filter_pending_email', $languageCode, 'admin_users');
$filter_pending_admin   = getTranslation('filter_pending_admin', $languageCode, 'admin_users');
$filter_active          = getTranslation('filter_active_users', $languageCode, 'admin_users');
$filter_suspended       = getTranslation('filter_suspended_users', $languageCode, 'admin_users');
$filter_rejected        = getTranslation('filter_rejected_users', $languageCode, 'admin_users');
$table_header_name      = getTranslation('table_header_name', $languageCode, 'admin_users');
$table_header_email     = getTranslation('table_header_email', $languageCode, 'admin_users');
$table_header_phone     = getTranslation('table_header_phone', $languageCode, 'admin_users');
$table_header_type      = getTranslation('table_header_type', $languageCode, 'admin_users');
$table_header_nationality = getTranslation('table_header_nationality', $languageCode, 'admin_users');
$table_header_status    = getTranslation('table_header_status', $languageCode, 'admin_users');
$table_header_registered = getTranslation('table_header_registered', $languageCode, 'admin_users');
$table_header_actions   = getTranslation('table_header_actions', $languageCode, 'admin_users');
$action_approve         = getTranslation('action_approve', $languageCode, 'admin_users');
$action_reject          = getTranslation('action_reject', $languageCode, 'admin_users');
$action_view_details    = getTranslation('action_view_details', $languageCode, 'admin_users');
$no_users_found         = getTranslation('no_users_found', $languageCode, 'admin_users');
$error_loading_users    = getTranslation('error_loading_users', $languageCode, 'admin_users');

// NEW TRANSLATIONS for the "Create Admin" button
$create_new_admin_button = getTranslation('create_new_admin_button', $languageCode, 'admin_users');
$action_suspend = getTranslation('action_suspend', $languageCode, 'admin_users');
$action_activate = getTranslation('action_activate', $languageCode, 'admin_users');
$status_rejected = getTranslation('status_rejected', $languageCode, 'admin_users');
$confirm_approve_user = getTranslation('confirm_approve_user', $languageCode, 'admin_users');
$confirm_reject_user = getTranslation('confirm_reject_user', $languageCode, 'admin_users');
$confirm_suspend_user = getTranslation('confirm_suspend_user', $languageCode, 'admin_users');
$confirm_activate_user = getTranslation('confirm_activate_user', $languageCode, 'admin_users');
$action_success_fallback = getTranslation('action_success_fallback', $languageCode, 'admin_users');
$action_error_fallback = getTranslation('action_error_fallback', $languageCode, 'admin_users');


// Lógica para filtrar e buscar os utilizadores
// AJUSTE 1: Filtro padrão alterado para 'all' para exibir todos os utilizadores por defeito.
$current_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?? 'all';
$allowed_statuses = ['all', 'pending_email_verification', 'pending_admin_approval', 'active', 'suspended', 'rejected'];

if (!in_array($current_filter, $allowed_statuses)) {
    $current_filter = 'all'; // Garante um fallback seguro
}

$db = getDBConnection();

$where_clause = "WHERE 1";
if ($current_filter !== 'all') {
    $where_clause = "WHERE a.status = :status";
}

$stmt_users = $db->prepare("
    SELECT
        a.id, a.email, a.full_name, a.birth_date, a.phone_code, a.phone_number,
        a.role, a.status, a.created_at,
        c.name as nationality_name
    FROM accounts a
    LEFT JOIN countries c ON a.nationality_id = c.id
    {$where_clause}
    ORDER BY a.created_at DESC
");

if ($current_filter !== 'all') {
    $stmt_users->bindValue(':status', $current_filter);
}
$stmt_users->execute();
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= htmlspecialchars($title_main) ?></h1>
        <a href="<?= SITE_URL ?>/admin/dashboard.php?module=create_admin" class="btn btn-primary create-admin-btn">
            <?= htmlspecialchars($create_new_admin_button) ?>
        </a>
    </div>

    <div id="users-error" class="alert alert-danger" style="display: none;"></div>

    <div class="status-filters">
        <button class="btn status-filter-btn <?= ($current_filter === 'all' ? 'active' : '') ?>" data-status="all"><?= htmlspecialchars($filter_all) ?></button>
        <button class="btn status-filter-btn <?= ($current_filter === 'pending_email_verification' ? 'active' : '') ?>" data-status="pending_email_verification"><?= htmlspecialchars($filter_pending_email) ?></button>
        <button class="btn status-filter-btn <?= ($current_filter === 'pending_admin_approval' ? 'active' : '') ?>" data-status="pending_admin_approval"><?= htmlspecialchars($filter_pending_admin) ?></button>
        <button class="btn status-filter-btn <?= ($current_filter === 'active' ? 'active' : '') ?>" data-status="active"><?= htmlspecialchars($filter_active) ?></button>
        <button class="btn status-filter-btn <?= ($current_filter === 'suspended' ? 'active' : '') ?>" data-status="suspended"><?= htmlspecialchars($filter_suspended) ?></button>
        <button class="btn status-filter-btn <?= ($current_filter === 'rejected' ? 'active' : '') ?>" data-status="rejected"><?= htmlspecialchars($filter_rejected) ?></button>
    </div>

    <div class="users-list-container">
        <?php if (empty($users)): ?>
            <p class="no-results-message"><?= htmlspecialchars($no_users_found) ?></p>
        <?php else: ?>
            <table class="users-table dashboard-table">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($table_header_name) ?></th>
                        <th><?= htmlspecialchars($table_header_email) ?></th>
                        <th><?= htmlspecialchars($table_header_phone) ?></th>
                        <th><?= htmlspecialchars($table_header_type) ?></th>
                        <th><?= htmlspecialchars($table_header_nationality) ?></th>
                        <th><?= htmlspecialchars($table_header_status) ?></th>
                        <th><?= htmlspecialchars($table_header_registered) ?></th>
                        <th><?= htmlspecialchars($table_header_actions) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['phone_code'] . $user['phone_number']) ?></td>
                            <td><?= htmlspecialchars(getTranslation('account_type_' . $user['role'], $languageCode, 'admin_users')) ?></td>
                            <td><?= htmlspecialchars($user['nationality_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($user['status']) ?>">
                                    <?= htmlspecialchars(getTranslation('status_' . $user['status'], $languageCode, 'admin_users')) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                            <td class="actions-column">
                                <?php
                                // AJUSTE 2: Exibir botões "Aprovar" e "Reprovar" para ambos os status pendentes.
                                if ($user['status'] === 'pending_admin_approval' || $user['status'] === 'pending_email_verification'): ?>
                                    <button class="btn btn-sm btn-success action-btn approve-btn" data-id="<?= $user['id'] ?>"><?= htmlspecialchars($action_approve) ?></button>
                                    <button class="btn btn-sm btn-danger action-btn reject-btn" data-id="<?= $user['id'] ?>"><?= htmlspecialchars($action_reject) ?></button>
                                <?php elseif ($user['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-warning action-btn suspend-btn" data-id="<?= $user['id'] ?>"><?= htmlspecialchars($action_suspend) ?></button>
                                <?php elseif ($user['status'] === 'suspended'): ?>
                                    <button class="btn btn-sm btn-info action-btn activate-btn" data-id="<?= $user['id'] ?>"><?= htmlspecialchars($action_activate) ?></button>
                                <?php elseif ($user['status'] === 'rejected'): ?>
                                    <span class="text-muted"><?= htmlspecialchars($status_rejected) ?></span>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-info action-btn details-btn" data-id="<?= $user['id'] ?>"><?= htmlspecialchars($action_view_details) ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const usersTable = document.querySelector('.users-table');
    const errorDiv = document.getElementById('users-error');
    const statusFilterButtons = document.querySelectorAll('.status-filter-btn');

    // Lógica para filtrar por status ao clicar nos botões
    statusFilterButtons.forEach(button => {
        button.addEventListener('click', () => {
            const status = button.dataset.status;
            window.location.href = `<?= SITE_URL ?>/admin/dashboard.php?module=users&status=${status}`;
        });
    });

    // Lógica para as ações (Aprovar, Rejeitar, etc.) via AJAX
    if (usersTable) {
        usersTable.addEventListener('click', async (event) => {
            const target = event.target;
            const userId = target.dataset.id;
            if (!userId) return;

            let action = '';
            let confirmMessage = '';

            if (target.classList.contains('approve-btn')) {
                action = 'approve';
                confirmMessage = '<?= htmlspecialchars($confirm_approve_user) ?>';
            } else if (target.classList.contains('reject-btn')) {
                action = 'reject';
                confirmMessage = '<?= htmlspecialchars($confirm_reject_user) ?>';
            } else if (target.classList.contains('suspend-btn')) {
                action = 'suspend';
                confirmMessage = '<?= htmlspecialchars($confirm_suspend_user) ?>';
            } else if (target.classList.contains('activate-btn')) {
                action = 'activate';
                confirmMessage = '<?= htmlspecialchars($confirm_activate_user) ?>';
            } else if (target.classList.contains('details-btn')) {
                window.location.href = `<?= SITE_URL ?>/admin/dashboard.php?module=user_details&id=${userId}`;
                return;
            } else {
                return;
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            try {
                const response = await fetch('<?= SITE_URL ?>/api/admin_user_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: action,
                        user_id: userId
                    })
                });

                // Se a resposta não for OK (ex: 403, 500), trata o erro
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    alert(data.message || '<?= htmlspecialchars($action_success_fallback) ?>');
                    window.location.reload();
                } else {
                    errorDiv.textContent = data.message || '<?= htmlspecialchars($action_error_fallback) ?>';
                    errorDiv.style.display = 'block';
                }
            } catch (err) {
                console.error('Erro na requisição AJAX:', err);
                errorDiv.textContent = '<?= htmlspecialchars($error_loading_users) ?>: ' + err.message;
                errorDiv.style.display = 'block';
            }
        });
    }
});
</script>