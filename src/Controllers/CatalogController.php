<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\AuthService;
use App\Services\CatalogService;

final class CatalogController
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly AuthService $auth,
    ) {}

    /** GET /catalog?q=&category=&type= (publik: tamu boleh melihat katalog) */
    public function index(array $params, array $body, array $query): Response
    {
        return Response::success($this->catalog->list(
            self::param($query, 'q'),
            self::param($query, 'category'),
            self::param($query, 'type'),
        ));
    }

    /** GET /catalog/categories (publik) */
    public function categories(array $params, array $body, array $query): Response
    {
        return Response::success($this->catalog->categories());
    }

    /** POST /catalog (admin) */
    public function store(array $params, array $body, array $query): Response
    {
        $this->auth->requireAdmin();
        $item = $this->catalog->create($body);
        return Response::success($item->toArray(), 201, 'Media ditambahkan');
    }

    /** PUT /catalog/{id} (admin) */
    public function update(array $params, array $body, array $query): Response
    {
        $this->auth->requireAdmin();
        $item = $this->catalog->update((string) ($params['id'] ?? ''), $body);
        return Response::success($item->toArray(), 200, 'Media diperbarui');
    }

    /** DELETE /catalog/{id} (admin) */
    public function destroy(array $params, array $body, array $query): Response
    {
        $this->auth->requireAdmin();
        $this->catalog->delete((string) ($params['id'] ?? ''));
        return Response::success(null, 200, 'Media dihapus');
    }

    /** Query string bisa berupa array (?q[]=x); hanya string yang dipakai, sisanya dianggap tidak ada. */
    private static function param(array $query, string $key): ?string
    {
        $v = $query[$key] ?? null;
        return is_string($v) ? $v : null;
    }
}
