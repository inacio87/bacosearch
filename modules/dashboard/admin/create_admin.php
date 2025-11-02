<?php
/**
 * /modules/dashboard/admin/create_admin.php
 * Módulo de Criação de Novos Administradores no Dashboard
 * - Exibe um formulário para coletar dados de um novo admin.
 * - Envia os dados para a API admin_create_admin.php via AJAX.
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// Carrega as traduções específicas para este módulo, do contexto 'admin_users' no banco de dados.
$title_create_admin       = getTranslation('create_admin_title', $languageCode, 'admin_users');
$label_full_name          = getTranslation('label_full_name', $languageCode, 'admin_users');
$label_email              = getTranslation('label_email', $languageCode, 'admin_users');
$label_password           = getTranslation('label_password', $languageCode, 'admin_users');
$label_confirm_password   = getTranslation('label_confirm_password', $languageCode, 'admin_users');
$label_role_level         = getTranslation('label_role_level', $languageCode, 'admin_users');
$button_create_admin      = getTranslation('button_create_admin', $languageCode, 'admin_users');
$back_to_users_list       = getTranslation('back_to_users_list', $languageCode, 'admin_users');

// Mensagens de feedback (sucesso/erro) que virão do banco de dados
$success_message_create_admin = getTranslation('success_message_create_admin', $languageCode, 'admin_users');
$error_message_create_admin   = getTranslation('error_message_create_admin', $languageCode, 'admin_users');
$error_password_mismatch      = getTranslation('error_password_mismatch', $languageCode, 'admin_users');
$error_validation             = getTranslation('error_validation', $languageCode, 'admin_users');
$error_general_api            = getTranslation('error_general_api', $languageCode, 'admin_users');
$option_superadmin            = getTranslation('option_superadmin', $languageCode, 'admin_users'); // Tradução para a opção do select

// Lógica para carregar os role_levels disponíveis.
// Por enquanto, estamos hardcoding 'superadmin' para o propósito de criar admins.
// Se você tiver outros sub-níveis de admin (e.g., 'editor', 'viewer'), você pode buscá-los
// do banco de dados ou defini-los aqui.
$available_role_levels = [
    'superadmin' => $option_superadmin, // Usando a tradução
    // Exemplo: 'editor_admin' => getTranslation('option_editor_admin', $languageCode, 'admin_users'),
];

?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= htmlspecialchars($title_create_admin) ?></h1>
        <a href="<?= SITE_URL ?>/admin/dashboard.php?module=users" class="btn btn-secondary back-btn">
            <?= htmlspecialchars($back_to_users_list) ?>
        </a>
    </div>

    <div id="create-admin-message" class="alert" style="display: none;"></div>

    <form id="createAdminForm" class="admin-form">
        <div class="form-group">
            <label for="full_name"><?= htmlspecialchars($label_full_name) ?>:</label>
            <input type="text" id="full_name" name="full_name" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="email"><?= htmlspecialchars($label_email) ?>:</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="password"><?= htmlspecialchars($label_password) ?>:</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="confirm_password"><?= htmlspecialchars($label_confirm_password) ?>:</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="role_level"><?= htmlspecialchars($label_role_level) ?>:</label>
            <select id="role_level" name="role_level" class="form-control" required>
                <?php foreach ($available_role_levels as $value => $text): ?>
                    <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($text) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-success"><?= htmlspecialchars($button_create_admin) ?></button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('createAdminForm');
    const messageDiv = document.getElementById('create-admin-message');

    form.addEventListener('submit', async (event) => {
        event.preventDefault(); // Evita o envio padrão do formulário

        messageDiv.style.display = 'none';
        messageDiv.className = 'alert'; // Limpa classes de alerta anteriores

        const fullName = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const roleLevel = document.getElementById('role_level').value;

        // Frontend validation
        if (password !== confirmPassword) {
            messageDiv.textContent = '<?= htmlspecialchars($error_password_mismatch) ?>';
            messageDiv.classList.add('alert-danger');
            messageDiv.style.display = 'block';
            return;
        }

        if (!fullName || !email || !password || !confirmPassword || !roleLevel) {
            messageDiv.textContent = '<?= htmlspecialchars($error_validation) ?>';
            messageDiv.classList.add('alert-danger');
            messageDiv.style.display = 'block';
            return;
        }

        try {
            // Sends data to the new API endpoint
            const response = await fetch('<?= SITE_URL ?>/api/admin_create_admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' // Important for security checks on the backend
                },
                body: JSON.stringify({
                    full_name: fullName,
                    email: email,
                    password: password,
                    role_level: roleLevel
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                messageDiv.textContent = data.message || '<?= htmlspecialchars($success_message_create_admin) ?>';
                messageDiv.classList.add('alert-success');
                form.reset(); // Clears the form after successful creation
                // Optional: Redirect to users list after successful creation
                // setTimeout(() => {
                //     window.location.href = '<?= SITE_URL ?>/admin/dashboard.php?module=users';
                // }, 2000);
            } else {
                // Handles errors returned from the API
                messageDiv.textContent = data.message || '<?= htmlspecialchars($error_message_create_admin) ?>';
                messageDiv.classList.add('alert-danger');
            }
            messageDiv.style.display = 'block';

        } catch (error) {
            console.error('Erro ao criar administrador:', error);
            messageDiv.textContent = '<?= htmlspecialchars($error_general_api) ?>' + ': ' + error.message;
            messageDiv.classList.add('alert-danger');
            messageDiv.style.display = 'block';
        }
    });
});
</script>