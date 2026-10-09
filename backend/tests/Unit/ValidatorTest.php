<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\ValidationFailedException;
use App\Support\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testMultiErrorReturnsAllFieldErrors(): void
    {
        try {
            Validator::validate([], [
                'name' => 'required|string',
                'email' => 'required|email',
            ]);
            $this->fail('Expected ValidationFailedException');
        } catch (ValidationFailedException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame('VALIDATION_FAILED', $e->getErrorCode());
            $fields = $e->getFields();
            $this->assertArrayHasKey('name', $fields);
            $this->assertArrayHasKey('email', $fields);
        }
    }

    public function testEmailAndIntValidation(): void
    {
        $this->expectException(ValidationFailedException::class);
        Validator::validate([
            'email' => 'a@b',
            'quantity' => '12x',
        ], [
            'email' => 'email',
            'quantity' => 'int|min_value:1',
        ]);
    }

    public function testBoundaryLengths(): void
    {
        // Max 5 chars
        $valid = Validator::validate(['code' => '12345'], ['code' => 'string|max:5']);
        $this->assertSame('12345', $valid['code']);

        $this->expectException(ValidationFailedException::class);
        Validator::validate(['code' => '123456'], ['code' => 'string|max:5']);
    }

    public function testUnicodeStringLength(): void
    {
        // "अर्यन" has 5 characters (multibyte)
        $valid = Validator::validate(['name' => 'अर्यन'], ['name' => 'string|min:2|max:10']);
        $this->assertSame('अर्यन', $valid['name']);
    }

    public function testCoordinatesValidation(): void
    {
        $valid = Validator::validate([
            'lat' => 28.6139,
            'lng' => 77.2090,
        ], [
            'lat' => 'required|lat',
            'lng' => 'required|lng',
        ]);
        $this->assertSame(28.6139, $valid['lat']);
        $this->assertSame(77.2090, $valid['lng']);
    }
}
