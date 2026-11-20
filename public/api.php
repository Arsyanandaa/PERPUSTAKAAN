<?php
declare(strict_types=1);

/**
 * Front controller REST API. Semua request /api.php/... lewat sini.
 * Backend inti (auth + loans) = Anggota 1. Route katalog dan member admin = Anggota 2 (lihat TODO).
 */

use App\Controllers\AuthController;
use App\Controllers\LoanController;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Repositories\LoanRepository;
use App\Repositories\MediaRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\LoanService;

$config = require __DIR__ . '/../bootstrap.php';

// Jaring pengaman terakhir: apa pun yang lolos tetap menjadi JSON, bukan error PHP mentah.
set_exception_handler(static function (Throwable $e): void {
    error_log('[PustakaKu] ' . $e::class . ': ' . $e->getMessage());
    Response::error('Terjadi kesalahan pada server', 500)->send();
});

// ---- path ----
$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $pos = strpos($uri, '/api.php');
    $path = $pos !== false ? substr($uri, $pos + strlen('/api.php')) : $uri;
}

// ---- body: JSON, fallback form-encoded ----
$body = $_POST;
$raw = file_get_contents('php://input');
if ($raw !== false && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        Response::error('Body harus berupa JSON yang valid', 400)->send();
        exit;
    }
    $body = $decoded;
}

// ---- wiring dependensi (constructor injection) ----
$data    = $config['dataDir'];
$session = new Session();
$users   = new UserRepository($data . '/users.json');
$media   = new MediaRepository($data . '/media.json');
$loans   = new LoanRepository($data . '/loans.json');

$authService = new AuthService($users, $session);
$loanService = new LoanService($loans, $media, $users);

$auth = new AuthController($authService);
$loan = new LoanController($loanService, $authService);

// ---- routes ----
$router = new Router();

$router->post('/auth/register', $auth->register(...));
$router->post('/auth/login',    $auth->login(...));
$router->post('/auth/logout',   $auth->logout(...));
$router->get('/auth/me',        $auth->me(...));

$router->post('/loans',             $loan->borrow(...));
$router->post('/loans/{id}/return', $loan->return(...));
$router->get('/loans',              $loan->history(...));

$router->post('/members/upgrade', $auth->upgrade(...));

// TODO (Anggota 2, T6): daftarkan route katalog dan member admin di sini, contoh:
// $catalog = new CatalogController(new CatalogService($media), $authService);
// $router->get('/catalog', $catalog->index(...));
// $router->post('/catalog', $catalog->store(...));
// $router->put('/catalog/{id}', $catalog->update(...));
// $router->delete('/catalog/{id}', $catalog->destroy(...));
// $router->get('/members', $members->index(...));
// $router->patch('/members/{id}/status', $members->setStatus(...));

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $body)->send();
