# PustakaKu: Perpustakaan Digital (PHP 8 OOP)

Prototipe perpustakaan digital tanpa framework, data disimpan di JSON.

## Menjalankan

```bash
php -v                              # butuh PHP 8.1+
php -S localhost:8000 -t public     # dari folder perpustakaan/
# buka http://localhost:8000
```

## Pengujian backend

```bash
php tests/smoke_test.php            # tes service/router tanpa file JSON
for f in $(find . -name '*.php'); do php -l "$f"; done
```

## Struktur

Lihat bagian 4 dokumen rancangan. Kontrak antar anggota tim: `docs/KONTRAK_ANGGOTA_2.md`.

## Status

- [x] T1 Fondasi, T2 Class inti, T3 Model, T4 Service dan controller (Anggota 1)
- [ ] T5 Data dan repository, T6 Katalog, T7 HTML/CSS, T8 JavaScript (Anggota 2)
- [ ] T9 Integrasi, T10 Finalisasi (bersama)
