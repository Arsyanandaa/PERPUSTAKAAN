<?php
declare(strict_types=1);

namespace App\Models;

final class Journal extends MediaItem
{
    public function getType(): string { return 'journal'; }

    public function calculateFee(int $daysLate): float
    {
        return $this->lateDays($daysLate) * $this->fee * 2.0; // jurnal: tarif tertinggi
    }

    public function describe(): string
    {
        $publisher = $this->extra['publisher'] ?? '-';
        $volume    = $this->extra['volume'] ?? '-';
        return "Jurnal: {$this->title} ({$publisher}, vol. {$volume})";
    }
}
