<?php
/**
 * /modules/providers/logistics.php - Módulo de Logística (VERSÃO OTIMIZADA)
 *
 * RESPONSABILIDADES:
 * - Exibe e pré-preenche os campos de localização e logística do anúncio.
 * - Alinhado com a nova estrutura de banco de dados centralizada.
 *
 * ÚLTIMA ATUALIZAÇÃO: 14/07/2025 - Ajustada a lógica de pré-preenchimento.
 */

if (!defined('IN_BACOSEARCH')) {
    exit('Acesso direto não permitido.');
}

// Contextos de tradução
$logistics_form_context = 'provider_logistics_form';
$common_form_context = 'common_form';
$common_options_context = 'common_options';

// Arrays de opções
$serviceLocationsOptions = [
    'own_place' => 'service_location_own_place',
    'hotel' => 'service_location_hotel',
    'customer_home' => 'service_location_customer_home',
    'club' => 'service_location_club'
];
$amenitiesOptions = [
    'parking' => 'amenity_parking',
    'wifi' => 'amenity_wifi',
    'ac' => 'amenity_ac'
];

// --- LÓGICA DE PRÉ-PREENCHIMENTO CORRIGIDA ---
// $provider_data e $logistics_data são as fontes principais de dados do DB.
// $form_data é a fonte de dados da sessão (em caso de erro de validação).

// Dados de Localização (da tabela provider_logistics)
$current_ad_city_display = $form_data['ad_city'] ?? ($logistics_data['ad_city'] ?? '');
$current_ad_state_display = $form_data['ad_state'] ?? ($logistics_data['ad_state'] ?? '');
$current_ad_country_display = $form_data['ad_country'] ?? ($logistics_data['ad_country'] ?? '');
$current_ad_postal_code = $form_data['ad_postal_code'] ?? ($logistics_data['ad_postal_code'] ?? '');
$current_latitude = $form_data['ad_latitude'] ?? ($logistics_data['latitude'] ?? '');
$current_longitude = $form_data['ad_longitude'] ?? ($logistics_data['longitude'] ?? '');
$current_serves_nearby_cities = $form_data['serves_nearby_cities'] ?? ($logistics_data['serves_nearby_cities'] ?? '0');
$current_nearby_cities_radius = $form_data['nearby_cities_radius'] ?? ($logistics_data['nearby_cities_radius'] ?? '');

// Dados de Serviços e Comodidades (agora diretamente da tabela providers)
$current_service_locations = $form_data['service_locations'] ?? ($provider_data['service_locations'] ?? []);
$current_amenities = $form_data['amenities'] ?? ($provider_data['amenities'] ?? []);


// Traduções específicas para este módulo (esta parte não muda)
$translations['module_title_logistics'] = getTranslation('module_title_logistics', $languageCode, $logistics_form_context);
$translations['label_ad_city'] = getTranslation('label_ad_city', $languageCode, $logistics_form_context);
// ... (o resto das suas traduções permanece igual) ...

?>

