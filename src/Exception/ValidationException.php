<?php

namespace App\Exception;

/**
 * Exception thrown when validation fails
 */
class ValidationException extends \Exception
{
    public function __construct(string $message = "Validation failed", int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
