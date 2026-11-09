<?php

namespace Rss\Vlog\Core\Http;

/**
 * A JSON response from the log viewer API, for an adapter to send with its
 * framework's response class.
 */
final class ApiResponse
{
    /**
     * Encoding options. Invalid UTF-8 in log files is replaced rather than
     * failing the whole response.
     */
    private const JSON_OPTIONS = JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PARTIAL_OUTPUT_ON_ERROR;

    private int $status;

    /**
     * @var array<string, mixed>
     */
    private array $body;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(int $status, array $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function ok(array $body): self
    {
        return new self(200, $body);
    }

    public static function error(int $status, string $message): self
    {
        return new self($status, ['message' => $message]);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function json(): string
    {
        return (string) json_encode($this->body, self::JSON_OPTIONS);
    }

    /**
     * Get the headers every API response should have. Logs can hold
     * sensitive data, so responses are never cached.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
