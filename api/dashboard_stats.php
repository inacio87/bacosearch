<?php
/**
 * /api/dashboard_stats.php — API de Estatísticas do Dashboard (anti‑bot, consistente)
 * ÚLTIMA ATUALIZAÇÃO: 11/08/2025
 */

declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/functions.php';

date_default_timezone_set('Europe/Lisbon');
header('Content-Type: application/json');

function compute_period_bounds(string $period, string $appTZ, string $dbTZ): array {
    $tzApp = new DateTimeZone($appTZ);
    $tzDb  = new DateTimeZone($dbTZ);
    $nowApp = new DateTime('now', $tzApp);

    switch ($period) {
        case '5min': $startApp=(clone $nowApp)->modify('-5 minutes'); $endApp=(clone $nowApp); break;
        case 'today': $startApp=(clone $nowApp)->setTime(0,0,0); $endApp=(clone $nowApp)->setTime(23,59,59); break;
        case '7d':   $startApp=(clone $nowApp)->modify('-7 days');  $endApp=(clone $nowApp); break;
        case '30d':  $startApp=(clone $nowApp)->modify('-30 days'); $endApp=(clone $nowApp); break;
        case '360d': $startApp=(clone $nowApp)->modify('-360 days');$endApp=(clone $nowApp); break;
        default:     $startApp=(clone $nowApp)->setTime(0,0,0); $endApp=(clone $nowApp)->setTime(23,59,59); break;
    }
    return [
        ':start_at' => (clone $startApp)->setTimezone($tzDb)->format('Y-m-d H:i:s'),
        ':end_at'   => (clone $endApp)->setTimezone($tzDb)->format('Y-m-d H:i:s'),
    ];
}

function make_in_params(array $ids, string $prefix = ':id'): array {
    $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn($x)=>$x!==null))));
    $placeholders = []; $params = [];
    foreach ($ids as $i => $id) { $k = $prefix.$i; $placeholders[]=$k; $params[$k]=$id; }
    return [$placeholders, $params];
}

