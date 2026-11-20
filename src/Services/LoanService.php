<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\LoanRepositoryInterface;
use App\Contracts\RepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\Loan;
use App\Models\MediaItem;
use App\Models\User;
use DateTimeImmutable;

/**
 * Seluruh aturan transaksi. Memakai tipe induk (MediaItem, User) sehingga
 * tidak ada if/switch per tipe: perbedaan perilaku diputuskan subclass (polymorphism).
 */
final class LoanService
{
    public function __construct(
        private readonly LoanRepositoryInterface $loans,
        private readonly RepositoryInterface $media,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** F-06: pinjam media. Lolos 4 pemeriksaan baru tercatat. */
    public function borrow(User $user, string $mediaId, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $mediaId = trim($mediaId);
        if ($mediaId === '') {
            throw new ValidationException(['mediaId' => 'mediaId wajib diisi']);
        }

        // Pemeriksaan 1: peminjam sah dan aktif
        if ($user->getBorrowLimit() === 0) {
            throw BusinessRuleException::forbidden('Hanya member yang dapat meminjam');
        }
        if (!$user->isActive()) {
            throw BusinessRuleException::forbidden('Akun Anda nonaktif, tidak dapat meminjam');
        }

        // Pemeriksaan 2: media ada dan stok tersedia
        $item = $this->findMedia($mediaId) ?? throw BusinessRuleException::notFound('Media tidak ditemukan');
        if (!$item->canBeBorrowed()) {
            throw new BusinessRuleException('Stok media habis', 409);
        }

        // Pemeriksaan 3 dan 4: tidak pinjaman ganda, kuota belum penuh
        $active = array_values(array_filter(
            $this->loans->findByUserId($user->getId()),
            static fn (Loan $l): bool => $l->isActive(),
        ));
        foreach ($active as $l) {
            if ($l->getMediaId() === $mediaId) {
                throw new BusinessRuleException('Anda sedang meminjam media ini', 409);
            }
        }
        if (count($active) >= $user->getBorrowLimit()) {
            throw new BusinessRuleException(
                'Kuota pinjaman aktif terlampaui (maks ' . $user->getBorrowLimit() . ')', 409
            );
        }

        $item->markBorrowed();
        $this->media->save($item);

        $loan = new Loan(
            id: 'L' . strtoupper(bin2hex(random_bytes(4))),
            userId: $user->getId(),
            mediaId: $mediaId,
            borrowedAt: $now->format('Y-m-d'),
            dueAt: $now->modify('+' . $user->getLoanDays() . ' days')->format('Y-m-d'),
        );
        $this->loans->save($loan);

        return $this->present($loan, $now, $item);
    }

    /** F-07: kembalikan media, hitung denda, tambah stok. */
    public function returnLoan(User $user, string $loanId, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();

        $loan = $this->loans->findById($loanId);
        if (!$loan instanceof Loan) {
            throw BusinessRuleException::notFound('Transaksi tidak ditemukan');
        }
        if ($loan->getUserId() !== $user->getId()) {
            throw BusinessRuleException::forbidden('Transaksi ini bukan milik Anda');
        }
        if (!$loan->isActive()) {
            throw new BusinessRuleException('Media sudah dikembalikan', 409);
        }

        $item = $this->findMedia($loan->getMediaId());
        $daysLate = $loan->daysLate($now);
        // tarif per tipe media (polymorphism) x pengali per jenis member (polymorphism)
        $fee = $item === null ? 0.0 : round($item->calculateFee($daysLate) * $user->getFeeMultiplier(), 2);

        if ($item !== null) {
            $item->markReturned();
            $this->media->save($item);
        }
        $loan->complete($now, $fee);
        $this->loans->save($loan);

        return $this->present($loan, $now, $item);
    }

    /** F-08: riwayat pribadi (member) atau semua transaksi (admin), terbaru dulu. */
    public function history(User $user, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $isAdmin = $user->getRole() === 'admin';

        /** @var Loan[] $loans */
        $loans = $isAdmin ? $this->loans->findAll() : $this->loans->findByUserId($user->getId());

        $mediaMap = [];
        foreach ($this->media->findAll() as $m) {
            if ($m instanceof MediaItem) {
                $mediaMap[$m->getId()] = $m;
            }
        }
        $userMap = [];
        if ($isAdmin) {
            foreach ($this->users->findAll() as $u) {
                if ($u instanceof User) {
                    $userMap[$u->getId()] = $u->getName();
                }
            }
        }

        usort($loans, static fn (Loan $a, Loan $b): int =>
            [$b->getBorrowedAt(), $b->getId()] <=> [$a->getBorrowedAt(), $a->getId()]);

        return array_map(
            fn (Loan $l): array => $this->present(
                $l, $now, $mediaMap[$l->getMediaId()] ?? null,
                $isAdmin ? ($userMap[$l->getUserId()] ?? '(dihapus)') : null,
            ),
            $loans,
        );
    }

    private function findMedia(string $id): ?MediaItem
    {
        $m = $this->media->findById($id);
        return $m instanceof MediaItem ? $m : null;
    }

    /** Bentuk respons: data loan + info tambahan untuk UI. */
    private function present(Loan $loan, DateTimeImmutable $now, ?MediaItem $item, ?string $userName = null): array
    {
        $ref = $loan->getReturnedAt() !== null ? new DateTimeImmutable($loan->getReturnedAt()) : $now;
        $view = $loan->toArray() + [
            'mediaTitle' => $item?->getTitle() ?? '(media dihapus)',
            'mediaType'  => $item?->getType(),
            'daysLate'   => $loan->daysLate($ref),
            'isOverdue'  => $loan->isOverdue($now),
        ];
        if ($userName !== null) {
            $view['userName'] = $userName;
        }
        return $view;
    }
}
