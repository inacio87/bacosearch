<?php
/**
 * Módulo de Estatísticas do Admin (stats)
 * - Layout: Filtros de Tempo -> Gráfico de Linha -> Cards de Dados
 * - Funcionalidade: Dados e gráfico atualizam dinamicamente com base no filtro.
 */
if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// Carrega as traduções para os textos estáticos.
$title_main         = getTranslation('dashboard_stats_title', $languageCode, 'admin_dashboard');
$filter_5min        = getTranslation('filter_5_min', $languageCode, 'admin_dashboard');
$filter_today       = getTranslation('filter_today', $languageCode, 'admin_dashboard');
$filter_7d          = getTranslation('filter_7_days', $languageCode, 'admin_dashboard');
$filter_30d         = getTranslation('filter_30_days', $languageCode, 'admin_dashboard');
$filter_360d        = getTranslation('filter_360_days', $languageCode, 'admin_dashboard');
$chart_title        = getTranslation('chart_main_title', $languageCode, 'admin_dashboard');
$loading_text       = getTranslation('loading_data', $languageCode, 'admin_dashboard');
$error_loading_text = getTranslation('error_loading_stats', $languageCode, 'admin_dashboard');
$error_network_text = getTranslation('error_network', $languageCode, 'admin_dashboard');
?>

<div class="dashboard-module-wrapper">
    <div class="module-header">
        <h1><?= $title_main ?></h1>
    </div>

    <div id="stats-error" class="alert alert-danger" style="display: none;"></div>

    <div class="time-filters">
        <button class="btn time-filter-btn" data-period="5min"><?= $filter_5min ?></button>
        <button class="btn time-filter-btn" data-period="today"><?= $filter_today ?></button>
        <button class="btn time-filter-btn active" data-period="7d"><?= $filter_7d ?></button>
        <button class="btn time-filter-btn" data-period="30d"><?= $filter_30d ?></button>
        <button class="btn time-filter-btn" data-period="360d"><?= $filter_360d ?></button>
    </div>

    <div class="chart-container">
        <h3><?= $chart_title ?></h3>
        <canvas id="stats-chart"></canvas>
    </div>

    <div id="stats-cards" class="stats-cards-grid">
        <p><?= $loading_text ?></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let myChart = null;
    const errorDiv = document.getElementById('stats-error');
    const cardsContainer = document.getElementById('stats-cards');
    const chartCanvas = document.getElementById('stats-chart');

    function fetchAndRenderStats(period = '7d') {
        cardsContainer.innerHTML = `<p><?= $loading_text ?></p>`;
        errorDiv.style.display = 'none';

        fetch(`<?= SITE_URL ?>/api/dashboard_stats.php?period=${period}`)
            .then(response => response.ok ? response.json() : Promise.reject(new Error('Erro de rede ou servidor.')))
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || '<?= $error_loading_text ?>');
                }
                renderCards(data.totals, data.labels);
                renderChart(data.chart_data, data.labels);
            })
            .catch(err => {
                errorDiv.textContent = err.message;
                errorDiv.style.display = 'block';
                cardsContainer.innerHTML = '';
                console.error('Dashboard Error:', err);
            });
    }

    function renderCards(totals, labels) {
        cardsContainer.innerHTML = '';
        const cardInfo = [
            { key: 'unique_visitors_human', icon: '👥' },
            { key: 'registrations', icon: '📝' },
            { key: 'page_views_human', icon: '👁️' },
            { key: 'bots_total', icon: '🤖' }
        ];

        cardInfo.forEach(c => {
            const value = totals[c.key] ?? 0;
            const label = labels[c.key] ?? c.key;
            cardsContainer.innerHTML += `
                <div class="stat-card metric-data-card">
                    <div class="card-icon">${c.icon}</div>
                    <div class="card-content">
                        <span class="card-value">${value}</span>
                        <span class="card-title">${label}</span>
                    </div>
                </div>`;
        });
    }

    function renderChart(chartData, labels) {
        if (!chartData) return;
        const chartCtx = chartCanvas.getContext('2d');

        if (myChart) myChart.destroy();

        myChart = new Chart(chartCtx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: labels.page_views_human,
                        data: chartData.page_views_human,
                        borderColor: 'var(--bs-primary, #7c154f)',
                        backgroundColor: 'rgba(124, 21, 79, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: labels.unique_visitors_human,
                        data: chartData.unique_visitors_human,
                        borderColor: 'var(--bs-info, #36A2EB)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        titleColor: 'var(--bs-primary, #7c154f)',
                        titleFont: { weight: 'bold' },
                        bodyColor: '#333',
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        borderColor: 'var(--bs-border-color, #e0e0e0)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true
                    }
                }
            }
        });
    }

    const filterButtons = document.querySelectorAll('.time-filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            fetchAndRenderStats(button.dataset.period);
        });
    });

    fetchAndRenderStats('7d');
});
</script>