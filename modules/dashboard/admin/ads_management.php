<?php
/**
 * /modules/dashboard/admin/ads_management.php - VERSÃO FINAL E TOTALMENTE COMPATÍVEL
 */

if (!defined('IN_BACOSEARCH')) exit('Acesso negado.');

// --- Configurações de fuso horário globais ---
if (!defined('DB_TIMEZONE')) {
    define('DB_TIMEZONE', 'America/Sao_Paulo');
}
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Europe/Lisbon');
}

$language_code = isset($_SESSION['language']) ? $_SESSION['language'] : (isset(LANGUAGE_CONFIG['default']) ? LANGUAGE_CONFIG['default'] : 'en-us');

// --- Mapa de traduções ---
$ads_management_translations_map = [
    'ads_management_title', 'create_new_ad', 'back_to_list', 'ad_title_label', 'destination_url_label', 'banner_image_label', 'banner_image_mobile_label', 'current_image_info',
    'remove_image_checkbox', 'target_level_label', 'global_target_option', 'national_target_option', 'regional_target_option', 'country_label', 'select_country_option',
    'region_label', 'region_placeholder', 'city_label', 'city_placeholder', 'placement_slot_label', 'select_slot_option', 'start_date_label', 'end_date_label', 'ad_active_checkbox',
    'save_ad_button', 'ad_id_column', 'ad_title_column', 'ad_slot_column', 'ad_target_column', 'ad_active_column', 'ad_period_column', 'ad_unique_users_column', 'ad_pageviews_column',
    'ad_clicks_column', 'edit_button', 'delete_button', 'confirm_delete_ad', 'no_ads_found', 'create_first_ad_link', 'operation_successful', 'ad_deleted_successfully', 'operation_error',
    'ad_not_found', 'could_not_load_ads_list', 'upload_failed_message', 'image_required_message', 'details_message', 'location_global', 'location_country_prefix', 'location_region_prefix',
    'location_city_prefix', 'location_not_applicable', 'ad_target_global_column', 'ad_target_country_column', 'ad_target_region_column', 'ad_target_city_column', 'ad_home_mobile_pageviews_column',
    'ad_home_desktop_pageviews_column', 'yes', 'no'
];

$module_translations = [];
foreach ($ads_management_translations_map as $key) {
    $context = 'ads_management';
    if (in_array($key, ['operation_successful', 'ad_deleted_successfully', 'operation_error', 'ad_not_found', 'could_not_load_ads_list', 'upload_failed_message', 'image_required_message', 'details_message'])) {
        $context = 'ui_messages';
    } elseif (in_array($key, ['yes', 'no'])) {
        $context = 'common_options';
    }
    $module_translations[$key] = getTranslation($key, $languageCode, $context);
}

