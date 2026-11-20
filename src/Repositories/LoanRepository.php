<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LoanRepositoryInterface;
use App\Models\Loan;
use InvalidArgumentException;

final class LoanRepository extends JsonRepository implements LoanRepositoryInterface
{
    protected function hydrate(array $row): object
    {
        return Loan::fromArray($row);
    }

    protected function dehydrate(object $entity): array
    {
        if (!$entity instanceof Loan) {
            throw new InvalidArgumentException('LoanRepository hanya dapat menyimpan Loan');
        }
        return $entity->toArray();
    }

    /** @return array<int, Loan> semua transaksi milik satu user (aktif dan selesai) */
    public function findByUserId(string $userId): array
    {
        $result = [];
        foreach ($this->readRows() as $row) {
            if ((string) ($row['userId'] ?? '') !== $userId) {
                continue;
            }
            $loan = $this->tryHydrate($row);
            if ($loan instanceof Loan) {
                $result[] = $loan;
            }
        }
        return $result;
    }
}
