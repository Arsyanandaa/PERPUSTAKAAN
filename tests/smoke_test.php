<?php
declare(strict_types=1);

/**
 * Smoke test backend Anggota 1, tanpa file JSON (repository in-memory).
 * Jalankan: php tests/smoke_test.php
 */

use App\Contracts\LoanRepositoryInterface;
use App\Contracts\RepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Controllers\AuthController;
use App\Controllers\LoanController;
use App\Core\Router;
use App\Core\Session;
use App\Models\Admin;
use App\Models\Loan;
use App\Models\MediaItem;
use App\Models\Member;
use App\Models\PremiumMember;
use App\Models\User;
use App\Services\AuthService;
use App\Services\LoanService;

require __DIR__ . '/../bootstrap.php';

class MemRepo implements RepositoryInterface
{
    /** @var array<string, object> */
    protected array $items = [];
    public function findAll(): array { return array_values($this->items); }
    public function findById(string $id): ?object { return $this->items[$id] ?? null; }
    public function save(object $e): void { $this->items[$e->getId()] = $e; }
    public function delete(string $id): bool { $had = isset($this->items[$id]); unset($this->items[$id]); return $had; }
}
class MemUsers extends MemRepo implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        foreach ($this->items as $u) { if (strcasecmp($u->getEmail(), $email) === 0) { return $u; } }
        return null;
    }
}
class MemLoans extends MemRepo implements LoanRepositoryInterface
{
    public function findByUserId(string $uid): array
    {
        return array_values(array_filter($this->items, fn (Loan $l) => $l->getUserId() === $uid));
    }
}

$pass = 0; $fail = 0;
function check(string $name, bool $ok): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  [OK]   " : "  [GAGAL] ") . $name . PHP_EOL;
}
function expectThrow(callable $fn, string $class, ?int $status = null, ?string $contains = null): bool
{
    try { $fn(); } catch (Throwable $e) {
        if (!$e instanceof $class) { return false; }
        if ($status !== null && $e->getCode() !== $status) { return false; }
        return $contains === null || str_contains($e->getMessage() . json_encode(method_exists($e, 'getErrors') ? $e->getErrors() : []), $contains);
    }
    return false;
}

// ---- setup ----
$users = new MemUsers(); $media = new MemRepo(); $loans = new MemLoans();
$session = new Session();
$auth = new AuthService($users, $session);
$loanSvc = new LoanService($loans, $media, $users);

$media->save(MediaItem::fromArray(['id'=>'M1','type'=>'ebook','title'=>'PHP Modern','author'=>'A','category'=>'Teknologi','year'=>2024,'stock'=>5,'fee'=>1000,'extra'=>['pages'=>320,'format'=>'PDF']]));
$media->save(MediaItem::fromArray(['id'=>'M2','type'=>'audiobook','title'=>'Sejarah','author'=>'B','category'=>'Sejarah','year'=>2023,'stock'=>3,'fee'=>2000,'extra'=>['durationMinutes'=>540,'narrator'=>'S']]));
$media->save(MediaItem::fromArray(['id'=>'M3','type'=>'journal','title'=>'Jurnal AI','author'=>'C','category'=>'Sains','year'=>2022,'stock'=>2,'fee'=>1000,'extra'=>['publisher'=>'UB','volume'=>4]]));
$media->save(MediaItem::fromArray(['id'=>'M4','type'=>'ebook','title'=>'Kosong','author'=>'D','category'=>'X','year'=>2020,'stock'=>0,'fee'=>500]));
$media->save(MediaItem::fromArray(['id'=>'M5','type'=>'ebook','title'=>'Buku 5','author'=>'E','category'=>'X','year'=>2020,'stock'=>9,'fee'=>500]));
$users->save(User::fromArray(['id'=>'A1','role'=>'admin','tier'=>'none','name'=>'Admin','email'=>'admin@x.id','passwordHash'=>password_hash('admin12345',PASSWORD_DEFAULT)]));

echo "== Model & polymorphism\n";
$audio = $media->findById('M2'); $ebook = $media->findById('M1'); $jr = $media->findById('M3');
check('calculateFee beda per tipe (3 hari: ebook 3000, audio 9000, jurnal 6000)',
    $ebook->calculateFee(3) === 3000.0 && $audio->calculateFee(3) === 9000.0 && $jr->calculateFee(3) === 6000.0);