// --- Funções Auxiliares ---
function deleteImageFile($image_path) {
    if ($image_path && file_exists(dirname(__DIR__, 3) . $image_path)) {
        @unlink(dirname(__DIR__, 3) . $image_path);
    }
}

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (POST - Criar/Atualizar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $destination_url = filter_input(INPUT_POST, 'destination_url', FILTER_SANITIZE_URL);
    $target_level = filter_input(INPUT_POST, 'target_level', FILTER_SANITIZE_STRING);
    $placement_slot = filter_input(INPUT_POST, 'placement_slot', FILTER_SANITIZE_STRING);
    $start_date_input = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    $end_date_input = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $country_code = null;
    $region_name = null;
    $city_name = null;

    if ($target_level === 'national') {
        $country_code = filter_input(INPUT_POST, 'country_code_national', FILTER_SANITIZE_STRING);
    } elseif ($target_level === 'regional') {
        $country_code = filter_input(INPUT_POST, 'country_code_regional', FILTER_SANITIZE_STRING);
        $region_name = filter_input(INPUT_POST, 'region_name', FILTER_SANITIZE_STRING);
        $city_name = filter_input(INPUT_POST, 'city_name', FILTER_SANITIZE_STRING);
    }

    $image_path = isset($_POST['current_image_path']) ? $_POST['current_image_path'] : null;
    $image_path_mobile = isset($_POST['current_image_path_mobile']) ? $_POST['current_image_path_mobile'] : null;

    if ($id && isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        deleteImageFile($image_path);
        $image_path = null;
    }
    if ($id && isset($_POST['remove_image_mobile']) && $_POST['remove_image_mobile'] === '1') {
        deleteImageFile($image_path_mobile);
        $image_path_mobile = null;
    }

    function handleImageUpload($file_key, $current_path, $translations) {
        $upload_dir_full = dirname(__DIR__, 3) . '/uploads/ads/';
        if (!is_dir($upload_dir_full)) {
            mkdir($upload_dir_full, 0777, true);
        }
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
            $file_extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('ad_') . '.' . $file_extension;
            $destination = $upload_dir_full . $new_file_name;
            if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $destination)) {
                return '/uploads/ads/' . $new_file_name;
            } else {
                header("Location: ?module=ads_management&status=error&message=" . urlencode($translations['upload_failed_message']));
                exit();
            }
        }
        return $current_path;
    }

    $image_path = handleImageUpload('image', $image_path, $module_translations);
    $image_path_mobile = handleImageUpload('image_mobile', $image_path_mobile, $module_translations);

    if (empty($image_path) && empty($image_path_mobile)) {
        header("Location: ?module=ads_management&status=error&message=" . urlencode($module_translations['image_required_message']));
        exit();
    }

    try {
        $start_datetime_app_tz = new DateTime($start_date_input . ' 00:00:00', new DateTimeZone(APP_TIMEZONE));
        $end_datetime_app_tz = new DateTime($end_date_input . ' 23:59:59', new DateTimeZone(APP_TIMEZONE));
        $start_date_db_format = $start_datetime_app_tz->setTimezone(new DateTimeZone(DB_TIMEZONE))->format('Y-m-d H:i:s');
        $end_date_db_format = $end_datetime_app_tz->setTimezone(new DateTimeZone(DB_TIMEZONE))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $error_message = isset($module_translations['operation_error']) ? $module_translations['operation_error'] : 'Error';
        header("Location: ?module=ads_management&status=error&message=" . urlencode($error_message . " (Data Inválida)"));
        exit();
    }

    try {
        if ($id) { // UPDATE
            $sql = "UPDATE advertisements SET title = :title, image_path = :image_path, image_path_mobile = :image_path_mobile, destination_url = :destination_url, ad_type = 'banner', target_level = :target_level, placement_slot = :placement_slot, country_code = :country_code, city_name = :city_name, region_name = :region_name, start_date = :start_date, end_date = :end_date, is_active = :is_active, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':title' => $title, ':image_path' => $image_path, ':image_path_mobile' => $image_path_mobile, ':destination_url' => $destination_url, ':target_level' => $target_level, ':placement_slot' => $placement_slot, ':country_code' => $country_code, ':city_name' => $city_name, ':region_name' => $region_name, ':start_date' => $start_date_db_format, ':end_date' => $end_date_db_format, ':is_active' => $is_active, ':id' => $id]);
            $status_msg = 'updated';
        } else { // INSERT
            $sql = "INSERT INTO advertisements (title, image_path, image_path_mobile, destination_url, ad_type, target_level, placement_slot, country_code, city_name, region_name, start_date, end_date, is_active, created_at, updated_at) VALUES (:title, :image_path, :image_path_mobile, :destination_url, 'banner', :target_level, :placement_slot, :country_code, :city_name, :region_name, :start_date, :end_date, :is_active, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':title' => $title, ':image_path' => $image_path, ':image_path_mobile' => $image_path_mobile, ':destination_url' => $destination_url, ':target_level' => $target_level, ':placement_slot' => $placement_slot, ':country_code' => $country_code, ':city_name' => $city_name, ':region_name' => $region_name, ':start_date' => $start_date_db_format, ':end_date' => $end_date_db_format, ':is_active' => $is_active]);
            $status_msg = 'created';
        }
        header("Location: ?module=ads_management&status=success&action={$status_msg}");
        exit();
    } catch (PDOException $e) {
        error_log("Erro ao salvar anúncio: " . $e->getMessage());
        header("Location: ?module=ads_management&status=error&message=" . urlencode($e->getMessage()));
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $ad_id_to_delete = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT image_path, image_path_mobile FROM advertisements WHERE id = :id");
        $stmt->execute([':id' => $ad_id_to_delete]);
        $image_paths = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($image_paths) {
            deleteImageFile($image_paths['image_path']);
            deleteImageFile($image_paths['image_path_mobile']);
        }
        $stmt_delete = $pdo->prepare("DELETE FROM advertisements WHERE id = :id");
        $stmt_delete->execute([':id' => $ad_id_to_delete]);
        header("Location: ?module=ads_management&status=deleted");
        exit();
    } catch (PDOException $e) {
        error_log("Erro ao deletar anúncio: " . $e->getMessage());
        $error_message = isset($module_translations['operation_error']) ? $module_translations['operation_error'] : 'Error deleting ad.';
        header("Location: ?module=ads_management&status=error&message=" . urlencode($error_message));
        exit();
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    $stmt_countries = $pdo->prepare("SELECT iso_code, name FROM countries ORDER BY name ASC");
    $stmt_countries->execute();
    $countries_list = $stmt_countries->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $countries_list = [];
}

