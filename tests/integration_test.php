<?php
declare(strict_types=1);

/**
 * Pengujian Integrasi T9 — Verifikasi 9 Skenario Sesuai Tabel 10.1 Dokumen Spesifikasi
 * Jalankan: php tests/integration_test.php
 */

use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\LoanController;
use App\Controllers\MemberController;
use App\Core\Router;
use App\Core\Session;
use App\Repositories\LoanRepository;
use App\Repositories\MediaRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CatalogService;
use App\Services\LoanService;
use App\Services\MemberService;

require __DIR__ . '/../bootstrap.php';

echo "========================================================\n";
echo "   PustakaKu — Pengujian Integrasi T9 (9 Skenario 10.1) \n";
echo "========================================================\n\n";

// Menggunakan folder data simulasi terisolasi
$tmp = sys_get_temp_dir() . '/pustakaku_t9_' . bin2hex(random_bytes(4));
mkdir($tmp);

// Salin file data media & users asli, loans mulai kosong []
copy(__DIR__ . "/../data/media.json", "$tmp/media.json");
copy(__DIR__ . "/../data/users.json", "$tmp/users.json");
file_put_contents("$tmp/loans.json", '[]');

$usersRepo = new UserRepository("$tmp/users.json");
$mediaRepo = new MediaRepository("$tmp/media.json");
$loansRepo = new LoanRepository("$tmp/loans.json");

$authService   = new AuthService($usersRepo, new Session());
$catalogService= new CatalogService($mediaRepo, $loansRepo);
$loanService   = new LoanService($loansRepo, $mediaRepo, $usersRepo);
$memberService = new MemberService($usersRepo);

$authC    = new AuthController($authService);
$catalogC = new CatalogController($catalogService, $authService);
$loanC    = new LoanController($loanService, $authService);
$memberC  = new MemberController($memberService, $authService);

$router = new Router();
$router->post('/auth/register',       $authC->register(...));
$router->post('/auth/login',          $authC->login(...));
$router->post('/auth/logout',         $authC->logout(...));
$router->get('/auth/me',              $authC->me(...));
$router->post('/loans',               $loanC->borrow(...));
$router->post('/loans/{id}/return',   $loanC->return(...));
$router->get('/loans',                $loanC->history(...));
$router->get('/catalog',              $catalogC->index(...));
$router->get('/catalog/categories',   $catalogC->categories(...));
$router->post('/catalog',             $catalogC->store(...));
$router->put('/catalog/{id}',         $catalogC->update(...));
$router->delete('/catalog/{id}',      $catalogC->destroy(...));
$router->get('/members',              $memberC->index(...));
$router->patch('/members/{id}/status', $memberC->setStatus(...));

$pass = 0; $fail = 0;
function testAssert(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  [LULUS] " . $name . PHP_EOL;
    } else {
        $fail++;
        echo "  [GAGAL] " . $name . ($detail ? " ($detail)" : '') . PHP_EOL;
    }
}

function req(string $method, string $path, array $body = [], array $query = []): array {
    global $router;
    $res = $router->dispatch($method, $path, $query, $body);
    return [$res->getStatus(), $res->getBody()];
}

// Skenario 1: Registrasi dengan email yang sudah terdaftar
echo "== Skenario 1: Registrasi dengan email terdaftar\n";
$authService->logout();
[$s1, $b1] = req('POST', '/auth/register', [
    'name' => 'Budi Kembar',
    'email' => 'budi@pustakaku.test', // Email sudah ada
    'password' => 'budi12345',
    'confirmPassword' => 'budi12345'
]);
testAssert('Ditolak 422 dengan pesan email sudah digunakan', 
    $s1 === 422 && isset($b1['errors']['email']) && str_contains($b1['errors']['email'], 'Email sudah digunakan'));

// Skenario 2: Login dengan password salah
echo "\n== Skenario 2: Login dengan password salah\n";
[$s2, $b2] = req('POST', '/auth/login', [
    'email' => 'budi@pustakaku.test',
    'password' => 'salah12345'
]);
testAssert('Ditolak 401 tanpa membocorkan eksistensi user', 
    $s2 === 401 && ($b2['message'] ?? '') === 'Email atau password salah');

// Skenario 3: Member reguler meminjam item ke-4
echo "\n== Skenario 3: Kuota peminjaman member reguler (maks 3)\n";
// Login sebagai Siti (Reguler)
[$sLogin, $bLogin] = req('POST', '/auth/login', ['email' => 'siti@pustakaku.test', 'password' => 'member12345']);
// Pinjam 3 buku
req('POST', '/loans', ['mediaId' => 'M001']);
req('POST', '/loans', ['mediaId' => 'M002']);
req('POST', '/loans', ['mediaId' => 'M003']);
// Pinjam buku ke-4
[$s3, $b3] = req('POST', '/loans', ['mediaId' => 'M004']);
testAssert('Ditolak 409 saat mencoba meminjam item ke-4 (kuota terlampaui)', 
    $s3 === 409 && stripos($b3['message'] ?? '', 'kuota') !== false, "Status: $s3, Pesan: " . ($b3['message'] ?? ''));

