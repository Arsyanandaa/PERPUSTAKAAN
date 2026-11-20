<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\LoanRepositoryInterface;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\Loan;
use App\Models\MediaItem;
use App\Repositories\MediaRepository;

/**
 * F-01 dan F-04: katalog media. Seluruh validasi input dan aturan bisnis katalog ada di sini,
 * controller hanya meneruskan input dan membentuk respons.
 */
final class CatalogService
{
    public const TYPES = ['ebook', 'audiobook', 'journal'];
    public const EBOOK_FORMATS = ['PDF', 'EPUB', 'MOBI'];

    public function __construct(
        private readonly MediaRepository $media,
        private readonly LoanRepositoryInterface $loans,
    ) {}

    // ------------------------------------------------------------------ baca

    /**
     * F-01: daftar media, boleh disaring. Semua filter opsional dan digabung dengan AND.
     * @return array<int, array> bentuk sama dengan satu entri media.json
     */
    public function list(?string $q = null, ?string $category = null, ?string $type = null): array
    {
        $type = $type === null ? null : strtolower(trim($type));
        if ($type !== null && $type !== '' && !in_array($type, self::TYPES, true)) {
            throw new ValidationException(['type' => 'Tipe harus ebook, audiobook, atau journal']);
        }

        return array_map(
            static fn (MediaItem $i): array => $i->toArray(),
            $this->media->search($q, $category, $type),
        );
    }

    /** @return array<int, string> kategori unik, untuk dropdown filter */
    public function categories(): array
    {
        return $this->media->findCategories();
    }

    // ------------------------------------------------------------------ tulis (admin)

