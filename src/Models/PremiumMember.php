<?php
declare(strict_types=1);

namespace App\Models;

final class PremiumMember extends Member
{
    public function getTier(): string { return 'premium'; }
    public function getBorrowLimit(): int { return 10; }
    public function getLoanDays(): int { return 14; }
    public function getFeeMultiplier(): float { return 0.5; } // diskon denda 50%
}
