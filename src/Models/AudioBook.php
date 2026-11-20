<?php
declare(strict_types=1);

namespace App\Models;

final class AudioBook extends MediaItem
{
    public function getType(): string { return 'audiobook'; }

    public function calculateFee(int $daysLate): float
    {
        return $this->lateDays($daysLate) * $this->fee * 1.5; // audiobook lebih mahal
    }

    public function describe(): string
    {
        $minutes  = $this->extra['durationMinutes'] ?? '?';
        $narrator = $this->extra['narrator'] ?? '-';
        return "Audiobook: {$this->title} ({$minutes} menit, narator {$narrator})";
    }
}
