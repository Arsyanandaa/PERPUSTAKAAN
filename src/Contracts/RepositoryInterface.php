<?php
declare(strict_types=1);

namespace App\Contracts;

/** Kontrak dasar semua repository. Entitas yang dikembalikan adalah object model. */
interface RepositoryInterface
{
    /** @return array<int, object> */
    public function findAll(): array;

    public function findById(string $id): ?object;

    /** Insert jika id belum ada, update jika sudah ada. */
    public function save(object $entity): void;

    public function delete(string $id): bool;
}