check('fee negatif/0 hari = 0', $audio->calculateFee(-2) === 0.0);
check('describe() beda per tipe', str_starts_with($ebook->describe(), 'E-Book') && str_starts_with($audio->describe(), 'Audiobook') && str_starts_with($jr->describe(), 'Jurnal'));
$m = new Member('U0','n','e@e.id','h'); $p = $m->upgrade();
check('Member 3 pinjam/7 hari; Premium 10/14, id sama', $m->getBorrowLimit()===3 && $m->getLoanDays()===7 && $p instanceof PremiumMember && $p->getBorrowLimit()===10 && $p->getLoanDays()===14 && $p->getId()==='U0');
check('toPublicArray tidak memuat passwordHash', !array_key_exists('passwordHash', $users->findById('A1')->toPublicArray()) && array_key_exists('passwordHash', $users->findById('A1')->toArray()));
check('round-trip toArray/fromArray media', MediaItem::fromArray(json_decode(json_encode($ebook->toArray()), true))->toArray() == $ebook->toArray());
check('Admin tidak bisa pinjam', !$users->findById('A1')->canBorrow());

echo "== Skenario 1-2: registrasi & login\n";
$reg = ['name'=>'Budi Santoso','email'=>'Budi@Mail.com','password'=>'rahasia123','confirmPassword'=>'rahasia123'];
$budi = $auth->register($reg);
check('registrasi sukses, email dinormalkan', $budi->getEmail() === 'budi@mail.com' && $users->findByEmail('budi@mail.com') !== null);
check('1. email dobel ditolak "Email sudah digunakan"', expectThrow(fn() => $auth->register($reg), \App\Exceptions\ValidationException::class, 422, 'Email sudah digunakan'));
check('validasi: password lemah & konfirmasi beda', expectThrow(fn() => $auth->register(['name'=>'Ab','email'=>'x','password'=>'abc','confirmPassword'=>'z']), \App\Exceptions\ValidationException::class, 422, 'confirmPassword'));
$e1 = $e2 = '';
try { $auth->login('budi@mail.com', 'salah'); } catch (Throwable $e) { $e1 = $e->getMessage() . $e->getCode(); }
try { $auth->login('tidakada@mail.com', 'salah'); } catch (Throwable $e) { $e2 = $e->getMessage() . $e->getCode(); }
check('2. password salah = email tak ada (pesan sama, 401)', $e1 !== '' && $e1 === $e2 && str_ends_with($e1, '401'));
$auth->login('budi@mail.com', 'rahasia123');
check('login sukses, session terisi', $auth->currentUser()?->getId() === $budi->getId());

echo "== Skenario 3-5: pinjam\n";
$day0 = new DateTimeImmutable('2026-11-01');
$l1 = $loanSvc->borrow($budi, 'M1', $day0);
check('pinjam sukses, jatuh tempo +7 hari, stok -1', $l1['dueAt'] === '2026-11-08' && $media->findById('M1')->getStock() === 4);
check('5. pinjam item sama lagi ditolak', expectThrow(fn() => $loanSvc->borrow($budi, 'M1', $day0), \App\Exceptions\BusinessRuleException::class, 409, 'sedang meminjam'));
check('4. stok 0 ditolak', expectThrow(fn() => $loanSvc->borrow($budi, 'M4', $day0), \App\Exceptions\BusinessRuleException::class, 409, 'Stok'));
$loanSvc->borrow($budi, 'M2', $day0); $loanSvc->borrow($budi, 'M3', $day0);
check('3. item ke-4 member reguler ditolak (kuota 3)', expectThrow(fn() => $loanSvc->borrow($budi, 'M5', $day0), \App\Exceptions\BusinessRuleException::class, 409, 'Kuota'));
check('media tak ada = 404', expectThrow(fn() => $loanSvc->borrow($budi, 'ZZZ', $day0), \App\Exceptions\BusinessRuleException::class, 404));
check('admin tidak boleh pinjam (403)', expectThrow(fn() => $loanSvc->borrow($users->findById('A1'), 'M5', $day0), \App\Exceptions\BusinessRuleException::class, 403));
$budi->deactivate(); $users->save($budi);
check('member nonaktif tidak boleh pinjam (403)', expectThrow(fn() => $loanSvc->borrow($budi, 'M5', $day0), \App\Exceptions\BusinessRuleException::class, 403));
$budi->activate(); $users->save($budi);

