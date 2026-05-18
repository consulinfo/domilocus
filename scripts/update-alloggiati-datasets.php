<?php
/**
 * Manual dataset refresh script.
 *
 * Usage (from WordPress root):
 * php wp-content/plugins/domilocus/scripts/update-alloggiati-datasets.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) {
    fwrite(STDERR, "Cannot find wp-load.php\n");
    exit(1);
}

require_once $wp_load;

if (!class_exists('Domilocus_Alloggiati_Locations')) {
    $class_file = dirname(__DIR__) . '/includes/class-domilocus-alloggiati-locations.php';
    if (!file_exists($class_file)) {
        fwrite(STDERR, "Cannot find locations class file.\n");
        exit(1);
    }
    require_once $class_file;
}

$refresh = Domilocus_Alloggiati_Locations::refresh_datasets();
$import = Domilocus_Alloggiati_Locations::import_datasets_to_db();

echo "Datasets refreshed.\n";
echo 'Countries downloaded: ' . intval($refresh['countries']) . "\n";
echo 'Municipalities downloaded: ' . intval($refresh['municipalities']) . "\n";
echo 'Countries imported: ' . intval($import['countries']) . "\n";
echo 'Municipalities imported: ' . intval($import['municipalities']) . "\n";
