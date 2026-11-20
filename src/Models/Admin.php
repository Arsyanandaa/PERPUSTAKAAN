<?php
declare(strict_types=1);

namespace App\Models;

final class Admin extends User
{
    public function getRole(): string { return 'admin'; }
    public function getTier(): string { return 'none'; }
    public function getBorrowLimit(): int { return 0; }
    public function getLoanDays(): int { return 0; }
    public function getFeeMultiplier(): float { return 1.0; }
}