// Skenario 4: Meminjam item dengan stok 0
echo "\n== Skenario 4: Meminjam item dengan stok 0\n";
// M011 stok = 0 (Jurnal Pendidikan)
// Login sebagai Budi (kuota 10)
req('POST', '/auth/login', ['email' => 'budi@pustakaku.test', 'password' => 'member12345']);
[$s4, $b4] = req('POST', '/loans', ['mediaId' => 'M011']);
testAssert('Ditolak 409 karena stok media habis', 
    $s4 === 409 && stripos($b4['message'] ?? '', 'stok') !== false, "Status: $s4, Pesan: " . ($b4['message'] ?? ''));

// Skenario 5: Meminjam item yang sedang dipinjam sendiri
echo "\n== Skenario 5: Pinjaman ganda pada item yang sama\n";
// Budi pinjam M005
req('POST', '/loans', ['mediaId' => 'M005']);
// Coba pinjam M005 lagi sebelum dikembalikan
[$s5, $b5] = req('POST', '/loans', ['mediaId' => 'M005']);
testAssert('Ditolak 409 saat meminjam ulang item yang masih aktif dipinjam', 
    $s5 === 409 && stripos($b5['message'] ?? '', 'meminjam media ini') !== false, "Status: $s5, Pesan: " . ($b5['message'] ?? ''));

// Skenario 6: Mengembalikan setelah lewat jatuh tempo (denda dihitung polimorfis)
echo "\n== Skenario 6: Perhitungan denda keterlambatan (Polymorphism)\n";
// Buat data transaksi buatan yang terlambat 3 hari
// Audiobook M007 (fee dasar 2000, audiobook x1.5 -> 3000/hari)
// Jika dipinjam oleh Siti (Reguler multiplier 1.0): 3 hari x 3000 x 1.0 = Rp 9.000
// Jika dipinjam oleh Budi (Premium multiplier 0.5): 3 hari x 3000 x 0.5 = Rp 4.500
$now = new DateTimeImmutable('2026-11-20');
$pastBorrowed = '2026-11-01';
$pastDue = '2026-11-08'; // telat 12 hari dari sekarang
$lateLoan = new \App\Models\Loan('LTES01', 'U002', 'M007', $pastBorrowed, $pastDue, null, 0.0, 'active');
$loansRepo->save($lateLoan);

$siti = $usersRepo->findById('U002');
$retRes = $loanService->returnLoan($siti, 'LTES01', $now);
$expectedFee = 12 * (2000 * 1.5) * 1.0; // 12 * 3000 = 36000
testAssert('Denda terlambat member reguler dihitung akurat sesuai tipe media', 
    $retRes['fee'] === $expectedFee && $retRes['daysLate'] === 12);

// Skenario 7: Member biasa mengakses endpoint admin
echo "\n== Skenario 7: Otorisasi hak akses (Member mengakses endpoint admin)\n";
req('POST', '/auth/login', ['email' => 'siti@pustakaku.test', 'password' => 'member12345']);
[$s7a] = req('POST', '/catalog', ['title' => 'Coba Admin']);
[$s7b] = req('GET', '/members');
[$s7c] = req('PATCH', '/members/U003/status', ['status' => 'inactive']);
testAssert('Member ditolak 403 saat mengakses seluruh endpoint khusus admin', 
    $s7a === 403 && $s7b === 403 && $s7c === 403);

// Skenario 8: File JSON kosong atau rusak ditangani dengan aman
echo "\n== Skenario 8: Penanganan file JSON kosong / tidak ada\n";
$emptyJsonPath = "$tmp/empty_loans.json";
file_put_contents($emptyJsonPath, ''); // file kosong
$emptyRepo = new LoanRepository($emptyJsonPath);
$foundLoans = $emptyRepo->findAll();
testAssert('File JSON kosong menghasilkan array kosong tanpa crash fatal', 
    is_array($foundLoans) && count($foundLoans) === 0);

// Skenario 9: Pemeriksaan aturan CSS responsif (Layar 360px)
echo "\n== Skenario 9: Responsif CSS (Breakpoint mobile & tidak ada overflow)\n";
$cssContent = file_get_contents(__DIR__ . '/../public/css/style.css');
$hasMediaQueries = str_contains($cssContent, '@media (max-width: 768px)') && str_contains($cssContent, '@media (max-width: 480px)');
$hasCardGridMobile = str_contains($cssContent, 'grid-template-columns: 1fr');
$hasTableScroll = str_contains($cssContent, 'overflow-x: auto');
testAssert('CSS memiliki breakpoint responsif mobile, kartu 1 kolom & scroll tabel', 
    $hasMediaQueries && $hasCardGridMobile && $hasTableScroll);

echo "\n========================================================\n";
echo "Total Hasil Pengujian Integrasi: $pass LULUS, $fail GAGAL\n";
echo "========================================================\n";

// Bersihkan folder sementara
$files = glob("$tmp/*");
foreach ($files as $file) {
    if (is_file($file)) unlink($file);
}
rmdir($tmp);

exit($fail === 0 ? 0 : 1);
