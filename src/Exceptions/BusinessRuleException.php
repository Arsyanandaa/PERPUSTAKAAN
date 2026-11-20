<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Pelanggaran aturan bisnis / otorisasi, membawa kode HTTP (401, 403, 404, 409). */
final class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 409)
    {
        parent::__construct($message, $httpStatus);
    }

    public function getHttpStatus(): int { return $this->httpStatus; }

    public static function unauthorized(string $m = 'Silakan login terlebih dahulu'): self { return new self($m, 401); }
    public static function forbidden(string $m = 'Anda tidak memiliki akses'): self { return new self($m, 403); }
    public static function notFound(string $m = 'Data tidak ditemukan'): self { return new self($m, 404); }
}
