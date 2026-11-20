<?php
declare(strict_types=1);

namespace App\Models;

use App\Contracts\Borrowable;
use App\Exceptions\BusinessRuleException;
use InvalidArgumentException;

abstract class MediaItem implements Borrowable
{
    public function __construct(
        private readonly string $id,
        protected string $title,
        protected string $author,
        protected string $category,
        protected int $year,
        protected int $stock,
        protected float $fee = 0.0,
        protected array $extra = [],
    ) {}

    // ---- getter ----
    public function getId(): string { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getAuthor(): string { return $this->author; }
    public function getCategory(): string { return $this->category; }
    public function getYear(): int { return $this->year; }
    public function getStock(): int { return $this->stock; }
    public function getFee(): float { return $this->fee; }
    public function getExtra(): array { return $this->extra; }

    // ---- setter (dipakai CatalogService milik Anggota 2) ----
    public function setTitle(string $v): void { $this->title = $v; }
    public function setAuthor(string $v): void { $this->author = $v; }
    public function setCategory(string $v): void { $this->category = $v; }
    public function setYear(int $v): void { $this->year = $v; }
    public function setFee(float $v): void { $this->fee = max(0.0, $v); }
    public function setExtra(array $v): void { $this->extra = $v; }
    public function setStock(int $v): void { $this->stock = max(0, $v); }

    // ---- Borrowable ----
    public function canBeBorrowed(): bool { return $this->stock > 0; }

    public function markBorrowed(): void
    {
        if (!$this->canBeBorrowed()) {
            throw new BusinessRuleException('Stok habis, media tidak dapat dipinjam', 409);
        }
        $this->stock--;
    }

    public function markReturned(): void { $this->stock++; }

    // ---- polymorphism: diimplementasi tiap subclass ----
    abstract public function getType(): string;
    abstract public function calculateFee(int $daysLate): float;
    abstract public function describe(): string;

    protected function lateDays(int $daysLate): int { return max(0, $daysLate); }

    /** Bentuk sama dengan satu entri media.json (juga dipakai sebagai respons API). */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->getType(),
            'title'    => $this->title,
            'author'   => $this->author,
            'category' => $this->category,
            'year'     => $this->year,
            'stock'    => $this->stock,
            'fee'      => $this->fee,
            'extra'    => (object) $this->extra,   // selalu {} di JSON, bukan []
        ];
    }

    /** Factory dari array media.json. type: ebook | audiobook | journal */
    public static function fromArray(array $d): self
    {
        $class = match ($d['type'] ?? '') {
            'ebook'     => EBook::class,
            'audiobook' => AudioBook::class,
            'journal'   => Journal::class,
            default     => throw new InvalidArgumentException('Tipe media tidak dikenal: ' . ($d['type'] ?? '(kosong)')),
        };

        return new $class(
            id: (string) $d['id'],
            title: (string) ($d['title'] ?? ''),
            author: (string) ($d['author'] ?? ''),
            category: (string) ($d['category'] ?? ''),
            year: (int) ($d['year'] ?? 0),
            stock: max(0, (int) ($d['stock'] ?? 0)),
            fee: (float) ($d['fee'] ?? 0),
            extra: (array) ($d['extra'] ?? []),
        );
    }
}
