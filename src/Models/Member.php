<?php
declare(strict_types=1);

namespace App\Models;

class Member extends User
{
    public function getRole(): string { return 'member'; }
    public function getTier(): string { return 'regular'; }
    public function getBorrowLimit(): int { return 3; }
    public function getLoanDays(): int { return 7; }
    public function getFeeMultiplier(): float { return 1.0; }

    /** Simulasi upgrade: kembalikan object PremiumMember dengan data yang sama. */
    public function upgrade(): PremiumMember
    {
        return PremiumMember::fromArray([...$this->toArray(), 'tier' => 'premium']);
    }
}
