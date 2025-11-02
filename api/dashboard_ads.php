<?php
/**
 * api/dashboard_ads.php
 * Minimal stub to prevent fatal errors while ads system is unavailable.
 */

if (!defined('IN_BACOSEARCH')) {
    define('IN_BACOSEARCH', true);
}

/**
 * Returns structure for homepage ads without querying DB.
 * @return array{global: array, national: array, regional_1: array, regional_2: array}
 */
function getAllHomePageAds($pdo, $countryCode = null, $region = null, $city = null, $activeOnly = true): array {
    return [
        'global'     => [],
        'national'   => [],
        'regional_1' => [],
        'regional_2' => [],
    ];
}

?>
