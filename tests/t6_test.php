<?php
declare(strict_types=1);

/**
 * Tes T6 (Anggota 2): katalog dan member admin, lewat Router + repository JSON ASLI.
 * Memakai SALINAN data/ di folder sementara, data asli tidak berubah.
 * Jalankan: php tests/t6_test.php
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

// ---- data sementara ----
$tmp = sys_get_temp_dir() . '/pustakaku_t6_' . bin2hex(random_bytes(4));
mkdir($tmp);
foreach (['media', 'users', 'loans'] as $n) {
    copy(__DIR__ . "/../data/$n.json", "$tmp/$n.json");
}

// ---- wiring: sama dengan public/api.php ----
$users   = new UserRepository("$tmp/users.json");
$media   = new MediaRepository("$tmp/media.json");
$loans   = new LoanRepository("$tmp/loans.json");
$auth    = new AuthService($users, new Session());
$authC   = new AuthController($auth);
$loanC   = new LoanController(new LoanService($loans, $media, $users), $auth);
$catalog = new CatalogController(new CatalogService($media, $loans), $auth);
$members = new MemberController(new MemberService($users), $auth);

$router = new Router();
$router->post('/auth/login', $authC->login(...));
$router->post('/loans', $loanC->borrow(...));
$router->post('/loans/{id}/return', $loanC->return(...));
$router->get('/loans', $loanC->history(...));
$router->get('/catalog', $catalog->index(...));
$router->get('/catalog/categories', $catalog->categories(...));
$router->post('/catalog', $catalog->store(...));
$router->put('/catalog/{id}', $catalog->update(...));
$router->delete('/catalog/{id}', $catalog->destroy(...));
$router->get('/members', $members->index(...));
$router->patch('/members/{id}/status', $members->setStatus(...));

// ---- helper ----
$pass = 0; $fail = 0;
function check(string $name, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  [OK]   ' : '  [GAGAL] ') . $name . PHP_EOL;
}
/** @return array{0:int, 1:array} [status, body] */
function call(string $m, string $p, array $body = [], array $query = []): array
{
    global $router;
    $res = $router->dispatch($m, $p, $query, $body);
    return [$res->getStatus(), $res->getBody()];
}
function loginAs(string $who): void
{
    global $auth;
    $auth->logout();
    if ($who === 'guest') {
        return;
    }
    [$s] = call('POST', '/auth/login', ['email' => "$who@pustakaku.test", 'password' => $who === 'admin' ? 'admin12345' : 'member12345']);
    if ($s !== 200) {
        throw new RuntimeException("login $who gagal ($s)");
    }
}
function errKeys(array $body): array
{
    $k = array_keys($body['errors'] ?? []);
    sort($k);
    return $k;
}

$valid = [
    'type' => 'ebook', 'title' => 'Belajar Git dari Nol', 'author' => 'Tim Uji', 'category' => 'Teknologi',
    'year' => 2025, 'stock' => 3, 'fee' => 1000, 'extra' => ['pages' => 120, 'format' => 'pdf'],
];

echo "== GET /catalog (publik)\n";
loginAs('guest');
[$s, $b] = call('GET', '/catalog');
check('tamu boleh lihat katalog: 200 dan 12 item', $s === 200 && count($b['data']) === 12);
check('q "jurnal" = 3 item', count(call('GET', '/catalog', [], ['q' => 'jurnal'])[1]['data']) === 3);
check('category "teknologi" tak peka huruf = 4 item', count(call('GET', '/catalog', [], ['category' => 'teknologi'])[1]['data']) === 4);
check('type audiobook = 3 item', count(call('GET', '/catalog', [], ['type' => 'audiobook'])[1]['data']) === 3);
[$s, $b] = call('GET', '/catalog', [], ['type' => 'komik']);
check('type tak dikenal = 422 errors.type', $s === 422 && errKeys($b) === ['type']);
check('query berupa array (?q[]=x) tidak membuat error', call('GET', '/catalog', [], ['q' => ['x']])[0] === 200);
[$s, $b] = call('GET', '/catalog/categories');
check('GET /catalog/categories = 6 kategori terurut', $s === 200 && $b['data'] === ['Bisnis', 'Pendidikan', 'Sains', 'Sastra', 'Sejarah', 'Teknologi']);

echo "== POST /catalog\n";
check('tamu = 401', call('POST', '/catalog', $valid)[0] === 401);
loginAs('siti');
check('member = 403', call('POST', '/catalog', $valid)[0] === 403);
loginAs('admin');
[$s, $b] = call('POST', '/catalog', $valid);
$newId = $b['data']['id'] ?? '';
check('admin valid = 201 dengan id Mxxxxxx', $s === 201 && preg_match('/^M[0-9A-F]{6}$/', $newId) === 1);
check('format "pdf" dinormalkan jadi "PDF", tipe ebook', ($b['data']['extra']->format ?? '') === 'PDF' && $b['data']['type'] === 'ebook');
check('masuk katalog (13 item) dan tersimpan di file', count(call('GET', '/catalog')[1]['data']) === 13 && $media->findById($newId) !== null);

