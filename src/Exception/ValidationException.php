<?php

namespace App\Exception;

/**
 * Exception thrown when validation fails
 */
class ValidationException extends \Exception
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, string> $errors */
    public function __construct(string $message = "Validation failed", array $errors = [], int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
