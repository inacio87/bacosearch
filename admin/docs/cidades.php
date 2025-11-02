<?php
/**
 * /pages/cidade.php - Página para exibir mapa e autocomplete de cidades.
 *
 * RESPONSABILIDADES:
 * 1. Carregar o arquivo de configuração central da aplicação para obter a chave de API.
 * 2. Injetar a chave da API do Google no script do lado do cliente (JavaScript).
 * 3. Renderizar um mapa interativo e um campo de input com a funcionalidade de Places Autocomplete.
 */

// Caminho para o arquivo de configuração, ajustado para a estrutura do servidor.
// Sobe dois níveis de diretório a partir de 'pages' para encontrar 'config.php'.
$configFile = __DIR__ . '/../../config.php';

// Verificação de segurança: garante que o arquivo de configuração exista.
if (!file_exists($configFile)) {
    http_response_code(500);
    // Exibe uma mensagem de erro amigável. Detalhes do erro podem ser logados internamente.
    die('Ocorreu um erro crítico na configuração do servidor. Por favor, tente novamente mais tarde.');
}

// Carrega as configurações, que definem a constante API_CONFIG.
require_once $configFile;

// Obtém a chave da API a partir da constante carregada do config.php.
$apiKey = API_CONFIG['Maps_API_KEY'] ?? null;

// Verificação de segurança: Interrompe a execução se a chave não for encontrada.
if (empty($apiKey)) {
    http_response_code(500);
    die('A chave da API do Google Maps não está configurada corretamente.');
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de API do Google Maps e Places</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        #map { height: 400px; width: 100%; border: 1px solid #ccc; margin-top: 20px; background-color: #e0e0e0; }
        #status { padding: 10px; margin-top: 10px; font-weight: bold; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        input { width: 100%; padding: 10px; font-size: 16px; box-sizing: border-box; margin-bottom: 20px; }
        h1, h2 { color: #333; }
    </style>
</head>
<body>

    <h1>Teste de API do Google Maps e Places (Integrado com PHP)</h1>
    <p>Se a chave de API do seu ficheiro <code>config.php</code> estiver correta, você verá uma mensagem de sucesso, um mapa e o campo abaixo irá sugerir cidades.</p>

    <div id="status">Aguardando carregamento da API...</div>

    <h2>Teste de Autocomplete de Cidades</h2>
    <input type="text" id="autocomplete-input" placeholder="Comece a digitar o nome de uma cidade...">

    <h2>Teste de Mapa</h2>
    <div id="map"></div>

    <script>
        // Função de callback global para falhas de autenticação da API do Google.
        function gm_authFailure() {
            const statusDiv = document.getElementById('status');
            statusDiv.className = 'error';
            statusDiv.innerHTML = `
                <strong>ERRO DE AUTENTICAÇÃO!</strong> A chave de API carregada via PHP é inválida, expirou, ou não tem permissão para usar as APIs necessárias.<br>
                Verifique se as APIs "Maps JavaScript API" e "Places API" estão ativadas na sua conta Google Cloud e se a faturação está ativa.
            `;
        }

        // Função de callback chamada quando a API do Google Maps carrega com sucesso.
        function initMapAndPlaces() {
            const statusDiv = document.getElementById('status');
            statusDiv.className = 'success';
            statusDiv.textContent = 'SUCESSO: API do Google Maps e Places carregada corretamente.';

            // Inicializa o mapa. Coordenadas de exemplo (Guimarães, Portugal).
            const map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 41.4426, lng: -8.291 },
                zoom: 8,
            });

            // Inicializa o autocomplete no campo de input.
            const input = document.getElementById('autocomplete-input');
            const autocomplete = new google.maps.places.Autocomplete(input, {
                types: ['(cities)'] // Restringe as sugestões a cidades.
            });

            // Adiciona um ouvinte para quando um lugar é selecionado.
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (place.name) {
                    statusDiv.textContent = 'Autocomplete funcionou! Você selecionou: ' + place.name;
                }
            });
        }
    </script>
    
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($apiKey); ?>&libraries=places&callback=initMapAndPlaces"></script>

</body>
</html>