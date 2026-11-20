<?php
declare(strict_types=1);

// Dimuat oleh public/api.php (dan tests). Mengembalikan array konfigurasi.

use App\Core\Autoloader;

define('BASE_PATH', __DIR__);

error_reporting(E_ALL);
ini_set('display_errors', '0');          // error PHP mentah tidak boleh bocor ke klien
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/src/Core/Autoloader.php';
Autoloader::register(__DIR__ . '/src');

return [
    'dataDir' => __DIR__ . '/data',
];
