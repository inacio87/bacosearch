<?php
/**
 * /modules/dashboard/admin/system_logs.php - Módulo de Logs do Sistema
 *
 * RESPONSABILIDADES:
 * 1. Exibe os logs do sistema (system_logs, search_logs, api_accuracy_log) com filtros de tempo.
 * 2. Permite a paginação dos logs para melhor usabilidade.
 * 3. Garante que os dados sejam exibidos de forma clara e organizada.
 * 4. Adiciona um modal para exibir a mensagem completa do log e permitir cópia.
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// Carrega as traduções necessárias para este módulo
$translations = [];
$keys_to_translate = [
    'system_logs_title', 'filter_5_min', 'filter_today', 'filter_7_days',
    'filter_30_days', 'filter_360_days', 'loading_data', 'no_data_found',
    'network_error', 'api_error', 'log_id', 'log_level', 'log_message',
    'log_context', 'log_ip_address',
    // 'log_user_agent', // REMOVA ou COMENTE esta linha se não precisar mais do cabeçalho "User Agent" em lugar algum.
    'log_visitor_id', // <-- ADICIONADA esta nova chave de tradução para visitor_id
    'log_request_uri',
    'log_created_at', 'search_log_term', 'search_log_normalized_term',
    'search_log_intent_category', 'search_log_results_count', 'search_log_visitor_id',
    'api_log_name', 'api_log_distance_error_km', 'api_log_country_code',
    'api_log_region', 'api_log_updated_at', 'system_logs_tab', 'search_logs_tab',
    'api_accuracy_logs_tab', 'previous_page', 'next_page', 'page_of_pages',
    'modal_log_message_title', 'modal_copy_to_clipboard', 'modal_close' // Novas chaves para o modal
];
foreach ($keys_to_translate as $key) {
    $translations[$key] = getTranslation($key, $languageCode, 'admin_dashboard');
}

// Definição do fuso horário para consistência
date_default_timezone_set('Europe/Lisbon');
?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= htmlspecialchars($translations['system_logs_title'] ?? 'Logs do Sistema') ?></h1>
    </div>

    <div class="time-filters">
        <button class="btn time-filter-btn" data-period="5min"><?= htmlspecialchars($translations['filter_5_min'] ?? '5 Min') ?></button>
        <button class="btn time-filter-btn" data-period="today"><?= htmlspecialchars($translations['filter_today'] ?? 'Hoje') ?></button>
        <button class="btn time-filter-btn active" data-period="7d"><?= htmlspecialchars($translations['filter_7_days'] ?? '7 Dias') ?></button>
        <button class="btn time-filter-btn" data-period="30d"><?= htmlspecialchars($translations['filter_30_days'] ?? '30 Dias') ?></button>
        <button class="btn time-filter-btn" data-period="360d"><?= htmlspecialchars($translations['filter_360_days'] ?? '360 Dias') ?></button>
    </div>

    <div class="log-tabs">
        <button class="btn log-tab-btn active" data-log-type="system_logs"><?= htmlspecialchars($translations['system_logs_tab'] ?? 'Logs do Sistema') ?></button>
        <button class="btn log-tab-btn" data-log-type="search_logs"><?= htmlspecialchars($translations['search_logs_tab'] ?? 'Logs de Busca') ?></button>
        <button class="btn log-tab-btn" data-log-type="api_accuracy_log"><?= htmlspecialchars($translations['api_accuracy_logs_tab'] ?? 'Logs de Precisão da API') ?></button>
    </div>

    <div id="logs-error" class="alert alert-danger" style="display: none;"></div>

    <div class="log-table-container">
        <p id="loading-logs-message" style="display: block;"><?= htmlspecialchars($translations['loading_data'] ?? 'A carregar dados...') ?></p>
        <table id="logs-table" class="table table-striped table-hover" style="display: none;">
            <thead>
                <tr id="logs-table-header">
                    </tr>
            </thead>
            <tbody id="logs-table-body">
                </tbody>
        </table>
        <div id="pagination-controls" class="pagination-controls" style="display: none;">
            <button id="prev-page" class="btn btn-secondary"><?= htmlspecialchars($translations['previous_page'] ?? 'Anterior') ?></button>
            <span id="page-info"></span>
            <button id="next-page" class="btn btn-secondary"><?= htmlspecialchars($translations['next_page'] ?? 'Próxima') ?></button>
        </div>
    </div>
</div>

<div id="logMessageModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title"><?= htmlspecialchars($translations['modal_log_message_title'] ?? 'Mensagem Completa do Log') ?></h3>
            <button type="button" class="close-modal-btn">&times;</button>
        </div>
        <div class="modal-body">
            <pre id="fullLogMessage" class="log-message-pre"></pre>
        </div>
        <div class="modal-footer">
            <button type="button" id="copyLogMessage" class="btn btn-primary"><?= htmlspecialchars($translations['modal_copy_to_clipboard'] ?? 'Copiar para Área de Transferência') ?></button>
            <button type="button" class="btn btn-secondary close-modal-btn"><?= htmlspecialchars($translations['modal_close'] ?? 'Fechar') ?></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const errorDiv = document.getElementById('logs-error');
    const loadingMessage = document.getElementById('loading-logs-message');
    const logsTable = document.getElementById('logs-table');
    const logsTableHeader = document.getElementById('logs-table-header');
    const logsTableBody = document.getElementById('logs-table-body');
    const paginationControls = document.getElementById('pagination-controls');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfoSpan = document.getElementById('page-info');

    // Elementos do Modal
    const logMessageModal = document.getElementById('logMessageModal');
    const fullLogMessagePre = document.getElementById('fullLogMessage');
    const copyLogMessageBtn = document.getElementById('copyLogMessage');
    const closeModalBtns = document.querySelectorAll('.close-modal-btn');

    let currentLogType = 'system_logs';
    let currentPeriod = '7d';
    let currentPage = 1;
    const itemsPerPage = 25;

    const jsTranslations = <?= json_encode($translations) ?>;

    // Mapeamento de cabeçalhos para cada tipo de log
    const logHeaders = {
        'system_logs': [
            { key: 'id', label: jsTranslations.log_id },
            { key: 'level', label: jsTranslations.log_level },
            { key: 'message', label: jsTranslations.log_message, clickable: true },
            { key: 'context', label: jsTranslations.log_context },
            { key: 'visitor_id', label: jsTranslations.log_visitor_id }, // <-- ALTERADO AQUI: Adicionado visitor_id
            { key: 'ip_address', label: jsTranslations.log_ip_address },
            // { key: 'user_agent', label: jsTranslations.log_user_agent }, // <-- REMOVIDO/COMENTADO: Não queremos mais esta coluna
            { key: 'request_uri', label: jsTranslations.log_request_uri },
            { key: 'created_at', label: jsTranslations.log_created_at }
        ],
        'search_logs': [
            { key: 'id', label: jsTranslations.log_id },
            { key: 'term', label: jsTranslations.search_log_term, clickable: true },
            { key: 'normalized_term', label: jsTranslations.search_log_normalized_term },
            { key: 'intent_category', label: jsTranslations.search_log_intent_category },
            { key: 'results_count', label: jsTranslations.search_log_results_count },
            { key: 'visitor_id', label: jsTranslations.search_log_visitor_id },
            { key: 'created_at', label: jsTranslations.log_created_at }
        ],
        'api_accuracy_log': [
            { key: 'id', label: jsTranslations.log_id },
            { key: 'api_name', label: jsTranslations.api_log_name },
            { key: 'distance_error_km', label: jsTranslations.api_log_distance_error_km },
            { key: 'country_code', label: jsTranslations.api_log_country_code },
            { key: 'region', label: jsTranslations.api_log_region },
            { key: 'created_at', label: jsTranslations.log_created_at },
            { key: 'updated_at', label: jsTranslations.api_log_updated_at }
        ]
    };

    function fetchAndRenderLogs() {
        loadingMessage.style.display = 'block';
        logsTable.style.display = 'none';
        paginationControls.style.display = 'none';
        errorDiv.style.display = 'none';
        logsTableBody.innerHTML = ''; // Limpa a tabela

        fetch(`<?= SITE_URL ?>/api/api_logs.php?log_type=${currentLogType}&period=${currentPeriod}&page=${currentPage}&limit=${itemsPerPage}`)
            .then(response => response.ok ? response.json() : Promise.reject(new Error(jsTranslations.network_error || 'Erro de rede ou servidor.')))
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || (jsTranslations.api_error || 'Erro na API ao carregar logs.'));
                }
                renderLogsTable(data.logs);
                updatePagination(data.total_pages);
            })
            .catch(err => {
                errorDiv.textContent = err.message;
                errorDiv.style.display = 'block';
                logsTable.style.display = 'none';
                paginationControls.style.display = 'none';
                console.error('Dashboard Error:', err);
            })
            .finally(() => {
                loadingMessage.style.display = 'none';
            });
    }

    function renderLogsTable(logs) {
        logsTableHeader.innerHTML = '';
        logsTableBody.innerHTML = '';

        const headers = logHeaders[currentLogType];
        headers.forEach(header => {
            const th = document.createElement('th');
            th.textContent = header.label;
            logsTableHeader.appendChild(th);
        });

        if (!logs || logs.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = headers.length;
            td.textContent = jsTranslations.no_data_found || 'Nenhum dado encontrado.';
            td.style.textAlign = 'center';
            tr.appendChild(td);
            logsTableBody.appendChild(tr);
        } else {
            logs.forEach(log => {
                const tr = document.createElement('tr');
                headers.forEach(header => {
                    const td = document.createElement('td');
                    let value = log[header.key];
                    td.setAttribute('data-label', header.label); // Adiciona data-label para responsividade

                    // Este if/else if/else precisa ser ajustado.
                    // A condição `header.key === 'user_agent'` não é mais necessária para `system_logs`.
                    if (header.clickable && value) {
                        td.classList.add('log-message-clickable');
                        td.textContent = value.substring(0, 100) + (value.length > 100 ? '...' : '');
                        td.title = jsTranslations.modal_log_message_title || 'Clique para ver a mensagem completa'; // Tooltip
                        td.addEventListener('click', () => openLogMessageModal(value));
                    } else if (header.key === 'request_uri' && typeof value === 'string' && value.length > 100) { // Apenas request_uri precisa de truncagem se for longo
                        td.textContent = value.substring(0, 100) + '...';
                        td.title = value; // Adiciona o texto completo no tooltip
                    } else {
                        td.textContent = value ?? 'N/A';
                    }
                    tr.appendChild(td);
                });
                logsTableBody.appendChild(tr);
            });
        }
        logsTable.style.display = 'table';
    }

    function updatePagination(totalPages) {
        pageInfoSpan.textContent = `${jsTranslations.page_of_pages || 'Página %s de %s'}`
            .replace('%s', currentPage)
            .replace('%s', totalPages);

        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;
        paginationControls.style.display = 'flex';
    }

    // Funções do Modal
    function openLogMessageModal(message) {
        fullLogMessagePre.textContent = message;
        logMessageModal.style.display = 'flex'; // Usar flex para centralizar
    }

    function closeLogMessageModal() {
        logMessageModal.style.display = 'none';
        fullLogMessagePre.textContent = '';
    }

    // Copiar para área de transferência
    copyLogMessageBtn.addEventListener('click', () => {
        const textToCopy = fullLogMessagePre.textContent;
        const textArea = document.createElement('textarea');
        textArea.value = textToCopy;
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            // Opcional: feedback visual de sucesso
            copyLogMessageBtn.textContent = 'Copiado!';
            setTimeout(() => {
                copyLogMessageBtn.textContent = jsTranslations.modal_copy_to_clipboard;
            }, 2000);
        } catch (err) {
            console.error('Falha ao copiar: ', err);
            // Opcional: feedback visual de erro
            copyLogMessageBtn.textContent = 'Erro ao Copiar!';
            setTimeout(() => {
                copyLogMessageBtn.textContent = jsTranslations.modal_copy_to_clipboard;
            }, 2000);
        }
        document.body.removeChild(textArea);
    });

    // Fechar modal ao clicar no botão de fechar ou fora do conteúdo
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', closeLogMessageModal);
    });

    logMessageModal.addEventListener('click', (e) => {
        if (e.target === logMessageModal) {
            closeLogMessageModal();
        }
    });

    // Event Listeners para filtros de tempo
    document.querySelectorAll('.time-filter-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.time-filter-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            currentPeriod = button.dataset.period;
            currentPage = 1;
            fetchAndRenderLogs();
        });
    });

    // Event Listeners para abas de tipo de log
    document.querySelectorAll('.log-tab-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.log-tab-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            currentLogType = button.dataset.logType;
            currentPage = 1;
            fetchAndRenderLogs();
        });
    });

    // Event Listeners para paginação
    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchAndRenderLogs();
        }
    });

    nextPageBtn.addEventListener('click', () => {
        currentPage++;
        fetchAndRenderLogs();
    });

    // Carga inicial dos dados
    fetchAndRenderLogs();
});
</script>