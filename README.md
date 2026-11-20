# PustakaKu: Perpustakaan Digital (E-Book) berbasis PHP 8 OOP

Prototipe aplikasi web perpustakaan digital mandiri tanpa framework (*vanilla PHP 8.1+*), penyimpanan data berbasis file JSON dengan proteksi `flock` *concurrency*, arsitektur berlapis modular (PSR-4), serta antarmuka web responsif dan semantik.

---

## 🎯 Status Penyelesaian Proyek (T1 - T10: SELESAI 100%)

- [x] **T1: Fondasi Proyek** — Struktur direktori `src/`, `public/`, `data/`, autoloader PSR-4, `bootstrap.php` (*Anggota 1*)
- [x] **T2: Class Inti** — `Response`, `Router`, `Session`, kontrak `Borrowable` & `RepositoryInterface` (*Anggota 1*)
- [x] **T3: Model & Hierarki OOP** — Hierarki `MediaItem` (`EBook`, `AudioBook`, `Journal`) & `User` (`Admin`, `Member`, `PremiumMember`), `Loan` (*Anggota 1*)
- [x] **T4: Service & Controller Transaksi** — `AuthService`, `LoanService`, `AuthController`, `LoanController` (*Anggota 1*)
- [x] **T5: Data Awal & Repository** — `JsonRepository`, `MediaRepository`, `UserRepository`, `LoanRepository`, seed data awal (*Anggota 2*)
- [x] **T6: Katalog & Manajemen Admin API** — `CatalogService`, `CatalogController`, `MemberService`, `MemberController`, endpoint REST API (*Anggota 2*)
- [x] **T7: HTML & CSS Front-End** — Desain antarmuka responsif semantik, palet warm-library, multi-halaman (`index.html`, `login.html`, `register.html`, `member.html`, `admin.html`, `style.css`) (*Anggota 2*)
- [x] **T8: JavaScript Interaktif** — `api.js` (Fetch wrapper), `validator.js` (validasi form klien), `auth.js`, `catalog.js`, `member.js`, `admin.js` (*Anggota 2*)
- [x] **T9: Integrasi & Pengujian** — Verifikasi 9 skenario uji fungsional (Tabel 10.1), pengecekan sintaks `php -l` seluruh berkas tanpa error (*Bersama*)
- [x] **T10: Finalisasi** — Dokumentasi lengkap, penyelarasan data demo, dan pemenuhan rubrik penilaian (*Bersama*)

---

## 🚀 Cara Menjalankan Aplikasi

### Kebutuhan Sistem:
- PHP 8.1 atau versi yang lebih baru (`php -v`)
- Ekstensi PHP bawaan: `ext-json`, `ext-mbstring` (opsional, sudah ada fallback regex)
- Tanpa dependensi eksternal (tidak memerlukan Composer, Node.js, maupun Database Server)

### Langkah Menjalankan:
1. Buka terminal di folder utama proyek `PERPUSTAKAAN/`.
2. Jalankan server lokal bawaan PHP:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Buka peramban (*browser*) dan akses:
   👉 **`http://localhost:8000/`**

---

## 👥 Akun Demo Pengujian

Data akun siap pakai tersimpan di `data/users.json`:

| Peran (Role) | Email | Password | Hak Akses & Fitur |
|---|---|---|---|
| **Admin** | `admin@pustakaku.test` | `admin12345` | Akses penuh Panel Admin (CRUD Media, kelola status member, riwayat semua transaksi) |
| **Member Reguler** | `siti@pustakaku.test` | `member12345` | Kuota pinjam 3 buku, masa pinjam 7 hari, denda tarif normal |
| **Member Premium** | `budi@pustakaku.test` | `member12345` | Kuota pinjam 10 buku, masa pinjam 14 hari, diskon denda 50% |
| **Member Nonaktif** | `rina@pustakaku.test` | `member12345` | Akses login ditolak (status nonaktif oleh admin) |

*Catatan: Pendaftaran anggota baru melalui form `register.html` juga mendukung alamat email umum seperti `@gmail.com`.*

---

## 🧪 Pengujian Sistem

Proyek ini dilengkapi rangkaian pengujian otomatis dan manual:

