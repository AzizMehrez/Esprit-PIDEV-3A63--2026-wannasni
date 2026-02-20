<?php

namespace App\Exception;

/**
 * Exception thrown when a business rule is violated
 */
class BusinessRuleException extends \Exception
{
    public function __construct(string $message = "Business rule violation", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
