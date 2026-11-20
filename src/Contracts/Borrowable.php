<?php
declare(strict_types=1);

namespace App\Contracts;

/** Kontrak untuk objek yang bisa dipinjam. */
interface Borrowable
{
    public function canBeBorrowed(): bool;
    public function markBorrowed(): void;   // stok -1
    public function markReturned(): void;   // stok +1
}
