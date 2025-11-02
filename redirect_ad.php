<?php
// /redirect_ad.php

// Ajuste o caminho para o bootstrap.php
require_once __DIR__ . '/core/bootstrap.php';

// ATENÇÃO: O arquivo core/ads.php foi movido/renomeado.
// Agora, as funções de anúncio (como logAdStat) estão em /admin/dashboard_ads.php.
// Se este arquivo 'redirect_ad.php' está na raiz do projeto, e dashboard_ads.php está em /admin/,
// o caminho para incluir as funções será:
require_once __DIR__ . '/admin/dashboard_ads.php'; // Inclui as funções de anúncio ajustadas

// Configura o fuso horário do PHP para a aplicação.
// Embora não afete diretamente a query aqui, é boa prática para consistência.
date_default_timezone_set('Europe/Lisbon'); // <-- Mude para o seu fuso horário se não for Lisboa

$ad_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ad_id) {
    // Se não houver ID, redireciona para a página inicial
    header('Location: ' . SITE_URL);
    exit;
}

// Obtém a conexão PDO. Presume que getDBConnection() está definido em bootstrap.php
$pdo = getDBConnection();

// Pega a URL de destino do anúncio
// Esta query não precisa de ajuste de fuso horário pois busca por ID, não por data.
$stmt = $pdo->prepare("SELECT destination_url FROM advertisements WHERE id = ?");
$stmt->execute([$ad_id]);
$destination_url = $stmt->fetchColumn();

if ($destination_url) {
    // Regista o clique ANTES de redirecionar
    // A função logAdStat já foi ajustada em /admin/dashboard_ads.php para lidar com o fuso horário
    // ao inserir o timestamp.
    logAdStat($pdo, $ad_id, 'click');
    
    // Redireciona o utilizador para o destino final
    header('Location: ' . $destination_url);
    exit;
} else {
    // Se o anúncio não existir, redireciona para a página inicial
    header('Location: ' . SITE_URL);
    exit;
}