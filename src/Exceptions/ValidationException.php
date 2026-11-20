<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Input tidak valid. errors: ['field' => 'pesan'] -> respons 422. */
final class ValidationException extends RuntimeException
{
    public function __construct(private readonly array $errors, string $message = 'Data tidak valid')
    {
        parent::__construct($message, 422);
    }

    public function getErrors(): array { return $this->errors; }
}
