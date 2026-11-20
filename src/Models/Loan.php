<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;

final class Loan
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    /** Tanggal disimpan format Y-m-d. */
    public function __construct(
        private readonly string $id,
        private readonly string $userId,
        private readonly string $mediaId,
        private readonly string $borrowedAt,
        private readonly string $dueAt,
        private ?string $returnedAt = null,
        private float $fee = 0.0,
        private string $status = self::STATUS_ACTIVE,
    ) {}

    public function getId(): string { return $this->id; }
    public function getUserId(): string { return $this->userId; }
    public function getMediaId(): string { return $this->mediaId; }
    public function getBorrowedAt(): string { return $this->borrowedAt; }
    public function getDueAt(): string { return $this->dueAt; }
    public function getReturnedAt(): ?string { return $this->returnedAt; }
    public function getFee(): float { return $this->fee; }
    public function getStatus(): string { return $this->status; }
    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }

    /** Hari terlambat per tanggal $at (0 jika belum lewat jatuh tempo). */
    public function daysLate(DateTimeImmutable $at): int
    {
        $due   = (new DateTimeImmutable($this->dueAt))->setTime(0, 0);
        $today = $at->setTime(0, 0);
        return max(0, (int) $due->diff($today)->format('%r%a'));
    }

    public function isOverdue(DateTimeImmutable $at): bool
    {
        return $this->isActive() && $this->daysLate($at) > 0;
    }

    /** Selesaikan transaksi. Pembayaran denda hanya simulasi. */
    public function complete(DateTimeImmutable $at, float $fee): void
    {
        $this->returnedAt = $at->format('Y-m-d');
        $this->fee = $fee;
        $this->status = self::STATUS_COMPLETED;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'userId'     => $this->userId,
            'mediaId'    => $this->mediaId,
            'borrowedAt' => $this->borrowedAt,
            'dueAt'      => $this->dueAt,
            'returnedAt' => $this->returnedAt,
            'fee'        => $this->fee,
            'status'     => $this->status,
        ];
    }

    public static function fromArray(array $d): self
    {
        return new self(
            id: (string) $d['id'],
            userId: (string) $d['userId'],
            mediaId: (string) $d['mediaId'],
            borrowedAt: (string) $d['borrowedAt'],
            dueAt: (string) $d['dueAt'],
            returnedAt: isset($d['returnedAt']) ? (string) $d['returnedAt'] : null,
            fee: (float) ($d['fee'] ?? 0),
            status: (string) ($d['status'] ?? self::STATUS_ACTIVE),
        );
    }
}