<fieldset class="form-module" id="logistics-module">
    <legend><?php echo htmlspecialchars($translations['module_title_logistics'] ?? 'Logística do Anúncio'); ?></legend>
    <p class="module-description"><?php echo htmlspecialchars($translations['logistics_module_description'] ?? 'Defina a localização principal do seu serviço e raio de atendimento.'); ?></p>

    <div class="form-grid">
        <div class="form-group full-width">
            <label class="group-title" for="address-autocomplete-input"><i class="fas fa-city"></i> <?php echo htmlspecialchars($translations['label_ad_city'] ?? 'Cidade do Anúncio *'); ?></label>
            <input type="text" id="address-autocomplete-input" name="ad_city_autocomplete" class="form-control"
                   placeholder="<?php echo htmlspecialchars($translations['placeholder_ad_city'] ?? 'Digite o nome de uma cidade'); ?>"
                   value="<?php echo htmlspecialchars($current_ad_city_display); ?>" required autocomplete="off">

            <input type="hidden" id="ad_city_hidden" name="ad_city" value="<?php echo htmlspecialchars($current_ad_city_display); ?>">
            <input type="hidden" id="ad_state_hidden" name="ad_state" value="<?php echo htmlspecialchars($current_ad_state_display); ?>">
            <input type="hidden" id="ad_country_hidden" name="ad_country" value="<?php echo htmlspecialchars($current_ad_country_display); ?>">
            
            <!-- Corrigido para usar as novas variáveis de pré-preenchimento -->
            <input type="hidden" id="ad_latitude_hidden" name="ad_latitude" value="<?php echo htmlspecialchars($current_latitude); ?>">
            <input type="hidden" id="ad_longitude_hidden" name="ad_longitude" value="<?php echo htmlspecialchars($current_longitude); ?>">

            <div class="location-display-group">
                <input type="text" id="ad_city_display" class="form-control-display" value="<?php echo htmlspecialchars($current_ad_city_display); ?>" readonly placeholder="<?php echo htmlspecialchars($translations['placeholder_city'] ?? 'Cidade'); ?>">
                <input type="text" id="ad_state_display" class="form-control-display" value="<?php echo htmlspecialchars($current_ad_state_display); ?>" readonly placeholder="<?php echo htmlspecialchars($translations['placeholder_state'] ?? 'Estado'); ?>">
                <input type="text" id="ad_country_display" class="form-control-display" value="<?php echo htmlspecialchars($current_ad_country_display); ?>" readonly placeholder="<?php echo htmlspecialchars($translations['placeholder_country'] ?? 'País'); ?>">
            </div>
            
            <div class="invalid-feedback" id="city-error"></div>
        </div>

        <div class="form-group">
            <label for="ad_postal_code"><?php echo htmlspecialchars($translations['label_ad_postal_code'] ?? 'Código Postal do Serviço'); ?></label>
            <input type="text" id="ad_postal_code" name="ad_postal_code" class="form-control"
                   placeholder="<?php echo htmlspecialchars($translations['placeholder_ad_postal_code'] ?? 'Ex: 1234-567'); ?>"
                   value="<?php echo htmlspecialchars($current_ad_postal_code); ?>" maxlength="15">
        </div>

        <div class="form-group map-section full-width">
            <div id="map"></div>
        </div>

        <div class="form-group">
            <label class="group-title"><i class="fas fa-map-marked-alt"></i> <?php echo htmlspecialchars($translations['label_serves_nearby'] ?? 'Atende em Cidades Vizinhas? *'); ?></label>
            <div class="radio-group">
                <label class="radio-label"><input type="radio" name="serves_nearby_cities" value="1" class="serves-nearby" <?php echo $current_serves_nearby_cities == '1' ? 'checked' : ''; ?>><span class="control-indicator"></span> <?php echo htmlspecialchars($translations['option_yes'] ?? 'Sim'); ?></label>
                <label class="radio-label"><input type="radio" name="serves_nearby_cities" value="0" class="serves-nearby" <?php echo ($current_serves_nearby_cities == '0' || $current_serves_nearby_cities == '') ? 'checked' : ''; ?>><span class="control-indicator"></span> <?php echo htmlspecialchars($translations['option_no'] ?? 'Não'); ?></label>
            </div>
        </div>
        <div class="form-group radius-section <?php echo $current_serves_nearby_cities == '1' ? 'show' : ''; ?>">
            <label class="group-title" for="nearby_cities_radius"><i class="fas fa-road"></i> <?php echo htmlspecialchars($translations['label_radius'] ?? 'Raio de Atendimento (km)'); ?></label>
            <input type="number" id="nearby_cities_radius" name="nearby_cities_radius" class="form-control" min="1" max="500" value="<?php echo htmlspecialchars($current_nearby_cities_radius); ?>" placeholder="<?php echo htmlspecialchars($translations['placeholder_radius'] ?? 'Ex: 50'); ?>" <?php echo $current_serves_nearby_cities == '1' ? 'required' : ''; ?>>
        </div>
        <div class="form-group">
            <label class="group-title"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($translations['label_service_locations'] ?? 'Atende em *'); ?></label>
            <div class="checkbox-columns">
                <?php foreach ($serviceLocationsOptions as $value => $translation_key): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="service_locations[]" value="<?php echo htmlspecialchars($value); ?>" class="service-location" id="<?php echo htmlspecialchars($value); ?>" <?php echo in_array($value, $current_service_locations) ? 'checked' : ''; ?>>
                        <span class="control-indicator"></span>
                        <?php echo htmlspecialchars($translations[$translation_key] ?? $value); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group amenities-section <?php echo (in_array('own_place', $current_service_locations) || in_array('club', $current_service_locations)) ? 'show' : ''; ?>">
            <label class="group-title"><i class="fas fa-concierge-bell"></i> <?php echo htmlspecialchars($translations['label_amenities'] ?? 'Comodidades do Local'); ?></label>
            <div class="checkbox-columns">
                <?php foreach ($amenitiesOptions as $value => $translation_key): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars($value); ?>" <?php echo in_array($value, $current_amenities) ? 'checked' : ''; ?>>
                        <span class="control-indicator"></span>
                        <?php echo htmlspecialchars($translations[$translation_key] ?? $value); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</fieldset>

