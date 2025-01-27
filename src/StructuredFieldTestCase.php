<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function implode;
use function ltrim;

abstract class StructuredFieldTestCase extends TestCase
{
    protected static string $rootPath = __DIR__.'/../vendor/httpwg/structured-field-tests';

    /** @var array<string> */
    protected static array $httpWgTestFilenames;

    #[Test]
    #[DataProvider('httpWgDataProvider')]
    public function it_can_pass_http_wg_tests(TestRecord $test): void
    {
        if ($test->mustFail) {
            $this->expectException(SyntaxError::class);
        }

        $structuredField = TestDataType::from($test->type)->newStructuredField(implode(',', $test->raw));

        if (!$test->mustFail) {
            self::assertSame(implode(',', $test->canonical), $structuredField->toHttpValue());
        }
    }

    /**
     * @throws JsonException
     * @return iterable<string, array<TestRecord>>
     */
    public static function httpWgDataProvider(): iterable
    {
        foreach (static::$httpWgTestFilenames as $path) {
            foreach (TestRecordCollection::fromPath(static::$rootPath.'/'.ltrim($path, '/')) as $test) {
                yield $test->name => [$test];
            }
        }
    }
}
