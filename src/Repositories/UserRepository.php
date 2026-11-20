<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use InvalidArgumentException;

final class UserRepository extends JsonRepository implements UserRepositoryInterface
{
    protected function hydrate(array $row): object
    {
        return User::fromArray($row);   // memilih Admin / Member / PremiumMember dari role + tier
    }

    protected function dehydrate(object $entity): array
    {
        if (!$entity instanceof User) {
            throw new InvalidArgumentException('UserRepository hanya dapat menyimpan User');
        }
        return $entity->toArray();      // memuat passwordHash, hanya untuk penyimpanan
    }

    /** Tidak peka huruf besar/kecil dan spasi di tepi. */
    public function findByEmail(string $email): ?User
    {
        $needle = strtolower(trim($email));
        if ($needle === '') {
            return null;
        }

        foreach ($this->readRows() as $row) {
            if (strtolower(trim((string) ($row['email'] ?? ''))) === $needle) {
                $user = $this->tryHydrate($row);
                return $user instanceof User ? $user : null;
            }
        }
        return null;
    }
}