$e = function($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

if ($action === 'create' || $action === 'edit'):
    $ad_data = null;
    $form_title = $module_translations['create_new_ad'];

    if ($action === 'edit' && $ad_id) {
        $stmt = $pdo->prepare("SELECT id, title, image_path, image_path_mobile, destination_url, target_level, placement_slot, country_code, city_name, region_name, is_active, CONVERT_TZ(start_date, '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "') as start_date, CONVERT_TZ(end_date, '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "') as end_date FROM advertisements WHERE id = :id");
        $stmt->execute([':id' => $ad_id]);
        $ad_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ad_data) {
            $ad_data['start_date'] = (new DateTime($ad_data['start_date']))->format('Y-m-d');
            $ad_data['end_date'] = (new DateTime($ad_data['end_date']))->format('Y-m-d');
            $form_title = $e(isset($module_translations['edit_button']) ? $module_translations['edit_button'] : 'Edit') . ": " . $e($ad_data['title']);
        } else {
            header("Location: ?module=ads_management&status=notfound");
            exit();
        }
    } else {
        $ad_data['start_date'] = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
        $ad_data['end_date'] = (new DateTime('+1 year', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d');
        $ad_data['is_active'] = 1;
    }
    $placement_slots_options = ['global', 'national', 'regional_1', 'regional_2'];
?>
    <div class="dashboard-module-wrapper">
        <div class="module-header">
            <h1><?= $e($form_title) ?></h1>
            <a href="?module=ads_management" class="btn"><?= $e($module_translations['back_to_list']) ?></a>
        </div>

        <form method="POST" action="?module=ads_management" enctype="multipart/form-data" class="dashboard-form">
            <?php if ($action === 'edit' && $ad_data): ?>
                <input type="hidden" name="id" value="<?= $e($ad_data['id']) ?>">
            <?php endif; ?>

            <div class="form-group"><label for="title"><?= $e($module_translations['ad_title_label']) ?></label><input type="text" id="title" name="title" value="<?= $e(isset($ad_data['title']) ? $ad_data['title'] : '') ?>" required></div>
            <div class="form-group"><label for="destination_url"><?= $e($module_translations['destination_url_label']) ?></label><input type="url" id="destination_url" name="destination_url" value="<?= $e(isset($ad_data['destination_url']) ? $ad_data['destination_url'] : '') ?>" required></div>
            
            <div class="form-group">
                <label for="image"><?= $e($module_translations['banner_image_label']) ?></label>
                <input type="file" id="image" name="image" accept="image/*">
                <?php if ($action === 'edit' && !empty($ad_data['image_path'])): ?>
                    <p class="current-image-info">
                        <?= $e($module_translations['current_image_info']) ?> <a href="<?= $e(SITE_URL . $ad_data['image_path']) ?>" target="_blank">Ver</a>
                        <label class="checkbox-label"><input type="checkbox" name="remove_image" value="1"> <?= $e($module_translations['remove_image_checkbox']) ?></label>
                    </p>
                    <input type="hidden" name="current_image_path" value="<?= $e($ad_data['image_path']) ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="image_mobile"><?= $e($module_translations['banner_image_mobile_label']) ?></label>
                <input type="file" id="image_mobile" name="image_mobile" accept="image/*">
                <?php if ($action === 'edit' && !empty($ad_data['image_path_mobile'])): ?>
                    <p class="current-image-info">
                        <?= $e($module_translations['current_image_info']) ?> <a href="<?= $e(SITE_URL . $ad_data['image_path_mobile']) ?>" target="_blank">Ver</a>
                        <label class="checkbox-label"><input type="checkbox" name="remove_image_mobile" value="1"> <?= $e($module_translations['remove_image_checkbox']) ?></label>
                    </p>
                    <input type="hidden" name="current_image_path_mobile" value="<?= $e($ad_data['image_path_mobile']) ?>">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="target_level"><?= $e($module_translations['target_level_label']) ?></label>
                <select id="target_level" name="target_level" required>
                    <option value="global" <?= ((isset($ad_data['target_level']) ? $ad_data['target_level'] : '') === 'global' ? 'selected' : '') ?>><?= $e($module_translations['global_target_option']) ?></option>
                    <option value="national" <?= ((isset($ad_data['target_level']) ? $ad_data['target_level'] : '') === 'national' ? 'selected' : '') ?>><?= $e($module_translations['national_target_option']) ?></option>
                    <option value="regional" <?= ((isset($ad_data['target_level']) ? $ad_data['target_level'] : '') === 'regional' ? 'selected' : '') ?>><?= $e($module_translations['regional_target_option']) ?></option>
                </select>
            </div>

            <div id="national-country-selection" style="display: <?= ((isset($ad_data['target_level']) ? $ad_data['target_level'] : '') === 'national' ? 'block' : 'none') ?>;">
                <div class="form-group">
                    <label for="country_code_select"><?= $e($module_translations['country_label']) ?></label>
                    <select id="country_code_select" name="country_code_national" class="form-control">
                        <option value=""><?= $e($module_translations['select_country_option']) ?></option>
                        <?php foreach ($countries_list as $country): ?>
                            <option value="<?= $e($country['iso_code']) ?>"
                                <?= ((isset($ad_data['country_code']) ? $ad_data['country_code'] : '') === $country['iso_code'] ? 'selected' : '') ?>>
                                <?= $e($country['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="regional-city-autocomplete" style="display: <?= ((isset($ad_data['target_level']) ? $ad_data['target_level'] : '') === 'regional' ? 'block' : 'none') ?>;">
                <div class="form-group">
                    <label for="address-autocomplete-input"><?= $e($module_translations['city_label']) ?> *</label>
                    <input type="text" id="address-autocomplete-input" name="ad_city_autocomplete" class="form-control" placeholder="<?= $e($module_translations['city_placeholder']) ?>" value="<?= $e(isset($ad_data['city_name']) ? $ad_data['city_name'] : '') ?>" autocomplete="off">
                    <input type="hidden" id="city_name_hidden" name="city_name" value="<?= $e(isset($ad_data['city_name']) ? $ad_data['city_name'] : '') ?>">
                    <input type="hidden" id="region_name_hidden" name="region_name" value="<?= $e(isset($ad_data['region_name']) ? $ad_data['region_name'] : '') ?>">
                    <input type="hidden" id="country_code_hidden_regional" name="country_code_regional" value="<?= $e(isset($ad_data['country_code']) ? $ad_data['country_code'] : '') ?>">
                    <div class="location-display-group">
                        <input type="text" id="city_display" class="form-control-display" value="<?= $e(isset($ad_data['city_name']) ? $ad_data['city_name'] : '') ?>" readonly placeholder="<?= $e($module_translations['city_placeholder']) ?>">
                        <input type="text" id="region_display" class="form-control-display" value="<?= $e(isset($ad_data['region_name']) ? $ad_data['region_name'] : '') ?>" readonly placeholder="<?= $e($module_translations['region_placeholder']) ?>">
                        <input type="text" id="country_display" class="form-control-display" value="<?= $e(isset($ad_data['country_code']) ? $ad_data['country_code'] : '') ?>" readonly placeholder="<?= $e($module_translations['select_country_option']) ?>">
                    </div>
                    <div class="invalid-feedback" id="city-error"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="placement_slot"><?= $e($module_translations['placement_slot_label']) ?></label>
                <select id="placement_slot" name="placement_slot" required>
                    <option value=""><?= $e($module_translations['select_slot_option']) ?></option>
                    <?php foreach ($placement_slots_options as $option): ?>
                        <option value="<?= $e($option) ?>" <?= ((isset($ad_data['placement_slot']) ? $ad_data['placement_slot'] : '') === $option ? 'selected' : '') ?>>
                            <?= $e(ucfirst(str_replace('_', ' ', $option))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label for="start_date"><?= $e($module_translations['start_date_label']) ?></label><input type="date" id="start_date" name="start_date" value="<?= $e(isset($ad_data['start_date']) ? $ad_data['start_date'] : '') ?>" required></div>
                <div class="form-group"><label for="end_date"><?= $e($module_translations['end_date_label']) ?></label><input type="date" id="end_date" name="end_date" value="<?= $e(isset($ad_data['end_date']) ? $ad_data['end_date'] : '') ?>" required></div>
            </div>
            <div class="form-group">
                <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" <?= ((isset($ad_data['is_active']) ? $ad_data['is_active'] : 1) ? 'checked' : '') ?>> <?= $e($module_translations['ad_active_checkbox']) ?></label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $e($module_translations['save_ad_button']) ?></button>
            </div>
        </form>
    </div>
    
    <script>
        function gm_authFailure() {
            var cityError = document.getElementById('city-error');
            if (cityError) {
                cityError.style.display = 'block';
                cityError.textContent = '<?= $e(getTranslation('auth_failure_google_maps', $languageCode, 'ui_messages')); ?>';
            }
        }

        function initAutocomplete() {
            var autocompleteInput = document.getElementById('address-autocomplete-input');
            if (!autocompleteInput || autocompleteInput.offsetParent === null) { return; }
            var cityDisplay = document.getElementById('city_display');
            var regionDisplay = document.getElementById('region_display');
            var countryDisplay = document.getElementById('country_display');
            var cityHidden = document.getElementById('city_name_hidden');
            var regionHidden = document.getElementById('region_name_hidden');
            var countryHiddenRegional = document.getElementById('country_code_hidden_regional');
            var cityError = document.getElementById('city-error');
            try {
                var autocomplete = new google.maps.places.Autocomplete(autocompleteInput, { types: ['(cities)'], fields: ['address_components', 'place_id'] });
                autocomplete.addListener('place_changed', function() {
                    var place = autocomplete.getPlace();
                    if (!place.address_components) {
                        cityError.style.display = 'block';
                        cityError.textContent = '<?= $e(getTranslation('invalid_city_error', $languageCode, 'ui_messages')); ?>';
                        cityHidden.value = ''; regionHidden.value = ''; countryHiddenRegional.value = '';
                        cityDisplay.value = ''; regionDisplay.value = ''; countryDisplay.value = '';
                        return;
                    }
                    cityError.style.display = 'none';
                    var city = '', region = '', country = '';
                    for (var i = 0; i < place.address_components.length; i++) {
                        var component = place.address_components[i];
                        var types = component.types;
                        if (types.includes('locality')) city = component.long_name;
                        if (types.includes('administrative_area_level_1')) region = component.long_name;
                        if (types.includes('country')) country = component.short_name;
                    }
                    autocompleteInput.value = city; cityDisplay.value = city; regionDisplay.value = region;
                    countryDisplay.value = country; cityHidden.value = city; regionHidden.value = region;
                    countryHiddenRegional.value = country;
                });
            } catch (e) {
                console.error("Erro no Google Maps: ", e);
                cityError.style.display = 'block';
                cityError.textContent = '<?= $e(getTranslation('google_maps_init_error', $languageCode, 'ui_messages')); ?>';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var targetLevelSelect = document.getElementById('target_level');
            var nationalDiv = document.getElementById('national-country-selection');
            var regionalDiv = document.getElementById('regional-city-autocomplete');
            var countrySelectNational = document.getElementById('country_code_select');
            var addressAutocompleteInput = document.getElementById('address-autocomplete-input');

            function toggleTargetingFields() {
                var level = targetLevelSelect.value;
                countrySelectNational.required = false;
                addressAutocompleteInput.required = false;
                nationalDiv.style.display = 'none';
                regionalDiv.style.display = 'none';

                if (level === 'national') {
                    nationalDiv.style.display = 'block';
                    countrySelectNational.required = true;
                } else if (level === 'regional') {
                    regionalDiv.style.display = 'block';
                    addressAutocompleteInput.required = true;
                    if (typeof google !== 'undefined' && google.maps) {
                        initAutocomplete();
                    }
                }
            }
            targetLevelSelect.addEventListener('change', toggleTargetingFields);
            toggleTargetingFields();
        });
    </script>

    <?php
    $googleMapsApiKey = isset(API_CONFIG['Maps_API_KEY']) ? API_CONFIG['Maps_API_KEY'] : '';
    if (!empty($googleMapsApiKey)) {
        echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . $e($googleMapsApiKey) . '&libraries=places&callback=initAutocomplete" async defer></script>';
    } else {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var cityError = document.getElementById('city-error');
                if(cityError) {
                    cityError.style.display = 'block';
                    cityError.textContent = '" . $e(getTranslation('google_maps_api_key_error', $languageCode, 'ui_messages')) . "';
                }
            });
        </script>";
    }
    ?>

<?php else: // Ação padrão: 'list' ?>
    <div class="dashboard-module-wrapper">
        <div class="module-header">
            <h1><?= $e($module_translations['ads_management_title']) ?></h1>
            <a href="?module=ads_management&action=create" class="btn btn-primary"><?= $e($module_translations['create_new_ad']) ?></a>
        </div>

        <?php
        if (isset($_GET['status'])) {
            $message = '';
            $alert_class = 'alert-info';
            switch ($_GET['status']) {
                case 'success':
                    $message = $module_translations['operation_successful'];
                    $alert_class = 'alert-success';
                    break;
                case 'deleted':
                    $message = $module_translations['ad_deleted_successfully'];
                    $alert_class = 'alert-success';
                    break;
                case 'error':
                    $message = $module_translations['operation_error'];
                    $alert_class = 'alert-danger';
                    if (isset($_GET['message'])) {
                        $message .= ' ' . $module_translations['details_message'] . ' ' . $e($_GET['message']);
                    }
                    break;
                case 'notfound':
                    $message = $module_translations['ad_not_found'];
                    $alert_class = 'alert-warning';
                    break;
            }
            if ($message) {
                echo '<div class="alert ' . $e($alert_class) . '">' . $e($message) . '</div>';
            }
        }
        
        try {
            $admin_visitor_id_list = '0';
            $stmt_admin_visitor_ids = $pdo->prepare("SELECT DISTINCT v.id FROM visitors v JOIN accounts a ON v.ip_address = a.ip_address WHERE a.role_id = 5 AND a.status = 'active'");
            $stmt_admin_visitor_ids->execute();
            $admin_visitor_ids = $stmt_admin_visitor_ids->fetchAll(PDO::FETCH_COLUMN);
            if (isset($_SESSION['visitor_db_id']) && !in_array($_SESSION['visitor_db_id'], $admin_visitor_ids)) {
                $admin_visitor_ids[] = (int)$_SESSION['visitor_db_id'];
            }
            if (!empty($admin_visitor_ids)) {
                $admin_visitor_id_list = implode(',', array_map('intval', $admin_visitor_ids));
            }

            $stmt = $pdo->prepare("
                SELECT
                    a.id, a.title, a.placement_slot, a.target_level, a.is_active, a.country_code, a.region_name, a.city_name,
                    CONVERT_TZ(a.start_date, '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "') as start_date,
                    CONVERT_TZ(a.end_date, '" . DB_TIMEZONE . "', '" . APP_TIMEZONE . "') as end_date,
                    COUNT(DISTINCT CASE WHEN s.stat_type = 'view' THEN s.visitor_id END) AS unique_viewers,
                    COUNT(CASE WHEN s.stat_type = 'view' THEN s.id END) AS total_views,
                    COUNT(CASE WHEN s.stat_type = 'click' THEN s.id END) AS total_clicks,
                    (SELECT COUNT(DISTINCT pv_m.visitor_id) FROM page_views pv_m WHERE pv_m.page_url = '/' AND pv_m.device_type = 'mobile' AND pv_m.is_bot_view = 0 AND pv_m.visitor_id NOT IN ($admin_visitor_id_list) AND pv_m.visit_timestamp BETWEEN a.start_date AND a.end_date) AS home_mobile_unique_views,
                    (SELECT COUNT(DISTINCT pv_d.visitor_id) FROM page_views pv_d WHERE pv_d.page_url = '/' AND pv_d.device_type = 'desktop' AND pv_d.is_bot_view = 0 AND pv_d.visitor_id NOT IN ($admin_visitor_id_list) AND pv_d.visit_timestamp BETWEEN a.start_date AND a.end_date) AS home_desktop_unique_views
                FROM advertisements AS a
                LEFT JOIN ad_stats AS s ON a.id = s.ad_id
                GROUP BY a.id ORDER BY a.created_at DESC
            ");
            $stmt->execute();
            $ads_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lista de anúncios: " . $e->getMessage());
            $ads_list = [];
            echo '<div class="alert alert-danger">' . $e(isset($module_translations['could_not_load_ads_list']) ? $module_translations['could_not_load_ads_list'] : '') . '</div>';
        }
        ?>

        <div class="ads-list-container">
            <?php if (empty($ads_list)): ?>
                <p><?= $e($module_translations['no_ads_found']) ?> <a href="?module=ads_management&action=create"><?= $e($module_translations['create_first_ad_link']) ?></a></p>
            <?php else: ?>
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th><?= $e($module_translations['ad_id_column']) ?></th>
                            <th><?= $e($module_translations['ad_title_column']) ?></th>
                            <th><?= $e($module_translations['ad_slot_column']) ?></th>
                            <th><?= $e($module_translations['ad_target_column']) ?></th>
                            <th><?= $e($module_translations['ad_target_global_column']) ?></th>
                            <th><?= $e($module_translations['ad_target_country_column']) ?></th>
                            <th><?= $e($module_translations['ad_target_region_column']) ?></th>
                            <th><?= $e($module_translations['ad_target_city_column']) ?></th>
                            <th><?= $e($module_translations['ad_active_column']) ?></th>
                            <th><?= $e($module_translations['ad_period_column']) ?></th>
                            <th><?= $e($module_translations['ad_unique_users_column']) ?></th>
                            <th><?= $e($module_translations['ad_pageviews_column']) ?></th>
                            <th><?= $e($module_translations['ad_clicks_column']) ?></th>
                            <th><?= $e($module_translations['ad_home_mobile_pageviews_column']) ?></th>
                            <th><?= $e($module_translations['ad_home_desktop_pageviews_column']) ?></th>
                            <th><?= $e($module_translations['ad_actions_column']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ads_list as $ad): ?>
                            <tr>
                                <td data-label="<?= $e($module_translations['ad_id_column']) ?>:"><?= $e($ad['id']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_title_column']) ?>:"><?= $e($ad['title']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_slot_column']) ?>:"><?= $e($ad['placement_slot']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_target_column']) ?>:"><?= $e(ucfirst($ad['target_level'])) ?></td>
                                <td data-label="<?= $e($module_translations['ad_target_global_column']) ?>:"><?= ($ad['target_level'] === 'global' ? 'X' : '-') ?></td>
                                <td data-label="<?= $e($module_translations['ad_target_country_column']) ?>:"><?= ($ad['target_level'] === 'national' || $ad['target_level'] === 'regional' ? $e(isset($ad['country_code']) ? $ad['country_code'] : $module_translations['location_not_applicable']) : '-') ?></td>
                                <td data-label="<?= $e($module_translations['ad_target_region_column']) ?>:"><?= ($ad['target_level'] === 'regional' ? $e(isset($ad['region_name']) ? $ad['region_name'] : $module_translations['location_not_applicable']) : '-') ?></td>
                                <td data-label="<?= $e($module_translations['ad_target_city_column']) ?>:"><?= ($ad['target_level'] === 'regional' ? $e(isset($ad['city_name']) ? $ad['city_name'] : $module_translations['location_not_applicable']) : '-') ?></td>
                                <td data-label="<?= $e($module_translations['ad_active_column']) ?>:"><?= ($ad['is_active'] ? $e($module_translations['yes']) : $e($module_translations['no'])) ?></td>
                                <td data-label="<?= $e($module_translations['ad_period_column']) ?>:"><?= $e((new DateTime($ad['start_date']))->format('d/m/Y')) ?> - <?= $e((new DateTime($ad['end_date']))->format('d/m/Y')) ?></td>
                                <td data-label="<?= $e($module_translations['ad_unique_users_column']) ?>:"><?= number_format((int)$ad['unique_viewers']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_pageviews_column']) ?>:"><?= number_format((int)$ad['total_views']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_clicks_column']) ?>:"><?= number_format((int)$ad['total_clicks']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_home_mobile_pageviews_column']) ?>:"><?= number_format((int)$ad['home_mobile_unique_views']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_home_desktop_pageviews_column']) ?>:"><?= number_format((int)$ad['home_desktop_unique_views']) ?></td>
                                <td data-label="<?= $e($module_translations['ad_actions_column']) ?>:">
                                    <a href="?module=ads_management&action=edit&id=<?= $ad['id'] ?>" class="btn btn-sm btn-edit"><?= $e($module_translations['edit_button']) ?></a>
                                    <a href="?module=ads_management&action=delete&id=<?= $ad['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= $e($module_translations['confirm_delete_ad']) ?>');"><?= $e($module_translations['delete_button']) ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>