<?php

namespace App\Tests\Unit\Exception;

use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $exception = new ValidationException();

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame(422, $exception->getCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function testCustomValues(): void
    {
        $errors = [
            'email' => 'Email invalide',
            'nom'   => 'Le nom est obligatoire',
        ];

        $exception = new ValidationException('Erreur de validation', $errors, 400);

        $this->assertSame('Erreur de validation', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertSame($errors, $exception->getErrors());
        $this->assertCount(2, $exception->getErrors());
    }

    public function testExtendsException(): void
    {
        $exception = new ValidationException();
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testPreviousExceptionChaining(): void
    {
        $previous = new \RuntimeException('original error');
        $exception = new ValidationException('wrapped', [], 422, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
