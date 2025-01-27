<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    #[Test]
    #[DataProvider('invalidTokenString')]
    public function it_will_fail_on_invalid_token_string(string $httpValue): void
    {
        $this->expectException(SyntaxError::class);

        Token::fromString($httpValue);
    }

    /**
     * @return array<array{0:string}>
     */
    public static function invalidTokenString(): array
    {
        return [
            'token contains spaces inside' => ['a a'],
            'token contains non-ASCII characters' => ["a\u0001a"],
            'token starts with invalid characters' => ['3a'],
            'token contains double quote' => ['a"a'],
            'token contains comma' => ['a,a'],
        ];
    }

    #[Test]
    public function it_can_compare_instances(): void
    {
        $decoded = 'pretend';
        $source = 'cHJldGVuZC';
        $value1 = Token::fromString($source);
        $value2 = Token::fromString($source);
        $value3 = Token::fromString($decoded);

        self::assertTrue($value1->equals($value2));
        self::assertFalse($value1->equals($value3));
    }
}
