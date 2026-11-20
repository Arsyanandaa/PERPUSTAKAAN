<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationException;
use App\Models\User;

/** F-05: admin melihat daftar member dan mengubah status aktif/nonaktif. */
final class MemberService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Semua member (admin tidak ikut), tanpa passwordHash.
     * @return array<int, array>
     */
    public function list(): array
    {
        $members = [];
        foreach ($this->users->findAll() as $u) {
            if ($u instanceof User && $u->getRole() === 'member') {
                $members[] = $u->toPublicArray();
            }
        }
        return $members;
    }

    /**
     * Ubah status member. Idempotent: mengirim status yang sama dua kali tidak error.
     * Akun admin diperlakukan sebagai "tidak ditemukan" agar tidak bisa dinonaktifkan lewat jalur ini.
     */
    public function setStatus(string $id, mixed $status): User
    {
        if (!in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE], true)) {
            throw new ValidationException(['status' => 'Status harus active atau inactive']);
        }

        $user = $this->users->findById($id);
        if (!$user instanceof User || $user->getRole() !== 'member') {
            throw BusinessRuleException::notFound('Member tidak ditemukan');
        }

        $status === User::STATUS_ACTIVE ? $user->activate() : $user->deactivate();
        $this->users->save($user);
        return $user;
    }
}
