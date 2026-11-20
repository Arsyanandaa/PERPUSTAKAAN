<?php
declare(strict_types=1);

namespace App\Models;

abstract class User
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    private readonly string $joinedAt;

    public function __construct(
        private readonly string $id,
        protected string $name,
        private readonly string $email,
        private string $passwordHash,
        protected string $status = self::STATUS_ACTIVE,
        ?string $joinedAt = null,
    ) {
        $this->joinedAt = $joinedAt ?? date('Y-m-d');
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getStatus(): string { return $this->status; }
    public function getJoinedAt(): string { return $this->joinedAt; }
    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function activate(): void { $this->status = self::STATUS_ACTIVE; }
    public function deactivate(): void { $this->status = self::STATUS_INACTIVE; }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->passwordHash);
    }

    public function canBorrow(): bool { return $this->isActive() && $this->getBorrowLimit() > 0; }

    // ---- polymorphism ----
    abstract public function getRole(): string;          // admin | member
    abstract public function getTier(): string;          // none | regular | premium
    abstract public function getBorrowLimit(): int;      // maks pinjaman aktif
    abstract public function getLoanDays(): int;         // lama pinjam (hari)
    abstract public function getFeeMultiplier(): float;  // pengali denda

    /** Untuk penyimpanan (users.json). MENGANDUNG passwordHash, jangan kirim ke klien. */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'role'         => $this->getRole(),
            'tier'         => $this->getTier(),
            'name'         => $this->name,
            'email'        => $this->email,
            'passwordHash' => $this->passwordHash,
            'status'       => $this->status,
            'joinedAt'     => $this->joinedAt,
        ];
    }

    /** Untuk respons JSON. TIDAK memuat passwordHash. */
    public function toPublicArray(): array
    {
        $a = $this->toArray();
        unset($a['passwordHash']);
        $a['borrowLimit'] = $this->getBorrowLimit();
        $a['loanDays'] = $this->getLoanDays();
        return $a;
    }

    /** Factory dari array users.json. */
    public static function fromArray(array $d): self
    {
        $class = match (true) {
            ($d['role'] ?? '') === 'admin'   => Admin::class,
            ($d['tier'] ?? '') === 'premium' => PremiumMember::class,
            default                          => Member::class,
        };

        return new $class(
            id: (string) $d['id'],
            name: (string) ($d['name'] ?? ''),
            email: (string) ($d['email'] ?? ''),
            passwordHash: (string) ($d['passwordHash'] ?? ''),
            status: (string) ($d['status'] ?? self::STATUS_ACTIVE),
            joinedAt: isset($d['joinedAt']) ? (string) $d['joinedAt'] : null,
        );
    }
}