echo "== Skenario 6: kembali & denda\n";
$loanAudio = array_values(array_filter($loans->findByUserId($budi->getId()), fn($l) => $l->getMediaId()==='M2'))[0];
$r = $loanSvc->returnLoan($budi, $loanAudio->getId(), new DateTimeImmutable('2026-11-08'));
check('tepat waktu: denda 0, stok pulih', $r['fee'] === 0.0 && $r['daysLate'] === 0 && $media->findById('M2')->getStock() === 3);
$loanJr = array_values(array_filter($loans->findByUserId($budi->getId()), fn($l) => $l->getMediaId()==='M3'))[0];
$r = $loanSvc->returnLoan($budi, $loanJr->getId(), new DateTimeImmutable('2026-11-11')); // telat 3 hari, jurnal x2
check('member reguler, jurnal telat 3 hari = Rp6000', $r['fee'] === 6000.0 && $r['daysLate'] === 3);
check('kembali dua kali ditolak', expectThrow(fn() => $loanSvc->returnLoan($budi, $loanJr->getId()), \App\Exceptions\BusinessRuleException::class, 409));
$prem = $budi->upgrade(); $users->save($prem);
$p1 = $loanSvc->borrow($prem, 'M2', $day0);
check('premium: jatuh tempo +14 hari', $p1['dueAt'] === '2026-11-15');
$r = $loanSvc->returnLoan($prem, $p1['id'], new DateTimeImmutable('2026-11-18')); // telat 3 hari audiobook 9000 x 0.5
check('premium, audiobook telat 3 hari = Rp4500 (diskon 50%)', $r['fee'] === 4500.0);
$other = $auth->register(['name'=>'Siti Aminah','email'=>'siti@mail.com','password'=>'rahasia123','confirmPassword'=>'rahasia123']);
check('kembalikan pinjaman orang lain = 403', expectThrow(fn() => $loanSvc->returnLoan($other, $loanAudio->getId()), \App\Exceptions\BusinessRuleException::class, 403));

echo "== Riwayat (F-08)\n";
$hMember = $loanSvc->history($prem); $hAdmin = $loanSvc->history($users->findById('A1'));
check('member hanya lihat miliknya (Siti 0), admin lihat semua + userName', count($hMember) === 4 && count($loanSvc->history($other)) === 0 && count($hAdmin) === 4 && isset($hAdmin[0]['userName']) && !isset($hMember[0]['userName']));

echo "== Skenario 7 + Router + keandalan\n";
$router = new Router();
$authC = new AuthController($auth); $loanC = new LoanController($loanSvc, $auth);
$router->get('/admin-only', function () use ($auth) { $auth->requireAdmin(); return \App\Core\Response::success('ok'); });
$router->get('/boom', function () { throw new RuntimeException('rahasia internal'); });
$router->post('/loans/{id}/return', $loanC->return(...));
$router->get('/loans', $loanC->history(...));
$router->post('/auth/login', $authC->login(...));
$router->get('/auth/me', $authC->me(...));
$res = $router->dispatch('GET', '/admin-only');
check('7. member biasa ke endpoint admin = 403', $res->getStatus() === 403);
$auth->logout();
check('tamu ke endpoint admin = 401, ke /loans = 401', $router->dispatch('GET', '/admin-only')->getStatus() === 401 && $router->dispatch('GET', '/loans')->getStatus() === 401);
check('/auth/me tamu = 200 data null', $router->dispatch('GET', '/auth/me')->getBody()['data'] === null);
$res = $router->dispatch('POST', '/auth/login', [], ['email'=>'admin@x.id','password'=>'admin12345']);
check('login admin via router 200, tanpa passwordHash', $res->getStatus() === 200 && !str_contains($res->toJson(), 'passwordHash') && !str_contains($res->toJson(), '$2y$'));
check('admin ke endpoint admin = 200', $router->dispatch('GET', '/admin-only')->getStatus() === 200);
$res = $router->dispatch('GET', '/boom');
check('exception tak terduga = 500 JSON tanpa bocor detail', $res->getStatus() === 500 && !str_contains($res->toJson(), 'rahasia') && json_decode($res->toJson(), true)['success'] === false);
check('404 & 405 JSON', $router->dispatch('GET', '/nope')->getStatus() === 404 && $router->dispatch('DELETE', '/loans')->getStatus() === 405);
$res = $router->dispatch('POST', '/auth/login', [], []);
check('login kosong = 422 dengan errors per field', $res->getStatus() === 422 && isset($res->getBody()['errors']['email'], $res->getBody()['errors']['password']));

echo PHP_EOL . "Hasil: $pass lulus, $fail gagal" . PHP_EOL;
exit($fail === 0 ? 0 : 1);