[$s, $b] = call('POST', '/catalog', []);
check('body kosong = 422, field umum + type', $s === 422 && errKeys($b) === ['author', 'category', 'stock', 'title', 'type', 'year']);
[$s, $b] = call('POST', '/catalog', ['extra' => []] + array_diff_key($valid, ['extra' => 1]));
check('ebook tanpa extra = 422 extra.pages dan extra.format', $s === 422 && errKeys($b) === ['extra.format', 'extra.pages']);
[$s, $b] = call('POST', '/catalog', ['type' => 'journal', 'extra' => ['publisher' => 'X']] + $valid);
check('jurnal tanpa volume = 422 extra.volume', $s === 422 && errKeys($b) === ['extra.volume']);
[$s, $b] = call('POST', '/catalog', ['type' => 'audiobook', 'extra' => ['durationMinutes' => 0, 'narrator' => '']] + $valid);
check('audiobook durasi 0 dan narator kosong = 422', $s === 422 && errKeys($b) === ['extra.durationMinutes', 'extra.narrator']);
check('tipe tak dikenal = 422 type', errKeys(call('POST', '/catalog', ['type' => 'komik'] + $valid)[1]) === ['type']);
check('tahun 1800 ditolak, tahun string "2024" diterima',
    errKeys(call('POST', '/catalog', ['year' => 1800] + $valid)[1]) === ['year'] && call('POST', '/catalog', ['year' => '2024'] + $valid)[0] === 201);
check('stok true / -1 / "abc" ditolak', errKeys(call('POST', '/catalog', ['stock' => true] + $valid)[1]) === ['stock']
    && errKeys(call('POST', '/catalog', ['stock' => -1] + $valid)[1]) === ['stock'] && errKeys(call('POST', '/catalog', ['stock' => 'abc'] + $valid)[1]) === ['stock']);
check('denda negatif ditolak, denda kosong = 0', errKeys(call('POST', '/catalog', ['fee' => -5] + $valid)[1]) === ['fee']
    && (float) call('POST', '/catalog', ['fee' => ''] + $valid)[1]['data']['fee'] === 0.0);
check('judul 150 karakter Unicode diterima, 151 ditolak',
    call('POST', '/catalog', ['title' => str_repeat('é', 150)] + $valid)[0] === 201
    && errKeys(call('POST', '/catalog', ['title' => str_repeat('é', 151)] + $valid)[1]) === ['title']);
check('judul hanya spasi ditolak, teks di-trim', errKeys(call('POST', '/catalog', ['title' => '   '] + $valid)[1]) === ['title']
    && call('POST', '/catalog', ['title' => '  Rapi  '] + $valid)[1]['data']['title'] === 'Rapi');

echo "== PUT /catalog/{id}\n";
$edit = ['title' => 'Belajar Git Lanjut', 'author' => 'Tim Uji', 'category' => 'Teknologi', 'year' => 2026, 'stock' => 9, 'fee' => 1500,
         'extra' => ['pages' => 200, 'format' => 'EPUB']];
loginAs('guest');
check('tamu = 401', call('PUT', "/catalog/$newId", $edit)[0] === 401);
loginAs('budi');
check('member = 403', call('PUT', "/catalog/$newId", $edit)[0] === 403);
loginAs('admin');
[$s, $b] = call('PUT', "/catalog/$newId", $edit);
$saved = $media->findById($newId);
check('admin valid = 200, perubahan tersimpan di file', $s === 200 && $saved->getTitle() === 'Belajar Git Lanjut'
    && $saved->getStock() === 9 && $saved->getFee() === 1500.0 && $saved->getExtra() === ['pages' => 200, 'format' => 'EPUB']);
check('id tetap, tipe tetap ebook', $saved->getId() === $newId && $saved->getType() === 'ebook');
check('PUT dengan type sama diterima', call('PUT', "/catalog/$newId", ['type' => 'EBOOK'] + $edit)[0] === 200);
[$s, $b] = call('PUT', "/catalog/$newId", ['type' => 'journal'] + $edit);
check('ubah tipe = 422 type', $s === 422 && errKeys($b) === ['type']);
check('id tak ada = 404', call('PUT', '/catalog/MXXXXXX', $edit)[0] === 404);
[$s, $b] = call('PUT', "/catalog/$newId", ['title' => 'X']);
check('PUT sebagian = 422 (field lain wajib dikirim ulang)', $s === 422 && errKeys($b) === ['author', 'category', 'extra.format', 'extra.pages', 'stock', 'year']);
check('data tidak berubah setelah PUT gagal', $media->findById($newId)->getTitle() === 'Belajar Git Lanjut');

