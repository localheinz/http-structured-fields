<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Iterator;
use IteratorAggregate;
use JsonException;
use RuntimeException;
use function basename;
use function file_get_contents;
use function json_decode;

/**
 * @implements IteratorAggregate<string, TestRecord>
 * @phpstan-import-type RecordData from TestRecord
 */
final class TestRecordCollection implements IteratorAggregate
{
    /** @param array<string, TestRecord> $elements */
    private function __construct(private array $elements = [])
    {
    }

    public function add(TestRecord $test): void
    {
        if (isset($this->elements[$test->name])) {
            throw new RuntimeException('Already existing test name `'.$test->name.'`.');
        }

        $this->elements[$test->name] = $test;
    }

    /**
     * @throws JsonException|RuntimeException
     */
    public static function fromPath(string $path): self
    {
        if (false === $content = file_get_contents($path)) {
            throw new RuntimeException("unable to connect to the path `$path`.");
        }

        $suite = new self();
        /** @var array<RecordData> $records */
        $records = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        foreach ($records as $offset => $record) {
            $record['name'] = basename($path).' #'.($offset + 1).': '.$record['name'];
            $suite->add(TestRecord::fromDecoded($record));
        }

        return $suite;
    }

    /**
     * @return Iterator<string, TestRecord>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->elements as $element) {
            yield $element->name => $element;
        }
    }
}
