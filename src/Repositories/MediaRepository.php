<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\MediaItem;
use InvalidArgumentException;

final class MediaRepository extends JsonRepository
{
    protected function hydrate(array $row): object
    {
        return MediaItem::fromArray($row);   // memilih EBook / AudioBook / Journal dari type
    }

    protected function dehydrate(object $entity): array
    {
        if (!$entity instanceof MediaItem) {
            throw new InvalidArgumentException('MediaRepository hanya dapat menyimpan MediaItem');
        }
        return $entity->toArray();
    }

    /**
     * Cari dan saring katalog. Semua parameter opsional dan digabung dengan AND.
     * - $q        : potongan kata pada judul ATAU penulis
     * - $category : kategori persis sama
     * - $type     : ebook | audiobook | journal
     * Perbandingan tidak peka huruf besar/kecil. Parameter null atau kosong diabaikan.
     *
     * @return array<int, MediaItem>
     */
    public function search(?string $q = null, ?string $category = null, ?string $type = null): array
    {
        $q        = self::fold($q);
        $category = self::fold($category);
        $type     = self::fold($type);

        $result = [];
        foreach ($this->findAll() as $item) {
            if (!$item instanceof MediaItem) {
                continue;
            }
            if ($category !== '' && self::fold($item->getCategory()) !== $category) {
                continue;
            }
            if ($type !== '' && self::fold($item->getType()) !== $type) {
                continue;
            }
            if ($q !== '' && !str_contains(self::fold($item->getTitle()), $q)
                           && !str_contains(self::fold($item->getAuthor()), $q)) {
                continue;
            }
            $result[] = $item;
        }
        return $result;
    }

    /** Daftar kategori unik (urut abjad), untuk dropdown filter di front-end. @return array<int, string> */
    public function findCategories(): array
    {
        $categories = [];
        foreach ($this->findAll() as $item) {
            if ($item instanceof MediaItem && $item->getCategory() !== '') {
                $categories[$item->getCategory()] = true;
            }
        }
        $names = array_keys($categories);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    private static function fold(?string $s): string
    {
        $s = trim((string) $s);
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }
}
