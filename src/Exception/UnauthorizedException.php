<?php

namespace App\Exception;

/**
 * Exception thrown when authorization fails
 */
class UnauthorizedException extends \Exception
{
    public function __construct(string $message = "Unauthorized access", int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