echo "== DELETE /catalog/{id}\n";
loginAs('guest');
check('tamu = 401', call('DELETE', "/catalog/$newId")[0] === 401);
loginAs('siti');
check('member = 403, data masih ada', call('DELETE', "/catalog/$newId")[0] === 403 && $media->findById($newId) !== null);
loginAs('admin');
check('id tak ada = 404', call('DELETE', '/catalog/MXXXXXX')[0] === 404);
[$s, $b] = call('DELETE', "/catalog/$newId");
check('admin = 200, hilang dari file', $s === 200 && $media->findById($newId) === null);
check('hapus lagi = 404', call('DELETE', "/catalog/$newId")[0] === 404);

loginAs('siti');
[$s, $b] = call('POST', '/loans', ['mediaId' => 'M002']);
$loanId = $b['data']['id'] ?? '';
loginAs('admin');
[$s, $b] = call('DELETE', '/catalog/M002');
check('media yang sedang dipinjam = 409, tidak terhapus', $s === 409 && $media->findById('M002') !== null);
loginAs('siti');
call('POST', "/loans/$loanId/return");
loginAs('admin');
check('setelah dikembalikan bisa dihapus (200)', call('DELETE', '/catalog/M002')[0] === 200);
$titles = array_column(call('GET', '/loans')[1]['data'], 'mediaTitle');
check('riwayat selesai tetap aman: "(media dihapus)"', $titles === ['(media dihapus)']);

echo "== GET /members\n";
loginAs('guest');
check('tamu = 401', call('GET', '/members')[0] === 401);
loginAs('siti');
check('member = 403', call('GET', '/members')[0] === 403);
loginAs('admin');
[$s, $b] = call('GET', '/members');
check('admin = 200, 3 member (admin tidak ikut)', $s === 200 && count($b['data']) === 3 && !in_array('U001', array_column($b['data'], 'id'), true));
check('tanpa passwordHash, ada borrowLimit dan loanDays', !str_contains(json_encode($b), 'passwordHash') && isset($b['data'][0]['borrowLimit'], $b['data'][0]['loanDays']));

echo "== PATCH /members/{id}/status\n";
loginAs('guest');
check('tamu = 401', call('PATCH', '/members/U004/status', ['status' => 'active'])[0] === 401);
loginAs('siti');
check('member = 403', call('PATCH', '/members/U004/status', ['status' => 'active'])[0] === 403);
loginAs('admin');
[$s, $b] = call('PATCH', '/members/U004/status', ['status' => 'active']);
check('aktifkan U004 = 200, tersimpan', $s === 200 && $b['data']['status'] === 'active' && $users->findById('U004')->isActive());
check('idempotent: kirim status sama lagi = 200', call('PATCH', '/members/U004/status', ['status' => 'active'])[0] === 200);
[$s, $b] = call('PATCH', '/members/U003/status', ['status' => 'inactive']);
check('nonaktifkan U003 = 200', $s === 200 && $b['data']['status'] === 'inactive' && !$users->findById('U003')->isActive());
check('hash password dan tier tetap utuh', $users->findById('U003')->verifyPassword('member12345') && $users->findById('U003')->getTier() === 'premium');
$auth->logout();
$authRes = $router->dispatch('POST', '/auth/login', [], ['email' => 'budi@pustakaku.test', 'password' => 'member12345']);
check('member nonaktif tidak bisa login (403)', $authRes->getStatus() === 403);
loginAs('admin');
check('status tak valid = 422', errKeys(call('PATCH', '/members/U004/status', ['status' => 'banned'])[1]) === ['status']);
check('tanpa body = 422', call('PATCH', '/members/U004/status', [])[0] === 422);
check('id tak ada = 404', call('PATCH', '/members/UXXXX/status', ['status' => 'active'])[0] === 404);
check('akun admin tidak bisa diubah lewat jalur ini (404)', call('PATCH', '/members/U001/status', ['status' => 'inactive'])[0] === 404 && $users->findById('U001')->isActive());

echo "== Data asli tidak tersentuh\n";
check('data/media.json masih 12 item, loans.json masih []',
    count(json_decode(file_get_contents(__DIR__ . '/../data/media.json'), true)) === 12
    && trim(file_get_contents(__DIR__ . '/../data/loans.json')) === '[]');

echo PHP_EOL . "Hasil: $pass lulus, $fail gagal" . PHP_EOL;
exec('rm -rf ' . escapeshellarg($tmp));
exit($fail === 0 ? 0 : 1);
