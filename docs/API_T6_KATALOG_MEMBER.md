# API T6: Katalog dan Member Admin (Anggota 2)

Format respons sama dengan `KONTRAK_ANGGOTA_2.md` bagian 4. Dokumen ini hanya menambah endpoint T6.

## Endpoint

| Method | Endpoint | Akses | Fungsi |
| --- | --- | --- | --- |
| GET | `/api.php/catalog?q=&category=&type=` | Semua (tamu boleh) | Daftar media, semua filter opsional (AND) |
| GET | `/api.php/catalog/categories` | Semua | Daftar kategori unik terurut abjad (untuk dropdown) |
| POST | `/api.php/catalog` | Admin | Tambah media, balas 201 |
| PUT | `/api.php/catalog/{id}` | Admin | Ubah media (kirim ulang semua field kecuali `type`) |
| DELETE | `/api.php/catalog/{id}` | Admin | Hapus media |
| GET | `/api.php/members` | Admin | Daftar member (admin tidak ikut, tanpa passwordHash) |
| PATCH | `/api.php/members/{id}/status` | Admin | Aktifkan atau nonaktifkan member |

Filter: `q` mencari di judul ATAU penulis (potongan kata); `category` persis sama; `type` = `ebook | audiobook | journal`. Semua tidak peka huruf besar/kecil. `type` di luar tiga nilai itu dibalas 422.

## Body tambah / ubah media

```json
{
  "type": "ebook",
  "title": "Belajar Git dari Nol",
  "author": "Tim Uji",
  "category": "Teknologi",
  "year": 2025,
  "stock": 3,
  "fee": 1000,
  "extra": { "pages": 120, "format": "PDF" }
}
```

- `type` wajib saat POST, **tidak bisa diubah** saat PUT (boleh dikirim asal sama, selain itu 422).
- `fee` = denda dasar per hari, boleh kosong (dianggap 0).
- PUT bersifat mengganti: field yang kurang dibalas 422, bukan dibiarkan seperti semula.

`extra` per tipe (semua wajib):

| type | field extra |
| --- | --- |
| ebook | `pages` (1-100000), `format` (`PDF` / `EPUB` / `MOBI`, huruf kecil diterima) |
| audiobook | `durationMinutes` (1-100000), `narrator` |
| journal | `publisher`, `volume` |

## Aturan validasi (sama untuk `validator.js` di T8)

| Field | Aturan | Kunci `errors` |
| --- | --- | --- |
| `type` | ebook, audiobook, atau journal | `type` |
| `title` | 1 sampai 150 karakter | `title` |
| `author` | 1 sampai 100 karakter | `author` |
| `category` | 1 sampai 50 karakter | `category` |
| `year` | bilangan bulat 1900 sampai tahun depan | `year` |
| `stock` | bilangan bulat 0 sampai 9999 | `stock` |
| `fee` | angka 0 sampai 1.000.000 | `fee` |
| field di dalam `extra` | lihat tabel di atas | `extra.pages`, `extra.format`, `extra.durationMinutes`, `extra.narrator`, `extra.publisher`, `extra.volume` |

Angka boleh dikirim sebagai string angka (`"2024"`). Teks di-trim. Error 422 memuat SEMUA field yang salah sekaligus.

## Body ubah status member

```json
{ "status": "active" }
```

`status` = `active` atau `inactive`, selain itu 422 (`errors.status`). Mengirim status yang sama dua kali tidak error. Member nonaktif tidak bisa login (403).

## Status HTTP

| Kode | Arti |
| --- | --- |
| 200 / 201 | Berhasil (201 untuk POST katalog) |
| 401 | Belum login |
| 403 | Login tetapi bukan admin |
| 404 | Media atau member tidak ditemukan. Akun admin dianggap "member tidak ditemukan" pada `/members/{id}/status` |
| 409 | Hapus media yang masih punya **pinjaman aktif**. Setelah dikembalikan, baru bisa dihapus |
| 422 | Validasi gagal, `errors` berisi kunci seperti tabel di atas |

## Catatan perilaku

- Menghapus media TIDAK menghapus riwayat yang sudah selesai: riwayat menampilkan judul `(media dihapus)`.
- Id media dibuat server (`M` + 6 karakter hex), klien tidak mengirim `id`.
- `stock` adalah stok yang tersedia. Pinjam menguranginya, kembali menambahnya, admin bisa menimpanya lewat PUT.
