<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use Countable;
use Iterator;
use IteratorAggregate;
use function array_key_exists;
use function array_keys;
use function array_values;
use function count;
use function explode;
use function ltrim;
use function preg_match;
use function rtrim;
use function trim;

/**
 * @implements IteratorAggregate<string, Item>
 */
final class Parameters implements Countable, IteratorAggregate, StructuredField
{
    private function __construct(
        /** @var array<string, Item> */
        private array $members = []
    ) {
    }

    /**
     * @param array{members:array<string, Item>} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['members']);
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<array-key, Item|Token|ByteSequence|float|int|bool|string> $members
     */
    public static function fromAssociative(iterable $members = []): self
    {
        $instance = new self();
        foreach ($members as $key => $member) {
            $instance->set($key, $member);
        }

        return $instance;
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each member is composed of an array with two elements
     * the first member represents the instance entry key
     * the second member represents the instance entry value
     *
     * @param iterable<array{0:string, 1:Item|ByteSequence|Token|bool|int|float|string}> $pairs
     */
    public static function fromPairs(iterable $pairs = []): self
    {
        $instance = new self();
        foreach ($pairs as [$key, $member]) {
            $instance->set($key, $member);
        }

        return $instance;
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     */
    public static function fromHttpValue(string $httpValue): self
    {
        $instance = new self();
        if ('' === $httpValue) {
            return $instance;
        }

        foreach (explode(';', $httpValue) as $pair) {
            [$key, $value] = explode('=', $pair, 2) + [1 => '?1'];
            if (rtrim($key) !== $key || ltrim($value) !== $value) {
                throw new SyntaxError("The HTTP textual representation `$pair` for a parameter pair contains invalid characters.");
            }

            $key = trim($key);
            if ('' !== $key) {
                $instance->set($key, Item::fromHttpValue($value));
            }
        }

        return $instance;
    }

    public function toHttpValue(): string
    {
        $returnValue = [];

        foreach ($this->members as $key => $val) {
            if (!$val->parameters()->isEmpty()) {
                throw new SerializationError('Parameters instances can not contain parameterized Items.');
            }

            $value = ';'.$key;
            if ($val->value() !== true) {
                $value .= '='.$val->toHttpValue();
            }

            $returnValue[] = $value;
        }

        return implode('', $returnValue);
    }

    public function count(): int
    {
        return count($this->members);
    }

    public function isEmpty(): bool
    {
        return [] === $this->members;
    }

    /**
     * @return Iterator<string, Item>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->members as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * Returns an iterable construct of dictionary pairs.
     *
     * @return Iterator<array{0:string, 1:Item}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, $member];
        }
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->members);
    }

    public function get(string $key): Item
    {
        if (!array_key_exists($key, $this->members)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->members[$key];
    }

    public function hasPair(int $index): bool
    {
        return null !== $this->filterIndex($index);
    }

    private function filterIndex(int $index): int|null
    {
        $max = count($this->members);

        return match (true) {
            [] === $this->members, 0 > $max + $index, 0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    /**
     * @return array{0:string, 1:Item}
     */
    public function pair(int $index): array
    {
        $offset = $this->filterIndex($index);
        if (null === $offset) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        return [
            array_keys($this->members)[$offset],
            array_values($this->members)[$offset],
        ];
    }

    /**
     * Add a member at the end of the instance if the key is new otherwise update the value associated with the key.
     */
    public function set(string $key, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        $member = self::filterMember($member);
        self::validate($key, $member);

        $this->members[$key] = $member;
    }

    private static function filterMember(Item|ByteSequence|Token|bool|int|float|string $member): Item
    {
        return match (true) {
            $member instanceof Item => $member,
            default => Item::from($member),
        };
    }

    private static function validate(string $key, Item $item): void
    {
        if (1 !== preg_match('/^[a-z*][a-z0-9.*_-]*$/', $key)) {
            throw new SyntaxError("The Parameters key `$key` contains invalid characters.");
        }

        if (!$item->parameters()->isEmpty()) {
            throw new SyntaxError('Parameters instances can not contain parameterized Items.');
        }
    }

    /**
     * Delete members associated with the list of submitted keys.
     */
    public function delete(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->members[$key]);
        }
    }

    /**
     * Remove all members from the instance.
     */
    public function clear(): void
    {
        $this->members = [];
    }

    /**
     * Add a member at the end of the instance if the key is new delete any previous reference to the key.
     */
    public function append(string $key, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        $member = self::filterMember($member);
        self::validate($key, $member);

        unset($this->members[$key]);

        $this->members[$key] = $member;
    }

    /**
     * Add a member at the beginning of the instance if the key is new delete any previous reference to the key.
     */
    public function prepend(string $key, Item|ByteSequence|Token|bool|int|float|string $member): void
    {
        $member = self::filterMember($member);
        self::validate($key, $member);

        unset($this->members[$key]);

        $this->members = [...[$key => $member], ...$this->members];
    }

    /**
     * Merge multiple instances.
     */
    public function merge(self ...$others): void
    {
        foreach ($others as $other) {
            foreach ($other as $key => $value) {
                $this->set($key, $value);
            }
        }
    }
}
