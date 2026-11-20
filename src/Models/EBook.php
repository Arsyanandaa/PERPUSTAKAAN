<?php
declare(strict_types=1);

namespace App\Models;

final class EBook extends MediaItem
{
    public function getType(): string { return 'ebook'; }

    public function calculateFee(int $daysLate): float
    {
        return $this->lateDays($daysLate) * $this->fee;
    }

    public function describe(): string
    {
        $pages  = $this->extra['pages'] ?? '?';
        $format = $this->extra['format'] ?? '-';
        return "E-Book: {$this->title} ({$pages} hal, {$format})";
    }
}
