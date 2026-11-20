<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\RepositoryInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Dasar semua repository berbasis file JSON.
 * - Baca memakai kunci bersama (LOCK_SH), tulis memakai kunci eksklusif (LOCK_EX).
 * - save() dan delete() membaca lalu menulis dalam SATU kunci, jadi dua request serentak tidak saling menimpa.
 * - File hilang, kosong, atau rusak diperlakukan sebagai [] (tidak fatal).
 * Subclass hanya menentukan cara mengubah array <-> object (hydrate / dehydrate).
 */
abstract class JsonRepository implements RepositoryInterface
{
    public function __construct(protected readonly string $path)
    {
    }

    /** Array satu baris JSON -> object model. */
    abstract protected function hydrate(array $row): object;

    /** Object model -> array satu baris JSON. */
    abstract protected function dehydrate(object $entity): array;

    /** @return array<int, object> */
    public function findAll(): array
    {
        $entities = [];
        foreach ($this->readRows() as $row) {
            $entity = $this->tryHydrate($row);
            if ($entity !== null) {
                $entities[] = $entity;
            }
        }
        return $entities;
    }

    public function findById(string $id): ?object
    {
        foreach ($this->readRows() as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $this->tryHydrate($row);
            }
        }
        return null;
    }

    /** Update jika id sudah ada (posisi baris dipertahankan), insert di akhir jika belum. */
    public function save(object $entity): void
    {
        $row = $this->dehydrate($entity);
        $id = (string) ($row['id'] ?? '');
        if ($id === '') {
            throw new InvalidArgumentException('Entitas harus memiliki id');
        }

        $this->mutate(static function (array $rows) use ($row, $id): array {
            foreach ($rows as $i => $existing) {
                if ((string) ($existing['id'] ?? '') === $id) {
                    $rows[$i] = $row;
                    return $rows;
                }
            }
            $rows[] = $row;
            return $rows;
        });
    }

    /** @return bool true jika ada baris yang dihapus */
    public function delete(string $id): bool
    {
        $found = false;
        $this->mutate(static function (array $rows) use ($id, &$found): ?array {
            $kept = [];
            foreach ($rows as $row) {
                if ((string) ($row['id'] ?? '') === $id) {
                    $found = true;
                    continue;
                }
                $kept[] = $row;
            }
            return $found ? $kept : null;   // null = tidak ada perubahan, file tidak ditulis ulang
        });
        return $found;
    }

    // ---------------------------------------------------------------- internal

    /** @return array<int, array> semua baris mentah; baris yang bukan array dibuang */
    protected function readRows(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            return [];
        }
        try {
            flock($handle, LOCK_SH);
            $raw = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        return $this->decode($raw === false ? '' : $raw);
    }

    /** Hydrate satu baris; baris rusak dilewati (dicatat di log), bukan membuat seluruh request gagal. */
    protected function tryHydrate(array $row): ?object
    {
        try {
            return $this->hydrate($row);
        } catch (Throwable $e) {
            error_log('[PustakaKu] Baris di ' . basename($this->path) . ' dilewati: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Baca-ubah-tulis atomik. $change menerima array baris dan mengembalikan array baru,
     * atau null jika tidak ada yang berubah.
     *
     * @param callable(array<int, array>): ?array $change
     */
    protected function mutate(callable $change): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder data tidak dapat dibuat');
        }

        $handle = fopen($this->path, 'c+b');   // dibuat jika belum ada, isi lama tidak dipotong dulu
        if ($handle === false) {
            throw new RuntimeException('File data tidak dapat dibuka: ' . basename($this->path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Gagal mengunci file data: ' . basename($this->path));
            }

            $raw = stream_get_contents($handle);
            $updated = $change($this->decode($raw === false ? '' : $raw));
            if ($updated === null) {
                return;
            }

            $json = json_encode(
                array_values($updated),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );

            rewind($handle);
            ftruncate($handle, 0);
            if (fwrite($handle, $json . PHP_EOL) === false) {
                throw new RuntimeException('Gagal menulis file data: ' . basename($this->path));
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<int, array> */
    private function decode(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('[PustakaKu] JSON rusak di ' . basename($this->path) . ': ' . $e->getMessage());
            return [];
        }
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}