    /** F-04: tambah media. type wajib dan tidak bisa diubah setelahnya. */
    public function create(array $data): MediaItem
    {
        $errors = [];

        $type = is_string($data['type'] ?? null) ? strtolower(trim($data['type'])) : '';
        if (!in_array($type, self::TYPES, true)) {
            $errors['type'] = 'Tipe harus ebook, audiobook, atau journal';
            $type = null;   // field tipe-spesifik tidak bisa dicek, tapi field umum tetap diperiksa
        }

        $clean = $this->validate($data, $type, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        do {
            $id = 'M' . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->media->findById($id) !== null);

        $item = MediaItem::fromArray(['id' => $id, 'type' => $type] + $clean);
        $this->media->save($item);
        return $item;
    }

    /** F-04: ubah media. PUT = semua field yang bisa diubah dikirim ulang; tipe tidak boleh berubah. */
    public function update(string $id, array $data): MediaItem
    {
        $item = $this->find($id);

        $errors = [];
        if (array_key_exists('type', $data) && $data['type'] !== null && $data['type'] !== ''
            && (!is_string($data['type']) || strtolower(trim($data['type'])) !== $item->getType())) {
            $errors['type'] = 'Tipe media tidak dapat diubah';
        }

        $clean = $this->validate($data, $item->getType(), $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $item->setTitle($clean['title']);
        $item->setAuthor($clean['author']);
        $item->setCategory($clean['category']);
        $item->setYear($clean['year']);
        $item->setStock($clean['stock']);
        $item->setFee($clean['fee']);
        $item->setExtra($clean['extra']);
        $this->media->save($item);
        return $item;
    }

    /**
     * F-04: hapus media. Ditolak (409) selama masih ada pinjaman aktif, karena pengembaliannya
     * tidak akan bisa memulihkan stok dan menghitung denda. Riwayat selesai tetap aman
     * (ditampilkan sebagai "(media dihapus)").
     */
    public function delete(string $id): void
    {
        $item = $this->find($id);

        foreach ($this->loans->findAll() as $loan) {
            if ($loan instanceof Loan && $loan->isActive() && $loan->getMediaId() === $item->getId()) {
                throw new BusinessRuleException('Media sedang dipinjam, tidak dapat dihapus', 409);
            }
        }
        $this->media->delete($item->getId());
    }

    // ------------------------------------------------------------------ internal

    private function find(string $id): MediaItem
    {
        $item = $this->media->findById($id);
        return $item instanceof MediaItem ? $item : throw BusinessRuleException::notFound('Media tidak ditemukan');
    }

    /**
     * Validasi field media. Pesan kesalahan ditambahkan ke $errors (kunci = nama field form;
     * field di dalam extra memakai awalan "extra.", mis. "extra.pages").
     * Jika $type null, hanya field umum yang diperiksa.
     *
     * @param array<string, string> $errors
     * @return array{title:string, author:string, category:string, year:int, stock:int, fee:float, extra:array}
     */
    private function validate(array $d, ?string $type, array &$errors): array
    {
        $maxYear = (int) date('Y') + 1;

        $year = self::toInt($d['year'] ?? null);
        if ($year === null || $year < 1900 || $year > $maxYear) {
            $errors['year'] = "Tahun harus berupa angka antara 1900 dan {$maxYear}";
        }

        $stock = self::toInt($d['stock'] ?? null);
        if ($stock === null || $stock < 0 || $stock > 9999) {
            $errors['stock'] = 'Stok harus berupa bilangan bulat 0 sampai 9999';
        }

        $fee = 0.0;   // denda per hari; kosong dianggap 0
        if (array_key_exists('fee', $d) && $d['fee'] !== null && $d['fee'] !== '') {
            $parsed = self::toFloat($d['fee']);
            if ($parsed === null || $parsed < 0 || $parsed > 1_000_000) {
                $errors['fee'] = 'Denda per hari harus berupa angka 0 sampai 1.000.000';
            } else {
                $fee = round($parsed, 2);
            }
        }

        return [
            'title'    => self::text($d['title'] ?? null, 'title', 'Judul', 150, $errors),
            'author'   => self::text($d['author'] ?? null, 'author', 'Penulis', 100, $errors),
            'category' => self::text($d['category'] ?? null, 'category', 'Kategori', 50, $errors),
            'year'     => $year ?? 0,
            'stock'    => $stock ?? 0,
            'fee'      => $fee,
            'extra'    => $type === null ? [] : $this->validateExtra($d['extra'] ?? null, $type, $errors),
        ];
    }

    /** @param array<string, string> $errors */
    private function validateExtra(mixed $raw, string $type, array &$errors): array
    {
        $e = is_array($raw) ? $raw : [];

        switch ($type) {
            case 'ebook':
                $pages = self::toInt($e['pages'] ?? null);
                if ($pages === null || $pages < 1 || $pages > 100000) {
                    $errors['extra.pages'] = 'Jumlah halaman harus berupa angka 1 sampai 100000';
                }
                $format = is_string($e['format'] ?? null) ? strtoupper(trim($e['format'])) : '';
                if (!in_array($format, self::EBOOK_FORMATS, true)) {
                    $errors['extra.format'] = 'Format harus ' . implode(', ', self::EBOOK_FORMATS);
                }
                return ['pages' => $pages ?? 0, 'format' => $format];

            case 'audiobook':
                $minutes = self::toInt($e['durationMinutes'] ?? null);
                if ($minutes === null || $minutes < 1 || $minutes > 100000) {
                    $errors['extra.durationMinutes'] = 'Durasi (menit) harus berupa angka 1 sampai 100000';
                }
                return [
                    'durationMinutes' => $minutes ?? 0,
                    'narrator' => self::text($e['narrator'] ?? null, 'extra.narrator', 'Narator', 100, $errors),
                ];

            default: // journal
                return [
                    'publisher' => self::text($e['publisher'] ?? null, 'extra.publisher', 'Penerbit', 100, $errors),
                    'volume'    => self::text($e['volume'] ?? null, 'extra.volume', 'Volume', 50, $errors),
                ];
        }
    }

    /** Teks wajib 1 sampai $max karakter (Unicode dihitung per karakter, tanpa ext-mbstring). */
    private static function text(mixed $v, string $field, string $label, int $max, array &$errors): string
    {
        $s = is_string($v) ? trim($v) : '';
        $len = (int) preg_match_all('/./us', $s);
        if ($len === 0) {
            $errors[$field] = "{$label} wajib diisi";
        } elseif ($len > $max) {
            $errors[$field] = "{$label} maksimal {$max} karakter";
        }
        return $s;
    }

    /** Bilangan bulat dari int, float utuh, atau string angka. Selain itu (termasuk bool/array) = null. */
    private static function toInt(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && is_finite($v) && floor($v) === $v) {
            return (int) $v;
        }
        if (is_string($v) && preg_match('/^\s*-?\d+\s*$/', $v) === 1) {
            return (int) trim($v);
        }
        return null;
    }

    private static function toFloat(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            $f = (float) $v;
        } elseif (is_string($v) && is_numeric($v)) {
            $f = (float) $v;
        } else {
            return null;
        }
        return is_finite($f) ? $f : null;
    }
}
