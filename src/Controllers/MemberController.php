<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\AuthService;
use App\Services\MemberService;

final class MemberController
{
    public function __construct(
        private readonly MemberService $members,
        private readonly AuthService $auth,
    ) {}

    /** GET /members (admin) */
    public function index(array $params, array $body, array $query): Response
    {
        $this->auth->requireAdmin();
        return Response::success($this->members->list());
    }

    /** PATCH /members/{id}/status (admin). Body: {"status": "active" | "inactive"} */
    public function setStatus(array $params, array $body, array $query): Response
    {
        $this->auth->requireAdmin();
        $user = $this->members->setStatus((string) ($params['id'] ?? ''), $body['status'] ?? null);

        $msg = $user->isActive() ? 'Member diaktifkan' : 'Member dinonaktifkan';
        return Response::success($user->toPublicArray(), 200, $msg);
    }
}
