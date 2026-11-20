# Kontrak Backend (Anggota 1 -> Anggota 2)

Dokumen ini menyepakati nama class, method, dan format data supaya T5 sampai T8 bisa jalan paralel.
Semua yang tertulis di sini SUDAH ada dan lolos tes di `tests/smoke_test.php`.

## 1. Repository (T5): yang harus Anggota 2 buat

Folder `src/Repositories/`, namespace `App\Repositories`. Constructor semua repository menerima **path file JSON** (string), persis seperti dipakai `public/api.php`:

```php
new UserRepository($dataDir . '/users.json');
new MediaRepository($dataDir . '/media.json');
new LoanRepository($dataDir . '/loans.json');
```

| Class | Implements | Method wajib |
| --- | --- | --- |
| `JsonRepository` (abstract) | `RepositoryInterface` | `findAll()`, `findById(string)`, `save(object)`, `delete(string): bool` + `abstract protected hydrate(array): object` |
| `UserRepository` | `UserRepositoryInterface` | + `findByEmail(string): ?User` (tidak peka huruf besar/kecil) |
| `MediaRepository` | `RepositoryInterface` | + method pencarian milikmu, mis. `search(?string $q, ?string $category, ?string $type)` |
| `LoanRepository` | `LoanRepositoryInterface` | + `findByUserId(string): array` |

Hydration (array JSON -> object) dan sebaliknya sudah disediakan model, tinggal dipanggil:

| Repository | Baca (hydrate) | Tulis |
| --- | --- | --- |
| Media | `MediaItem::fromArray($row)` (memilih EBook/AudioBook/Journal dari `type`) | `$item->toArray()` |
| User | `User::fromArray($row)` (memilih Admin/Member/PremiumMember dari `role` + `tier`) | `$user->toArray()` (**memuat passwordHash**, hanya untuk disimpan) |
| Loan | `Loan::fromArray($row)` | `$loan->toArray()` |

Aturan: `save()` = update jika id sudah ada, insert jika belum. Tulis dengan `LOCK_EX`. File hilang/kosong/rusak -> perlakukan sebagai `[]`, jangan fatal.

## 2. Bentuk data JSON (seed T5)

- `media.json`: `id, type (ebook|audiobook|journal), title, author, category, year, stock, fee, extra{}`
  - extra ebook: `pages, format` | audiobook: `durationMinutes, narrator` | journal: `publisher, volume`
- `users.json`: `id, role (admin|member), tier (none|regular|premium), name, email, passwordHash, status (active|inactive), joinedAt (Y-m-d)`
  - Admin: `role=admin, tier=none`. Buat hash demo: `php -r 'echo password_hash("admin12345", PASSWORD_DEFAULT);'`
- `loans.json`: `id, userId, mediaId, borrowedAt, dueAt, returnedAt (null/Y-m-d), fee, status (active|completed)`. Boleh mulai dari `[]`.

## 3. Catalog dan member admin (T6)

Semua handler controller berbentuk (contoh: `AuthController`, `LoanController`):

```php
public function store(array $params, array $body, array $query): Response
{
    $this->auth->requireAdmin();          // 401 jika tamu, 403 jika bukan admin
    ...
    return Response::success($item->toArray(), 201, 'Media ditambahkan');
}
```

- `$params` = placeholder URL (`{id}`), `$body` = JSON request, `$query` = `?q=&category=&type=`.
- Salah input -> `throw new ValidationException(['title' => 'Judul wajib diisi']);` (jadi 422 + errors per field).
- Aturan bisnis -> `throw new BusinessRuleException('pesan', 409);` atau `BusinessRuleException::notFound()`.
- Jangan `try/catch` di controller: `Router` sudah mengubah semua exception jadi JSON.
- Buat media baru: `MediaItem::fromArray([... 'id' => 'M' . strtoupper(bin2hex(random_bytes(3))) ...])`. Ubah: setter `setTitle/Author/Category/Year/Stock/Fee/Extra`.
- Ubah status member: `$user->activate()` / `$user->deactivate()` lalu `save()`. Daftar member = `findAll()` disaring `getRole() === 'member'`, kirim pakai `toPublicArray()` (tanpa hash).
- Daftarkan route di bagian TODO pada `public/api.php`.

## 4. Front-end (T7, T8)

Format respons semua endpoint:

```json
{ "success": true,  "data": ..., "message": "opsional" }
{ "success": false, "message": "...", "errors": { "email": "Email sudah digunakan" } }
```

`errors` hanya ada untuk status 422; kunci = nama field form. Status penting: 401 belum login, 403 tidak berhak, 404, 409 aturan bisnis (stok/kuota/ganda).

Endpoint tambahan di luar tabel 8.2 dokumen: `GET /api.php/auth/me` (cek session saat halaman dibuka; tamu mendapat `data: null`, bukan error).

Aturan validasi yang SAMA dengan server (pakai di `validator.js`):

| Field | Aturan |
| --- | --- |
| `name` | 3 sampai 50 karakter |
| `email` | format email valid |
| `password` | 8 sampai 72 karakter, memuat huruf dan angka |
| `confirmPassword` | sama dengan `password` |

Body register: `{name, email, password, confirmPassword}`. Login: `{email, password}`. Pinjam: `{mediaId}`.
Item riwayat (`GET /loans`): `id, mediaId, mediaTitle, mediaType, borrowedAt, dueAt, returnedAt, fee, status (active|completed), daysLate, isOverdue` (+ `userName` untuk admin).
Objek user: `id, role, tier, name, email, status, joinedAt, borrowLimit, loanDays`.

## 5. Keputusan desain yang perlu diketahui bersama

- Denda = `calculateFee(hariTerlambat)` media x `getFeeMultiplier()` member. Tarif per tipe: e-book x1, audiobook x1,5, jurnal x2 dari `fee` media. Premium dapat diskon 50% (pengali 0,5).
- Tanggal disimpan `Y-m-d`; terlambat dihitung per hari kalender.
- Zona waktu `Asia/Jakarta` (di `bootstrap.php`).
