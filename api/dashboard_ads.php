<?php
// /admin/dashboard_ads.php - VERSÃO FINAL AJUSTADA PARA FILTRAR BOTS E ADMINS

// Se este ficheiro for acedido diretamente, precisa do bootstrap.
// Se for incluído pelo index.php, o bootstrap já foi carregado.
if (!defined('IN_BACOSEARCH')) {
    // Ajusta o caminho assumindo que este ficheiro está em /admin/ e bootstrap em /core/
    require_once dirname(__DIR__) . '/core/bootstrap.php';
}

// --- Definições de fuso horário globais ---
const DB_TIMEZONE = 'America/Sao_Paulo';
const APP_TIMEZONE = 'Europe/Lisbon';
const CURRENT_TIME_IN_APP_TZ_SQL = "CONVERT_TZ(NOW(), '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "')";


/**
 * Função principal para obter todos os anúncios da página inicial.
 *
 * @param PDO $pdo O objeto de conexão PDO.
 * @param string|null $country_code O código do país do utilizador (ex: 'PT').
 * @param string|null $region O nome do estado/região do utilizador (ex: 'Braga').
 * @param string|null $city O nome da cidade do utilizador (ex: 'Guimaraes').
 * @param bool $log_views Se deve registar as visualizações.
 * @return array Um array com os anúncios para cada slot.
 */
function getAllHomePageAds(PDO $pdo, ?string $country_code, ?string $region, ?string $city, bool $log_views = false): array
{
    $slots = ['global', 'national', 'regional_1', 'regional_2'];
    $ads = [];

    // Obter o visitor_db_id e verificar se é um admin ou bot
    $visitor_db_id = $_SESSION['visitor_db_id'] ?? null;
    $is_current_visitor_bot = isset($_SESSION['is_bot']) ? (bool)$_SESSION['is_bot'] : false;
    $is_current_visitor_admin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

    foreach ($slots as $slot) {
        $ad = getAdForSlot($pdo, $slot, $country_code, $region, $city);
        $ads[$slot] = $ad;

        // Somente loga a visualização se não for um bot e não for um admin
        if ($ad && $log_views && !$is_current_visitor_bot && !$is_current_visitor_admin) {
            logAdView($pdo, $ad['id']);
        }
    }
    return $ads;
}

/**
 * Busca um único anúncio para um slot, usando targeting por nome de país, região e cidade.
 *
 * @param PDO $pdo
 * @param string $slot O slot de posicionamento ('global', 'national', 'regional_1', 'regional_2').
 * @param string|null $user_country_code O código do país do utilizador.
 * @param string|null $user_region O nome da região/estado do utilizador.
 * @param string|null $user_city O nome da cidade do utilizador.
 * @return array|null O anúncio encontrado ou null.
 */
function getAdForSlot(PDO $pdo, string $slot, ?string $user_country_code, ?string $user_region, ?string $user_city): ?array
{
    $sql_base = "SELECT id, title, image_path, image_path_mobile, destination_url FROM advertisements WHERE ";
    $params = [':slot' => $slot];
    $conditions = [
        "is_active = 1",
        "placement_slot = :slot",
        CURRENT_TIME_IN_APP_TZ_SQL . " BETWEEN CONVERT_TZ(start_date, '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "') AND CONVERT_TZ(end_date, '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "')"
    ];

    switch ($slot) {
        case 'global':
            $conditions[] = "target_level = 'global'";
            break;

        case 'national':
            $conditions[] = "target_level = 'national'";
            if ($user_country_code) {
                $conditions[] = "country_code = :country_code";
                $params[':country_code'] = $user_country_code;
            } else {
                return null; // Impossível encontrar anúncio nacional sem o país do utilizador.
            }
            break;

        case 'regional_1':
        case 'regional_2':
            $conditions[] = "target_level = 'regional'";

            // Para um anúncio regional ser selecionado, os dados de localização do utilizador devem corresponder
            // exatamente aos dados definidos no anúncio.
            if ($user_country_code && $user_region && $user_city) {
                $conditions[] = "country_code = :country_code";
                $params[':country_code'] = $user_country_code;

                $conditions[] = "region_name = :region_name";
                $params[':region_name'] = $user_region;

                $conditions[] = "city_name = :city_name";
                $params[':city_name'] = $user_city;
            } else {
                return null; // Impossível encontrar anúncio regional sem a localização completa do utilizador.
            }
            break;
    }

    $sql = $sql_base . implode(' AND ', $conditions);
    $sql .= " ORDER BY RAND() LIMIT 1";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);
        return $ad ?: null;
    } catch (PDOException $e) {
        error_log("Error in getAdForSlot for slot '{$slot}': " . $e->getMessage());
        return null;
    }
}

// --- Funções de Log (AJUSTADAS para filtrar bots e admins) ---
function logAdView(PDO $pdo, int $ad_id): void { logAdStat($pdo, $ad_id, 'view'); }
function logAdClick(PDO $pdo, int $ad_id): void { logAdStat($pdo, $ad_id, 'click'); }

function logAdStat(PDO $pdo, int $ad_id, string $type): void
{
    // Obter o visitor_db_id e verificar se é um bot ou admin.
    // Essas variáveis são definidas no bootstrap.php e devem estar na sessão.
    $visitor_db_id = $_SESSION['visitor_db_id'] ?? null;
    $is_current_visitor_bot = isset($_SESSION['is_bot']) ? (bool)$_SESSION['is_bot'] : false;
    $is_current_visitor_admin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

    // NÃO LOGA estatísticas de anúncios se for um bot ou um admin.
    if ($is_current_visitor_bot || $is_current_visitor_admin) {
        // Opcional: logar que uma visualização/clique de bot/admin foi ignorada
        // log_system_error("Ad stat ignored for bot/admin (Ad ID: {$ad_id}, Type: {$type}, Visitor ID: {$visitor_db_id})", 'INFO', 'ad_stat_ignored');
        return;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $sql = "INSERT INTO ad_stats (ad_id, stat_type, visitor_id, ip_address, user_agent)
            VALUES (:ad_id, :stat_type, :visitor_id, :ip_address, :user_agent)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ad_id' => $ad_id,
            ':stat_type' => $type,
            ':visitor_id' => $visitor_db_id, // Usar o visitor_db_id da sessão
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log ad stat for ad_id {$ad_id}: " . $e->getMessage());
    }
}

// O bloco para acesso direto (API/debug) pode ser mantido ou removido se não for usado.
if (basename($_SERVER['PHP_SELF']) === 'dashboard_ads.php') {
    // Lógica de API/debug aqui...
    // Esta parte não precisa de alterações para o seu objetivo atual.
    exit();
}