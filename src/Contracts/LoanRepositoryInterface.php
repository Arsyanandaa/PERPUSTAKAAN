<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Loan;

interface LoanRepositoryInterface extends RepositoryInterface
{
    /** @return array<int, Loan> semua transaksi milik satu user (aktif dan selesai) */
    public function findByUserId(string $userId): array;
}
