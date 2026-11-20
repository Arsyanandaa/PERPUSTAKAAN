<?php
declare(strict_types=1);

namespace App\Core;

/** Pembungkus respons JSON. Format seragam: {success, data|message, errors?} */
final class Response
{
    private function __construct(
        private readonly int $status,
        private readonly array $body,
    ) {}

    public static function success(mixed $data = null, int $status = 200, ?string $message = null): self
    {
        $body = ['success' => true, 'data' => $data];
        if ($message !== null) {
            $body['message'] = $message;
        }
        return new self($status, $body);
    }

    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        return new self($status, $body);
    }

    public function getStatus(): int { return $this->status; }
    public function getBody(): array { return $this->body; }

    public function toJson(): string
    {
        return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
            ?: '{"success":false,"message":"Gagal membentuk JSON"}';
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $this->toJson();
    }
}