<!-- O JAVASCRIPT E O CARREGAMENTO DA API DO GOOGLE MAPS PERMANECEM IDÊNTICOS, POIS JÁ ESTÃO CORRETOS -->
<script>
    function gm_authFailure() { /* ...código idêntico... */ }

    function initMapAndAutocomplete() {
        const autocompleteInput = document.getElementById('address-autocomplete-input');
        if (!autocompleteInput) return;

        const cityDisplay = document.getElementById('ad_city_display');
        const stateDisplay = document.getElementById('ad_state_display');
        const countryDisplay = document.getElementById('ad_country_display');
        const postalCodeInput = document.getElementById('ad_postal_code');
        const cityHidden = document.getElementById('ad_city_hidden');
        const stateHidden = document.getElementById('ad_state_hidden');
        const countryHidden = document.getElementById('ad_country_hidden');
        const latitudeHidden = document.getElementById('ad_latitude_hidden');
        const longitudeHidden = document.getElementById('ad_longitude_hidden');
        const mapDiv = document.getElementById('map');

        try {
            const map = new google.maps.Map(mapDiv, {
                center: { lat: 40.2033, lng: -8.4103 }, // Coimbra, Portugal
                zoom: 7,
                streetViewControl: false,
                mapTypeControl: false,
            });
            const marker = new google.maps.Marker({ map: map });

            // Se já houver lat/lon, centraliza o mapa e posiciona o marcador
            const initialLat = parseFloat(latitudeHidden.value);
            const initialLng = parseFloat(longitudeHidden.value);
            if (!isNaN(initialLat) && !isNaN(initialLng)) {
                const initialPos = { lat: initialLat, lng: initialLng };
                map.setCenter(initialPos);
                map.setZoom(12);
                marker.setPosition(initialPos);
                marker.setVisible(true);
            }

            const autocomplete = new google.maps.places.Autocomplete(autocompleteInput, {
                types: ['(cities)'],
                fields: ['address_components', 'geometry', 'place_id']
            });

            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                const cityError = document.getElementById('city-error');

                if (!place.geometry || !place.address_components) {
                    cityError.style.display = 'block';
                    cityError.textContent = 'Cidade inválida ou sem dados suficientes.';
                    return;
                }
                cityError.style.display = 'none';

                let city = '', state = '', country = '', postalCode = '';
                let lat = place.geometry.location.lat();
                let lng = place.geometry.location.lng();

                for (const component of place.address_components) {
                    const types = component.types;
                    if (types.includes('locality')) city = component.long_name;
                    if (types.includes('administrative_area_level_1')) state = component.long_name;
                    if (types.includes('country')) country = component.long_name;
                    if (types.includes('postal_code')) postalCode = component.long_name;
                }

                autocompleteInput.value = city;
                cityDisplay.value = city;
                stateDisplay.value = state;
                countryDisplay.value = country;
                if (postalCodeInput) postalCodeInput.value = postalCode;
                cityHidden.value = city;
                stateHidden.value = state;
                countryHidden.value = country;
                latitudeHidden.value = lat;
                longitudeHidden.value = lng;

                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter(place.geometry.location);
                    map.setZoom(12);
                }
                marker.setPosition(place.geometry.location);
                marker.setVisible(true);
            });
        } catch (e) {
            console.error("Erro ao inicializar o Google Maps: ", e);
            const cityError = document.getElementById('city-error');
            if(cityError) {
                cityError.style.display = 'block';
                cityError.textContent = 'Ocorreu um erro ao carregar o mapa.';
            }
        }

        document.querySelectorAll('.serves-nearby').forEach(radio => {
            radio.addEventListener('change', function() {
                const radiusSection = document.querySelector('.radius-section');
                radiusSection.classList.toggle('show', this.value === '1');
                document.getElementById('nearby_cities_radius').required = this.value === '1';
            });
        });

        document.querySelectorAll('.service-location').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const ownPlaceChecked = document.getElementById('own_place').checked;
                const clubChecked = document.getElementById('club').checked;
                const amenitiesSection = document.querySelector('.amenities-section');
                amenitiesSection.classList.toggle('show', ownPlaceChecked || clubChecked);
            });
        });
    }
</script>

<?php
$googleMapsApiKey = API_CONFIG['Maps_API_KEY'] ?? '';
if (!empty($googleMapsApiKey)) {
    echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . htmlspecialchars($googleMapsApiKey ) . '&libraries=places&callback=initMapAndAutocomplete" async defer></script>';
} else {
    log_system_error("Chave da API do Google Maps não definida.", 'CRITICAL', 'google_api_key_missing');
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() { 
            const cityError = document.getElementById('city-error');
            if(cityError) {
                cityError.style.display = 'block'; 
                cityError.textContent = 'Erro Crítico: A chave da API de Mapas não está configurada no servidor.';
            }
        });
    </script>";
}
?>
