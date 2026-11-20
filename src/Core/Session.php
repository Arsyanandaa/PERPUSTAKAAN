<?php
declare(strict_types=1);

namespace App\Core;

/** Pembungkus $_SESSION supaya service tidak menyentuh superglobal langsung. */
class Session
{
    private const USER_KEY = 'userId';

    public function start(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'path'     => '/',
            ]);
            session_start();
        }
        $_SESSION ??= [];
    }

    public function login(string $userId): void
    {
        $this->start();
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);   // cegah session fixation
        }
        $_SESSION[self::USER_KEY] = $userId;
    }

    public function getUserId(): ?string
    {
        $this->start();
        $id = $_SESSION[self::USER_KEY] ?? null;
        return is_string($id) ? $id : null;
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