### 1. Uji Sintaks PHP (Zero Syntax Error)
```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

### 2. Uji Integrasi 9 Skenario Spesifikasi (Tabel 10.1)
```bash
php tests/integration_test.php
```
Mencakup pengujian otomatis:
1. Penolakan registrasi email ganda.
2. Penolakan login password salah tanpa membocorkan eksistensi akun.
3. Penolakan peminjaman melebihi batas kuota (kuota 3 buku pada member reguler).
4. Penolakan peminjaman media dengan stok 0.
5. Penolakan peminjaman ganda pada media yang sama.
6. Perhitungan denda polimorfis berdasarkan jenis media dan tier member.
7. Otorisasi proteksi hak akses endpoint admin (respons 403 Forbidden).
8. Penanganan toleransi file JSON kosong / rusak tanpa crash.
9. Pengecekan breakpoint responsif CSS pada lebar mobile (360px).

### 3. Uji Unit Backend
```bash
php tests/smoke_test.php
```

---

## 🏗️ Struktur Direktori Proyek

```
PERPUSTAKAAN/
├── bootstrap.php                 # Autoloader, konfigurasi timezone & error handling
├── README.md                     # Dokumentasi utama proyek
├── data/                         # Penyimpanan data JSON (dengan penguncian file)
│   ├── media.json                # Katalog media (E-Book, Audiobook, Jurnal)
│   ├── users.json                # Akun admin & member
│   └── loans.json                # Data transaksi peminjaman
├── docs/                         # Dokumen kontrak dan spesifikasi API
│   ├── API_T6_KATALOG_MEMBER.md
│   └── KONTRAK_ANGGOTA_2.md
├── public/                       # Dokumen root web server
│   ├── index.html                # Halaman Katalog Publik
│   ├── login.html                # Halaman Masuk
│   ├── register.html             # Halaman Registrasi Member Baru
│   ├── member.html               # Dashboard Member (Pinjaman, Riwayat, Upgrade)
│   ├── admin.html                # Panel Kontrol Administrator
│   ├── api.php                   # Front controller REST API
│   ├── css/
│   │   └── style.css             # Stylesheet responsif warm-library
│   └── js/
│       ├── api.js                # Fetch API client wrapper
│       ├── validator.js          # Validasi form client-side
│       ├── auth.js               # Manajemen sesi & sinkronisasi navbar
│       ├── catalog.js            # Interaksi katalog & live search debounce
│       ├── member.js             # Interaksi dashboard peminjaman member
│       └── admin.js              # Interaksi tab & CRUD panel admin
├── src/                          # Backend PHP (Arsitektur Berlapis & PSR-4)
│   ├── Contracts/                # Interface (RepositoryInterface, Borrowable, dll.)
│   ├── Controllers/              # Penghubung HTTP (Auth, Catalog, Loan, Member)
│   ├── Core/                     # Autoloader, Router, Response JSON, Session
│   ├── Exceptions/               # ValidationException, BusinessRuleException
│   ├── Models/                   # Entitas OOP (MediaItem, User, Loan, dsb.)
│   ├── Repositories/             # Akses JSON dengan flock (Media, User, Loan)
│   └── Services/                 # Aturan bisnis murni (Auth, Catalog, Loan, Member)
└── tests/                        # Skrip pengujian otomatis
    ├── smoke_test.php            # Tes logika model, service, dan router
    ├── t6_test.php               # Tes endpoint katalog dan member admin
    └── integration_test.php      # Tes integrasi 9 skenario spesifikasi
```

---

## 📋 Pemenuhan Rubrik Penilaian (Total: 100 Poin)

| Komponen | Poin | Indikator | Implementasi Nyata pada Proyek |
|---|---|---|---|
| **Front-End** | 30 | Form Validasi | `validator.js` memvalidasi email, kekuatan password, konfirmasi, tahun, stok, denda, dan metadata per tipe sebelum submit. |
| | | Elemen Semantik | Menggunakan `<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<dialog>/<modal>`, `<fieldset>`, label aksesibel. |
| | | Layout Responsif | CSS Grid `repeat(auto-fill, minmax(...))`, Flexbox, breakpoint responsif pada 768px dan 480px (nyaman di 360px). |
| | | Interaktivitas JS | Live search dengan debounce 300ms, filter kategori, toast alert, modal pop-up tambah/edit media, fetch API asinkron. |
| **PHP OOP** | 40 | Class & Object | 30+ class modular terbagi atas Model, Repository, Service, Controller, dan Core. |
| | | Encapsulation | Properti `private`/`protected`, getter/setter, `readonly id`, `declare(strict_types=1)`, proteksi `passwordHash`. |
| | | Inheritance | `EBook`, `AudioBook`, `Journal` mewarisi `MediaItem`; `Admin`, `Member`, `PremiumMember` mewarisi `User`. |
| | | Polymorphism | `calculateFee()`, `describe()`, `getBorrowLimit()`, `getLoanDays()`, `getFeeMultiplier()` di-override pada subclass; `LoanService` bekerja pada tipe induk tanpa `switch-case`. |
| | | Namespace | Standar PSR-4 dengan root namespace `App\` (`App\Models`, `App\Services`, `App\Repositories`, `App\Controllers`, `App\Core`). |
| **Kerapian** | 30 | Penataan Folder | Struktur `src/` modular, pemisahan yang jelas antara presenter, domain logic, dan storage. |
| | | Modularitas Berkas | Satu class satu file, dependensi dialirkan via Constructor Injection. |
| | | Bebas Error | Seluruh berkas lolos `php -l`, penanganan exception JSON seragam, 100% tes integrasi lulus. |
