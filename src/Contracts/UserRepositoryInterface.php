<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    /** Pencarian email tidak peka huruf besar/kecil. */
    public function findByEmail(string $email): ?User;
}
