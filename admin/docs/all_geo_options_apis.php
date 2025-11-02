<?php
/**
 * /admin/docs/all_geo_options_apis.php
 * Ranking das 3 APIs (Google, ipwho.is, ip-api.com) contra múltiplos alvos fixos.
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

echo ">>> Consulta realizada em: " . date('Y-m-d H:i:s') . " UTC\n\n";

require_once dirname(__DIR__, 2) . '/core/config.php';

// --- Múltiplos Alvos Fixos ---
// Adicione mais alvos conforme necessário para cobrir suas principais regiões de usuários
const TEST_TARGETS = [
    // Alvo 1: Portugal (Guimarães)
    [
        'name' => 'Guimaraes, Portugal',
        'ip' => '94.132.32.57', // Exemplo de IP em Guimarães
        'postal' => '4810-000',
        'lat' => 41.442,
        'lon' => -8.29561,
    ],
    // Alvo 2: Portugal (Lisboa)
    [
        'name' => 'Lisboa, Portugal',
        'ip' => '213.30.114.42', // Exemplo de IP em Lisboa
        'postal' => '1100-000',
        'lat' => 38.7071,
        'lon' => -9.13549,
    ],
    // Alvo 3: Espanha (Madrid)
    [
        'name' => 'Madrid, Espanha',
        'ip' => '195.235.92.26', // Exemplo de IP em Madrid
        'postal' => '28001',
        'lat' => 40.416775,
        'lon' => -3.703790,
    ],
    // Alvo 4: Espanha (Barcelona)
    [
        'name' => 'Barcelona, Espanha',
        'ip' => '217.13.124.105', // Exemplo de IP em Barcelona
        'postal' => '08001',
        'lat' => 41.3873974,
        'lon' => 2.168568,
    ],
    // Alvo 5: Alemanha (Frankfurt)
    [
        'name' => 'Frankfurt, Alemanha',
        'ip' => '185.93.180.131', // Exemplo de IP em Frankfurt
        'postal' => '60313',
        'lat' => 50.110924,
        'lon' => 8.682127,
    ],
    // Alvo 6: Hong Kong
    [
        'name' => 'Hong Kong',
        'ip' => '182.239.127.137', // Exemplo de IP em Hong Kong
        'postal' => '999077',
        'lat' => 22.356514,
        'lon' => 114.136253,
    ],
    // Alvo 7: EUA (Miami)
    [
        'name' => 'Miami, EUA',
        'ip' => '50.73.157.178', // Exemplo de IP em Miami
        'postal' => '33132',
        'lat' => 25.7616798,
        'lon' => -80.1917902,
    ],
    // Alvo 8: EUA (Nova York)
    [
        'name' => 'New York, EUA',
        'ip' => '63.116.61.253', // Exemplo de IP em Nova York
        'postal' => '10007',
        'lat' => 40.730610,
        'lon' => -73.935242,
    ],
    // Alvo 9: EUA (Los Angeles)
    [
        'name' => 'Los Angeles, EUA',
        'ip' => '104.174.125.138', // Exemplo de IP em Los Angeles
        'postal' => '90001',
        'lat' => 34.052235,
        'lon' => -118.243683,
    ],
    // Alvo 10: Brasil (São Paulo)
    [
        'name' => 'Sao Paulo, Brasil',
        'ip' => '177.192.255.38', // Exemplo de IP em São Paulo
        'postal' => '01000-000',
        'lat' => -23.5489,
        'lon' => -46.6388,
    ],
    // Alvo 11: Brasil (Rio de Janeiro)
    [
        'name' => 'Rio de Janeiro, Brasil',
        'ip' => '150.165.212.10', // Exemplo de IP no Rio de Janeiro
        'postal' => '20000-000',
        'lat' => -22.908333,
        'lon' => -43.196388,
    ],
    // Alvo 12: Brasil (Belo Horizonte)
    [
        'name' => 'Belo Horizonte, Brasil',
        'ip' => '150.164.117.205', // Exemplo de IP em Belo Horizonte
        'postal' => '30000-001',
        'lat' => -19.912998,
        'lon' => -43.940933,
    ],
    // Alvo 13: China (Xangai)
    [
        'name' => 'Xangai, China',
        'ip' => '61.151.178.177', // Exemplo de IP em Xangai
        'postal' => '200000',
        'lat' => 31.224361,
        'lon' => 121.469170,
    ],
    // Alvo 14: Japão (Tóquio)
    [
        'name' => 'Tóquio, Japão',
        'ip' => '210.138.184.59', // Exemplo de IP em Tóquio
        'postal' => '100-0001',
        'lat' => 35.652832,
        'lon' => 139.839478,
    ],
];

$timeout = API_CONFIG['timeout'] ?? 10;

// --- Funções (mantidas as mesmas) ---
function haversineDistance($lat1, $lon1, $lat2, $lon2): float {
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    return round($earthRadius * (2 * atan2(sqrt($a), sqrt(1-$a))), 2);
}

function testApiAndReturnResult($name, callable $callback, $targetLat, $targetLon): array {
    $result = $callback();
    $res = [
        'name'      => $name,
        'country'   => $result['country'] ?? 'N/A',
        'region'    => $result['region'] ?? 'N/A',
        'city'      => $result['city'] ?? 'N/A',
        'postal'    => $result['postal_code'] ?? 'N/A',
        'latitude'  => $result['latitude'] ?? 'N/A',
        'longitude' => $result['longitude'] ?? 'N/A',
        'error'     => $result['error'] ?? null,
        'distance'  => null
    ];
    if (is_numeric($res['latitude']) && is_numeric($res['longitude'])) {
        $res['distance'] = haversineDistance($targetLat, $targetLon, $res['latitude'], $res['longitude']);
    }
    return $res;
}

// --- Execução para Múltiplos Alvos ---
$overallBestApis = []; // Para armazenar a melhor API por alvo

foreach (TEST_TARGETS as $target) {
    echo "\n=== Testando para: {$target['name']} (IP: {$target['ip']}) ===\n";
    $apiResultsForTarget = [];

    // Google Maps Geocoding (usando o CEP do alvo)
    // Note: Para uma comparação mais justa com IPs, o Google Maps Geocoding deveria ser usado para reverso de coordenadas,
    // ou você precisaria de uma API de geolocalização de IP do Google, que não é o Maps Geocoding.
    // Mantido aqui para demonstrar a estrutura, mas considere a sugestão de normalização dos testes.
    $apiResultsForTarget[] = testApiAndReturnResult('Google Maps Geocoding (CEP alvo)', function() use ($timeout, $target) {
        if (empty(API_CONFIG['Maps_API_KEY'])) {
            return ['error' => 'Chave Google Maps API não configurada.'];
        }
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($target['postal'] ) . "&key=" . API_CONFIG['Maps_API_KEY'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (($data['status'] ?? '') === 'OK' && !empty($data['results'][0])) {
            $r = $data['results'][0];
            $lat = $r['geometry']['location']['lat'];
            $lon = $r['geometry']['location']['lng'];
            $city = $region = $country = $postal = null;
            foreach ($r['address_components'] as $comp) {
                if (in_array('locality', $comp['types'])) $city = $comp['long_name'];
                if (in_array('country', $comp['types'])) $country = $comp['short_name'];
                if (in_array('postal_code', $comp['types'])) $postal = $comp['long_name'];
                if (in_array('administrative_area_level_1', $comp['types'])) $region = $comp['long_name'];
            }
            return ['country'=>$country,'region'=>$region,'city'=>$city,'postal_code'=>$postal,'latitude'=>$lat,'longitude'=>$lon];
        }
        return ['error' => 'Falha Google Geocoding'];
    }, $target['lat'], $target['lon']);

    // ipwho.is (usando o IP do alvo)
    $apiResultsForTarget[] = testApiAndReturnResult('ipwho.is', function() use ($timeout, $target) {
        $url = "https://ipwho.is/" . $target['ip'];
        $ch = curl_init($url );
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout]);
        $response = curl_exec($ch);
        curl_close($ch);
        $d = json_decode($response,true);
        if (($d['success'] ?? false) && isset($d['latitude'])) {
            return [
                'country'=>$d['country_code'],
                'region'=>$d['region'],
                'city'=>$d['city'],
                'postal_code'=>$d['postal'],
                'latitude'=>$d['latitude'],
                'longitude'=>$d['longitude']
            ];
        }
        return ['error'=>'Falha ipwho.is'];
    }, $target['lat'], $target['lon']);

    // ip-api.com (usando o IP do alvo)
    $apiResultsForTarget[] = testApiAndReturnResult('ip-api.com', function() use ($timeout, $target) {
        $url = "http://ip-api.com/json/" . $target['ip'];
        $ch = curl_init($url );
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout]);
        $response = curl_exec($ch);
        curl_close($ch);
        $d = json_decode($response,true);
        if (($d['status'] ?? '')==='success') {
            return [
                'country'=>$d['countryCode'],
                'region'=>$d['regionName'],
                'city'=>$d['city'],
                'postal_code'=>$d['zip'],
                'latitude'=>$d['lat'],
                'longitude'=>$d['lon']
            ];
        }
        return ['error'=>'Falha ip-api.com'];
    }, $target['lat'], $target['lon']);

    // --- Ranking para o Alvo Atual ---
    $sortableForTarget = array_filter($apiResultsForTarget, function ($a) {
        return $a['distance'] !== null;
    });
    usort($sortableForTarget, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    $winnerForTarget = $sortableForTarget[0] ?? null;
    if ($winnerForTarget && empty($winnerForTarget['error'])) {
        $overallBestApis[$target['name']] = [
            'api_name' => $winnerForTarget['name'],
            'avg_distance' => $winnerForTarget['distance'],
            'target_name' => $target['name'], // Novo campo para identificar o alvo
        ];
    }

    // --- Output para o Alvo Atual ---
    echo "--- Ranking das APIs para {$target['name']} ---\n";
    echo "Alvo: CEP {$target['postal']} | Lat {$target['lat']} | Lon {$target['lon']} \n";
    echo "IP fixo usado: {$target['ip']} \n\n";

    $rank = 1;
    foreach ($sortableForTarget as $api) {
        echo "Rank {$rank}: {$api['name']} \n";
        echo "  País: {$api['country']} \n";
        echo "  Região: {$api['region']} \n";
        echo "  Cidade: {$api['city']} \n";
        echo "  CEP: {$api['postal']} \n";
        echo "  Lat/Lon: {$api['latitude']}, {$api['longitude']} \n";
        echo "  Distância do alvo: {$api['distance']} km \n";
        echo "  Erro: " . ($api['error'] ?? 'Nenhum') . " \n";
        echo "--------------------------------------------------\n\n";
        $rank++;
    }
}

// --- Persistência Final (Atualizar a tabela geo_api_status) ---
try {
    $pdo = getDBConnection();
    // Limpa a seleção anterior, se necessário, ou gerencia por target_name
    // Para este exemplo, vamos atualizar/inserir por target_name
    foreach ($overallBestApis as $targetName => $bestApi) {
        $stmt = $pdo->prepare("
            INSERT INTO geo_api_status (api_name, avg_distance, last_checked_at, is_selected, target_name)
            VALUES (?, ?, NOW(), 1, ?)
            ON DUPLICATE KEY UPDATE 
                api_name = VALUES(api_name),
                avg_distance = VALUES(avg_distance),
                last_checked_at = NOW(),
                is_selected = 1
        ");
        // Note: A coluna `is_selected` pode precisar de uma lógica mais complexa se você quiser uma única API global
        // ou se você tiver um campo `is_selected` por `target_name`.
        // Para este exemplo, `is_selected` = 1 significa que é a melhor para aquele `target_name`.
        $stmt->execute([$bestApi['api_name'], $bestApi['avg_distance'], $bestApi['target_name']]);
    }
    echo "\n>>> Atualização da tabela geo_api_status concluída para todos os alvos.\n";
} catch (Throwable $e) {
    error_log("Erro ao atualizar geo_api_status: " . $e->getMessage());
    echo "\n>>> ERRO ao atualizar geo_api_status: " . $e->getMessage() . "\n";
}

?>