try {
    $db = getDBConnection();

    $allowed = ['5min','today','7d','30d','360d'];
    $period = (isset($_GET['period']) && in_array($_GET['period'], $allowed, true)) ? $_GET['period'] : 'today';

    // FUSOS
    $dbTZ  = 'America/Sao_Paulo';
    $appTZ = 'Europe/Lisbon';

    $bounds = compute_period_bounds($period, $appTZ, $dbTZ);
    $start_at = $bounds[':start_at'];
    $end_at   = $bounds[':end_at'];

    // Opcional: marcar page_views.is_bot_view via system_logs no período
    $stUpd = $db->prepare("
        UPDATE page_views pv
        JOIN system_logs sl ON pv.visitor_id = sl.visitor_id
        SET pv.is_bot_view = 1
        WHERE sl.created_at BETWEEN :start_at AND :end_at
          AND sl.context IN ('bot_detection','bot_db_blocked')
    ");
    $stUpd->execute([':start_at'=>$start_at, ':end_at'=>$end_at]);

    // --- listas de exclusão ---
    // admins
    $adminVisitorIds = [];
    if (!empty($_SESSION['visitor_db_id'])) $adminVisitorIds[] = (int)$_SESSION['visitor_db_id'];
    try {
        $roleId = $db->query("SELECT id FROM access_roles WHERE slug='admin' AND is_active=1 LIMIT 1")->fetchColumn();
        if ($roleId) {
            $stAdm = $db->prepare("
                SELECT DISTINCT v.id
                FROM visitors v
                JOIN accounts a ON v.ip_address = a.ip_address
                WHERE a.role_id = :rid AND a.status = 'active'
            ");
            $stAdm->execute([':rid' => (int)$roleId]);
            $adminVisitorIds = array_merge($adminVisitorIds, array_map('intval', $stAdm->fetchAll(PDO::FETCH_COLUMN)));
        }
    } catch (Throwable $e) { /* pode não existir access_roles */ }
    $adminVisitorIds = array_values(array_unique($adminVisitorIds));
    [$adminPH, $adminParams] = make_in_params($adminVisitorIds, ':adm');

    // bots via logs
    $stBots = $db->prepare("
        SELECT DISTINCT visitor_id
        FROM system_logs
        WHERE created_at BETWEEN :start_at AND :end_at
          AND context IN ('bot_detection','bot_db_blocked')
          AND visitor_id IS NOT NULL AND visitor_id != 0
    ");
    $stBots->execute([':start_at'=>$start_at, ':end_at'=>$end_at]);
    $botVisitorIds = array_map('intval', $stBots->fetchAll(PDO::FETCH_COLUMN));
    [$botPH, $botParams] = make_in_params($botVisitorIds, ':bot');

    // Condições dinâmicas (NADA de NOT IN (NULL)!)
    $condAdmins = $adminPH ? (' AND pv.visitor_id NOT IN ('.implode(',',$adminPH).')') : '';
    $condBots   = $botPH   ? (' AND pv.visitor_id NOT IN ('.implode(',',$botPH).')')   : '';

    $commonParams = [':start_at'=>$start_at, ':end_at'=>$end_at] + $adminParams + $botParams;
    $pageFilter = "pv.page_url IS NOT NULL AND pv.page_url != '' AND (pv.page_url LIKE '%.php%' OR pv.page_url NOT LIKE '%.%')";

    // --- totais ---
    $sqlPV = "
        SELECT COUNT(*)
        FROM page_views pv
        JOIN visitors v ON v.id = pv.visitor_id
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND v.is_bot = 0
          AND pv.is_bot_view = 0
          AND $pageFilter
          $condAdmins
          $condBots
    ";
    $stPV = $db->prepare($sqlPV);
    $stPV->execute($commonParams);
    $pageViewsHuman = (int)$stPV->fetchColumn();

    $sqlUV = "
        SELECT COUNT(DISTINCT pv.visitor_id)
        FROM page_views pv
        JOIN visitors v ON v.id = pv.visitor_id
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND v.is_bot = 0
          AND pv.is_bot_view = 0
          AND $pageFilter
          $condAdmins
          $condBots
    ";
    $stUV = $db->prepare($sqlUV);
    $stUV->execute($commonParams);
    $uniqueVisitorsHuman = (int)$stUV->fetchColumn();

    $stReg = $db->prepare("SELECT COUNT(*) FROM accounts WHERE created_at BETWEEN :start_at AND :end_at");
    $stReg->execute([':start_at'=>$start_at, ':end_at'=>$end_at]);
    $registrations = (int)$stReg->fetchColumn();

    $botsTotal = count($botVisitorIds);

    // --- charts (mesma filtragem) ---
    $isHourly = ($period === 'today' || $period === '5min');
    $dateFmt  = $isHourly ? '%Y-%m-%d %H' : '%Y-%m-%d';

    $sqlChartPV = "
        SELECT DATE_FORMAT(pv.visit_timestamp, '$dateFmt') AS tkey, COUNT(*) AS val
        FROM page_views pv
        JOIN visitors v ON v.id = pv.visitor_id
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND v.is_bot = 0
          AND pv.is_bot_view = 0
          AND $pageFilter
          $condAdmins
          $condBots
        GROUP BY tkey
        ORDER BY tkey
    ";
    $stCPV = $db->prepare($sqlChartPV);
    $stCPV->execute($commonParams);
    $pvRows = $stCPV->fetchAll(PDO::FETCH_KEY_PAIR);

    $sqlChartUV = "
        SELECT DATE_FORMAT(pv.visit_timestamp, '$dateFmt') AS tkey, COUNT(DISTINCT pv.visitor_id) AS val
        FROM page_views pv
        JOIN visitors v ON v.id = pv.visitor_id
        WHERE pv.visit_timestamp BETWEEN :start_at AND :end_at
          AND v.is_bot = 0
          AND pv.is_bot_view = 0
          AND $pageFilter
          $condAdmins
          $condBots
        GROUP BY tkey
        ORDER BY tkey
    ";
    $stCUV = $db->prepare($sqlChartUV);
    $stCUV->execute($commonParams);
    $uvRows = $stCUV->fetchAll(PDO::FETCH_KEY_PAIR);

    // eixo completo
    $labels = []; $chartPV=[]; $chartUV=[];
    if ($isHourly) {
        $startDbDT = new DateTime($start_at, new DateTimeZone($dbTZ));
        $dayStart  = (clone $startDbDT)->setTime(0,0,0);
        $tzLisbon  = new DateTimeZone($appTZ);
        for ($h=0; $h<24; $h++) {
            $key = $dayStart->format('Y-m-d ').str_pad((string)$h, 2, '0', STR_PAD_LEFT);
            $labelDT = DateTime::createFromFormat('Y-m-d H', $key, new DateTimeZone($dbTZ));
            $labels[] = $labelDT ? $labelDT->setTimezone($tzLisbon)->format('H:00') : sprintf('%02d:00',$h);
            $chartPV[] = (int)($pvRows[$key] ?? 0);
            $chartUV[] = (int)($uvRows[$key] ?? 0);
        }
    } else {
        $cursor = new DateTime($start_at, new DateTimeZone($dbTZ));
        $endDT  = new DateTime($end_at,   new DateTimeZone($dbTZ));
        $fmt = ($period === '7d' || $period === '30d') ? 'd/m' : 'd/m/y';
        while ($cursor <= $endDT) {
            $key = $cursor->format('Y-m-d');
            $labelDT = DateTime::createFromFormat('Y-m-d', $key, new DateTimeZone($dbTZ));
            $labels[] = $labelDT ? $labelDT->setTimezone(new DateTimeZone($appTZ))->format($fmt) : $key;
            $chartPV[] = (int)($pvRows[$key] ?? 0);
            $chartUV[] = (int)($uvRows[$key] ?? 0);
            $cursor->modify('+1 day');
        }
    }

    $language = $_SESSION['language'] ?? 'pt-br';
    $context  = 'admin_dashboard';

    echo json_encode([
        'success' => true,
        'totals' => [
            'unique_visitors_human' => $uniqueVisitorsHuman,
            'page_views_human'      => $pageViewsHuman,
            'registrations'         => $registrations,
            'bots_total'            => $botsTotal
        ],
        'chart_data' => [
            'labels'                => $labels,
            'page_views_human'      => $chartPV,
            'unique_visitors_human' => $chartUV
        ],
        'labels' => [
            'unique_visitors_human' => getTranslation('metric_unique_visitors_human', $language, $context),
            'page_views_human'      => getTranslation('metric_page_views_human', $language, $context),
            'registrations'         => getTranslation('metric_registrations', $language, $context),
            'bots_total'            => getTranslation('metric_bots_total', $language, $context)
        ],
        // opcional para debug rápido do fuso:
        'period_window' => ['start_db' => $start_at, 'end_db' => $end_at]
    ]);

} catch (Throwable $e) {
    if (function_exists('log_system_error')) {
        log_system_error("Dashboard Stats Error: ".$e->getMessage(), 'ERROR', 'dashboard_stats_api_exception');
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro ao consultar os dados das estatísticas.']);
}
