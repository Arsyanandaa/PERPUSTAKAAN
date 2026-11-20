<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(array $params, array $body, array $query): Response
    {
        $member = $this->auth->register($body);
        return Response::success($member->toPublicArray(), 201, 'Registrasi berhasil, silakan login');
    }

    public function login(array $params, array $body, array $query): Response
    {
        $user = $this->auth->login((string) ($body['email'] ?? ''), (string) ($body['password'] ?? ''));
        return Response::success($user->toPublicArray(), 200, 'Selamat datang, ' . $user->getName());
    }

    public function logout(array $params, array $body, array $query): Response
    {
        $this->auth->requireUser();
        $this->auth->logout();
        return Response::success(null, 200, 'Anda telah logout');
    }

    /** Cek session saat halaman dimuat. Tamu mendapat data: null (bukan error). */
    public function me(array $params, array $body, array $query): Response
    {
        return Response::success($this->auth->currentUser()?->toPublicArray());
    }

    public function upgrade(array $params, array $body, array $query): Response
    {
        $user = $this->auth->upgradeToPremium();
        return Response::success($user->toPublicArray(), 200, 'Upgrade ke premium berhasil (simulasi)');
    }
}
